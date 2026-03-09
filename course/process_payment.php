<?php
session_start();
require_once __DIR__.'/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $course_id = intval($_POST['course_id'] ?? 0);
        $course_price = floatval($_POST['course_price'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? '';
        $transaction_ref = $_POST['transaction_ref'] ?? '';
        
        if ($course_id <= 0 || $course_price <= 0) {
            throw new Exception("Invalid course information.");
        }
        
        if (!in_array($payment_method, ['bank_transfer', 'crypto'])) {
            throw new Exception("Invalid payment method.");
        }
        
        // Validate course exists
        $course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $course_stmt->execute([$course_id]);
        $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            throw new Exception("Course not found.");
        }
        
        // Check if user already purchased this course
        $existing_purchase = $pdo->prepare("SELECT * FROM course_purchases WHERE user_id = ? AND course_id = ? AND status = 'approved'");
        $existing_purchase->execute([$user_id, $course_id]);
        if ($existing_purchase->fetch()) {
            throw new Exception("You have already purchased this course.");
        }
        
        // Handle file upload
        $payment_proof = null;
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__.'/../uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
            }
            
            if ($_FILES['payment_proof']['size'] > 5 * 1024 * 1024) { // 5MB
                throw new Exception("File size too large. Maximum size is 5MB.");
            }
            
            $file_name = 'proof_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
                throw new Exception("Failed to upload payment proof.");
            }
            
            $payment_proof = 'uploads/payment_proofs/' . $file_name;
        } else {
            throw new Exception("Please upload payment proof.");
        }
        
        // Get payment details based on method
        $payment_details = [];
        if ($payment_method === 'bank_transfer') {
            $bank_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM payment_settings WHERE payment_type = 'bank_transfer' AND is_active = 1");
            $bank_stmt->execute();
            $bank_settings = $bank_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bank_settings as $setting) {
                $payment_details[$setting['setting_key']] = $setting['setting_value'];
            }
        } else if ($payment_method === 'crypto') {
            $crypto_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM payment_settings WHERE payment_type = 'crypto' AND is_active = 1");
            $crypto_stmt->execute();
            $crypto_settings = $crypto_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($crypto_settings as $setting) {
                $payment_details[$setting['setting_key']] = $setting['setting_value'];
            }
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Create payment record
        $payment_stmt = $pdo->prepare("
            INSERT INTO payments (user_id, course_id, amount, payment_method, transaction_id, payment_proof, payment_details, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $payment_details_json = json_encode($payment_details);
        $payment_stmt->execute([
            $user_id,
            $course_id,
            $course_price,
            $payment_method,
            $transaction_ref ?: null,
            $payment_proof,
            $payment_details_json
        ]);
        $payment_id = $pdo->lastInsertId();
        
        // Create course purchase record
        $purchase_stmt = $pdo->prepare("
            INSERT INTO course_purchases (user_id, course_id, payment_id, payment_method, amount, payment_proof, bank_details, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $bank_details_json = json_encode($payment_details);
        $purchase_stmt->execute([
            $user_id,
            $course_id,
            $payment_id,
            $payment_method,
            $course_price,
            $payment_proof,
            $bank_details_json
        ]);
        
        $pdo->commit();
        
        $message = "Payment proof submitted successfully! Your payment is under review. You will be notified once it's approved.";
        $message_type = 'success';
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Submission - NpLTrader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
        }
        body {
            background-color: var(--dark-bg);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .result-card {
            background-color: var(--dark-card);
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-card text-center">
            <?php if ($message_type === 'success'): ?>
                <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                <h2 class="text-success mb-3">Payment Submitted!</h2>
            <?php else: ?>
                <i class="fas fa-exclamation-circle fa-4x text-danger mb-4"></i>
                <h2 class="text-danger mb-3">Payment Submission Failed</h2>
            <?php endif; ?>
            
            <p class="mb-4"><?php echo htmlspecialchars($message); ?></p>
            
            <div class="d-flex gap-3 justify-content-center">
                <a href="course.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                </a>
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-th-large me-2"></i>Go to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
