<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__.'/../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'create_post':
            $content = trim($_POST['content'] ?? '');
            $post_type = $_POST['post_type'] ?? 'general';
            
            if (empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Content cannot be empty']);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO community_posts (user_id, content, post_type) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $content, $post_type]);
            $post_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'message' => 'Post created successfully', 'post_id' => $post_id]);
            break;
            
        case 'add_comment':
            $post_id = (int)($_POST['post_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            
            if (empty($content) || $post_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO community_comments (post_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$post_id, $user_id, $content]);
            
            // Update comments count
            $pdo->prepare("UPDATE community_posts SET comments_count = comments_count + 1 WHERE id = ?")->execute([$post_id]);
            
            $comment_id = $pdo->lastInsertId();
            
            // Get comment with user info
            $stmt = $pdo->prepare("
                SELECT c.*, u.username, u.profile_image 
                FROM community_comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'message' => 'Comment added', 'comment' => $comment]);
            break;
            
        case 'toggle_like_post':
            $post_id = (int)($_POST['post_id'] ?? 0);
            
            if ($post_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
                exit();
            }
            
            // Check if already liked
            $stmt = $pdo->prepare("SELECT id FROM community_post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Unlike
                $pdo->prepare("DELETE FROM community_post_likes WHERE post_id = ? AND user_id = ?")->execute([$post_id, $user_id]);
                $pdo->prepare("UPDATE community_posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?")->execute([$post_id]);
                $liked = false;
            } else {
                // Like
                $pdo->prepare("INSERT INTO community_post_likes (post_id, user_id) VALUES (?, ?)")->execute([$post_id, $user_id]);
                $pdo->prepare("UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$post_id]);
                $liked = true;
            }
            
            // Get updated likes count
            $stmt = $pdo->prepare("SELECT likes_count FROM community_posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $likes_count = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'liked' => $liked, 'likes_count' => (int)$likes_count]);
            break;
            
        case 'get_comments':
            $post_id = (int)($_GET['post_id'] ?? 0);
            
            if ($post_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                SELECT c.*, u.username, u.profile_image,
                       (SELECT COUNT(*) FROM community_comment_likes WHERE comment_id = c.id) as likes_count,
                       EXISTS(SELECT 1 FROM community_comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                FROM community_comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.post_id = ? 
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$user_id, $post_id]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'comments' => $comments]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
