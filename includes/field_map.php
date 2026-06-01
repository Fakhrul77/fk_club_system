<?php
/**
 * Database column name resolver
 * Handles different column naming conventions across modules
 */

if (!function_exists('resolveColumn')) {
    function resolveColumn($pdo, $table, $possible_names) {
        try {
            $stmt = $pdo->prepare("DESCRIBE `$table`");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($possible_names as $name) {
                if (in_array($name, $columns)) {
                    return $name;
                }
            }
        } catch(PDOException $e) {
            // Table might not exist yet
        }
        return $possible_names[0] ?? null;
    }
}
?>