<?php
require_once __DIR__.'/../config/database.php';

header('Content-Type: application/json');

$payment_type = $_GET['type'] ?? '';

if (!in_array($payment_type, ['bank_transfer', 'crypto'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment type']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM payment_settings WHERE payment_type = ? AND is_active = 1");
    $stmt->execute([$payment_type]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = ['success' => true];
    foreach ($settings as $setting) {
        $result[$setting['setting_key']] = $setting['setting_value'];
    }
    
    echo json_encode($result);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
