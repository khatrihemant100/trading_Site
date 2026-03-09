<?php
// Check if courses are enabled
require_once __DIR__.'/../config/database.php';

function isCoursesEnabled() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'courses_enabled'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($result['setting_value']) && $result['setting_value'] == '1';
    } catch (PDOException $e) {
        // If table doesn't exist, return false (courses disabled by default)
        return false;
    }
}
?>
