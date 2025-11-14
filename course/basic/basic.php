<?php
require_once __DIR__.'/../config/database.php';

// डाटाबेसबाट कोर्सहरू फेच गर्ने
try {
    $stmt = $pdo->query("SELECT * FROM courses WHERE is_free = 1 ORDER BY created_at DESC");
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    die("कोर्स फेच गर्दा त्रुटि: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>निःशुल्क कोर्सहरू</title>
</head>
<body>
    <h1>निःशुल्क ट्रेडिङ कोर्सहरू</h1>
    <ul>
        <?php foreach ($courses as $course): ?>
            <li>
                <h3><?= htmlspecialchars($course['title']) ?></h3>
                <p><?= htmlspecialchars($course['description']) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>