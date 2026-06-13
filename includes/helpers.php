<?php
/**
 * Module 4 – Manage Attendance & Reports
 * Helper classes: PointsCalculator, RecognitionLevelDeterminer, QRCodeGenerator
 * 
 * NOTE: resolveColumn() is defined in field_map.php - DO NOT redeclare it here!
 */

class PointsCalculator {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Calculate points based on attendance status ONLY
     * Present on time = +10 points
     * Late arrival = +5 points
     * Volunteer/Helper = +5 points
     * Absent = -10 points
     */
    public function calculatePointsByStatus($attendance_status) {
        switch ($attendance_status) {
            case 'Present':
                return 10;
            case 'Late':
                return 5;
            case 'Volunteer':
                return 5;
            case 'Absent':
                return -10;
            default:
                return 0;
        }
    }

    /**
     * Get total points for a student
     */
    public function getTotalPoints($user_id) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(pointsEarned), 0) AS total FROM activity_points WHERE user_id = ?"
        );
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Record points for a student (UPDATE if exists, INSERT if not)
     */
    public function recordPoints($user_id, $event_id, $points) {
        // Check if points already recorded for this event
        $stmt = $this->pdo->prepare(
            "SELECT points_id FROM activity_points WHERE user_id = ? AND event_id = ?"
        );
        $stmt->execute([$user_id, $event_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing record
            $stmt = $this->pdo->prepare(
                "UPDATE activity_points SET pointsEarned = ?, awardedDate = NOW() WHERE user_id = ? AND event_id = ?"
            );
            return $stmt->execute([$points, $user_id, $event_id]);
        } else {
            // Insert new record
            $stmt = $this->pdo->prepare(
                "INSERT INTO activity_points (user_id, event_id, pointsEarned, awardedDate) VALUES (?, ?, ?, NOW())"
            );
            return $stmt->execute([$user_id, $event_id, $points]);
        }
    }

    /**
     * Remove points for a student (when attendance is deleted)
     */
    public function removePoints($user_id, $event_id) {
        $stmt = $this->pdo->prepare(
            "DELETE FROM activity_points WHERE user_id = ? AND event_id = ?"
        );
        $stmt->execute([$user_id, $event_id]);
    }

    /**
     * Calculate and record points based on attendance status
     */
    public function processAttendancePoints($user_id, $event_id, $attendance_status) {
        $points = $this->calculatePointsByStatus($attendance_status);
        $this->recordPoints($user_id, $event_id, $points);
        return $points;
    }
}

class RecognitionLevelDeterminer {
    private $pdo;

    private $levels = [
        ['name' => 'Warning', 'min' => 0,   'max' => 19],
        ['name' => 'Certificate Eligible', 'min' => 20,  'max' => 49],
        ['name' => 'Active Student Award', 'min' => 50,  'max' => 79],
        ['name' => 'Outstanding Participant', 'min' => 80, 'max' => 999999],
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function determineLevel($total_points) {
        $result = ['name' => 'Warning', 'min_points' => 0, 'max_points' => 19, 'is_maximum' => false];
        foreach ($this->levels as $level) {
            if ($total_points >= $level['min'] && $total_points <= $level['max']) {
                $result = [
                    'name'       => $level['name'],
                    'min_points' => $level['min'],
                    'max_points' => $level['max'],
                    'is_maximum' => ($level['name'] === 'Outstanding Participant'),
                ];
            }
        }
        return $result;
    }

    public function getStudentRecognitionLevel($user_id) {
        $calculator = new PointsCalculator($this->pdo);
        $total = $calculator->getTotalPoints($user_id);
        return $this->determineLevel($total);
    }

    public function updateRecognitionLevel($user_id, $total_points) {
        $level = $this->determineLevel($total_points);
        try {
            // Check if recognition_level column exists
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM users LIKE 'recognition_level'");
            $stmt->execute();
            $column_exists = $stmt->fetch();
            
            if ($column_exists) {
                $stmt = $this->pdo->prepare(
                    "UPDATE users SET recognition_level = ?, points_updated = NOW() WHERE user_id = ?"
                );
                $stmt->execute([$level['name'], $user_id]);
            }
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getAllLevels() {
        return $this->levels;
    }

    public function getPointsToNextLevel($current_points) {
        foreach ($this->levels as $level) {
            if ($level['min'] > $current_points) {
                return $level['min'] - $current_points;
            }
        }
        return 0;
    }
}

class QRCodeGenerator {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function generateQRData($registration_id, $event_id, $student_id) {
        return $registration_id . '|' . $event_id . '|' . $student_id;
    }

    public function validateQRCode($qr_data) {
        $parts = explode('|', trim($qr_data));

        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Invalid QR code format'];
        }

        [$registration_id, $event_id, $student_id] = $parts;

        if (!is_numeric($registration_id) || !is_numeric($event_id)) {
            return ['valid' => false, 'error' => 'Invalid QR code data'];
        }

        $stmt = $this->pdo->prepare(
            "SELECT er.status AS registrationStatus, e.status AS eventStatus"
          . " FROM event_registration er"
          . " JOIN event e ON er.event_id = e.event_id"
          . " WHERE er.registration_id = ? AND er.event_id = ?"
        );
        $stmt->execute([$registration_id, $event_id]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['valid' => false, 'error' => 'Registration not found'];
        }
        if ($row['registrationStatus'] !== 'Confirmed') {
            return ['valid' => false, 'error' => 'Registration is not confirmed'];
        }
        if (!in_array($row['eventStatus'], ['UPCOMING', 'ONGOING'])) {
            return ['valid' => false, 'error' => 'Event is not accepting check-ins'];
        }

        return [
            'valid'           => true,
            'registration_id' => (int)$registration_id,
            'event_id'        => (int)$event_id,
            'student_id'      => $student_id,
        ];
    }
}
?>