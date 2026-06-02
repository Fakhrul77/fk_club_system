<?php
/**
 * Module 4 – Manage Attendance & Reports
 * Helper classes: PointsCalculator, RecognitionLevelDeterminer, QRCodeGenerator
 * 
 * NOTE: resolveColumn() is defined in field_map.php - DO NOT redeclare it here!
 */

class PointsCalculator {
    private $pdo;

    private $points_map = [
        'General Event' => 5,
        'Workshop'      => 10,
        'Seminar'       => 8,
        'Competition'   => 15,
        'Volunteer'     => 12,
        'Leadership'    => 20,
        'Organizing'    => 25,
        'Default'       => 5,
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function calculateAttendancePoints($user_id, $event_id) {
        $stmt = $this->pdo->prepare("SELECT eventCategory FROM event WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        if (!$event) return 0;

        $cat = $event['eventCategory'] ?? 'Default';
        return $this->points_map[$cat] ?? $this->points_map['Default'];
    }

    public function getTotalPoints($user_id) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(pointsEarned), 0) AS total FROM activity_points WHERE user_id = ?"
        );
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    }

    public function recordPoints($user_id, $event_id, $points) {
        // FIXED: Using UPDATE then INSERT approach (no need for point_id/points_id)
        try {
            // Try to update existing record first
            $stmt = $this->pdo->prepare(
                "UPDATE activity_points SET pointsEarned = ?, awardedDate = NOW() 
                 WHERE user_id = ? AND event_id = ?"
            );
            $stmt->execute([$points, $user_id, $event_id]);
            
            // If no rows were updated, insert new record
            if ($stmt->rowCount() == 0) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO activity_points (user_id, event_id, pointsEarned, awardedDate) 
                     VALUES (?, ?, ?, NOW())"
                );
                return $stmt->execute([$user_id, $event_id, $points]);
            }
            return true;
        } catch (PDOException $e) {
            error_log("Points recording error: " . $e->getMessage());
            return false;
        }
    }

    public function removePoints($user_id, $event_id) {
        $stmt = $this->pdo->prepare(
            "DELETE FROM activity_points WHERE user_id = ? AND event_id = ?"
        );
        $stmt->execute([$user_id, $event_id]);
    }

    public function recalculateStudentPoints($user_id) {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT a.event_id"
          . " FROM attendance a"
          . " JOIN event_registration er ON a.registration_id = er.registration_id"
          . " WHERE er.user_id = ? AND a.attendanceStatus = 'Present'"
        );
        $stmt->execute([$user_id]);
        $events = $stmt->fetchAll();

        $total = 0;
        foreach ($events as $ev) {
            $pts = $this->calculateAttendancePoints($user_id, $ev['event_id']);
            $this->recordPoints($user_id, $ev['event_id'], $pts);
            $total += $pts;
        }
        return $total;
    }
}

class RecognitionLevelDeterminer {
    private $pdo;

    private $levels = [
        ['name' => 'Bronze', 'min' => 0,   'max' => 49],
        ['name' => 'Silver', 'min' => 50,  'max' => 99],
        ['name' => 'Gold',   'min' => 100, 'max' => 999999],
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function determineLevel($total_points) {
        $result = ['name' => 'Bronze', 'min_points' => 0, 'max_points' => 49, 'is_maximum' => false];
        foreach ($this->levels as $level) {
            if ($total_points >= $level['min'] && $total_points <= $level['max']) {
                $result = [
                    'name'       => $level['name'],
                    'min_points' => $level['min'],
                    'max_points' => $level['max'],
                    'is_maximum' => ($level['name'] === 'Gold'),
                ];
            }
        }
        return $result;
    }

    public function getStudentRecognitionLevel($user_id) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(pointsEarned), 0) AS total FROM activity_points WHERE user_id = ?"
        );
        $stmt->execute([$user_id]);
        $total = (int)$stmt->fetchColumn();
        return $this->determineLevel($total);
    }

    public function updateRecognitionLevel($user_id, $total_points) {
        $level = $this->determineLevel($total_points);
        try {
            // Check if recognition_level column exists in users table
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

    public function generateQRData($registration_id, $event_id, $matrix_number) {
        return $registration_id . '|' . $event_id . '|' . $matrix_number;
    }

    public function validateQRCode($qr_data) {
        $parts = explode('|', trim($qr_data));

        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Invalid QR code format'];
        }

        [$registration_id, $event_id, $matrix_number] = $parts;

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
            'matrix_number'   => $matrix_number,
        ];
    }
}
?>