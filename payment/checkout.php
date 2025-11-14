<?php
require_once __DIR__.'/../config/database.php';
session_start();

// युजर लगइन जाँच
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// कोर्स डाटा फेच गर्ने
$course_id = $_GET['course_id'];
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    die("कोर्स फेला परेन");
}
?>

<!-- खाल्टी पेमेन्ट फर्म -->
<script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js"></script>
<script>
    var config = {
        "publicKey": "test_public_key_...",
        "productIdentity": "<?= $course['id'] ?>",
        "productName": "<?= $course['title'] ?>",
        "productUrl": window.location.href,
        "paymentPreference": ["KHALTI"],
        "eventHandler": {
            onSuccess(payload) {
                // सफल भुक्तानी पछिको प्रक्रिया
                window.location.href = `/payment/success.php?token=${payload.token}`;
            },
            onError(error) {
                console.log(error);
            }
        }
    };
    var checkout = new KhaltiCheckout(config);
</script>
<button onclick="checkout.show({amount: <?= $course['price'] * 100 ?>})">खाल्टीमा पेमेन्ट गर्नुहोस्</button>