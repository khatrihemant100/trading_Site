<?php
require_once __DIR__.'/../config/database.php';
session_start();

// युजर लगइन जाँच
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// कोर्स डाटा फेच गर्ने
if (!isset($_GET['course_id']) || empty($_GET['course_id'])) {
    header("Location: ../course/course.php");
    exit();
}

$course_id = intval($_GET['course_id']);
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header("Location: ../course/course.php");
    exit();
}
?>

<!-- खाल्टी पेमेन्ट फर्म -->
<script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js"></script>
<script>
    var config = {
        "publicKey": "test_public_key_...",
        "productIdentity": "<?= htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8'); ?>",
        "productName": "<?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>",
        "productUrl": window.location.href,
        "paymentPreference": ["KHALTI"],
        "eventHandler": {
            onSuccess(payload) {
                // सफल भुक्तानी पछिको प्रक्रिया
                window.location.href = "../payment/success.php?token=" + encodeURIComponent(payload.token);
            },
            onError(error) {
                console.log(error);
                alert("Payment failed. Please try again.");
            }
        }
    };
    var checkout = new KhaltiCheckout(config);
</script>
<button onclick="checkout.show({amount: <?= intval($course['price'] * 100); ?>})">खाल्टीमा पेमेन्ट गर्नुहोस्</button>