<?php
require_once __DIR__.'/../config/database.php';

// URL बाट स्लग पढ्ने
$slug = 'stock-market-intro';  // यसलाई डायनामिक बनाउन सकिन्छ

try {
    $stmt = $pdo->prepare("SELECT blogs.*, users.username as author 
                          FROM blogs 
                          JOIN users ON blogs.author_id = users.id 
                          WHERE slug = ?");
    $stmt->execute([$slug]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ब्लग फेच गर्दा त्रुटि: " . $e->getMessage());
}

if (!$blog) {
    header("HTTP/1.0 404 Not Found");
    die("ब्लग फेला परेन");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($blog['title']) ?></title>
</head>
<body>
    <article>
        <h1><?= htmlspecialchars($blog['title']) ?></h1>
        <p>लेखक: <?= htmlspecialchars($blog['author']) ?></p>
        <div><?= htmlspecialchars($blog['content']) ?></div>
    </article>
</body>
</html>