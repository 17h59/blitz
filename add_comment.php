<?php
// Ajouter un commentaire sur un post
header('Content-Type: application/json');
error_reporting(0);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'error' => 'Méthode invalide']));
}

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

$postId = intval($_POST['post_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$parentCommentId = isset($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : null;

if ($postId <= 0 || empty($content) || strlen($content) > 2000) {
    exit(json_encode(['success' => false, 'error' => 'Données invalides']));
}

// Échapper pour XSS
$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Vérifier que le post existe
    $stmt = $db->prepare('SELECT id FROM forum_posts WHERE id = ?');
    $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if (!$result) {
        exit(json_encode(['success' => false, 'error' => 'Post introuvable']));
    }
    
    // Insérer le commentaire
    $stmt = $db->prepare('INSERT INTO forum_comments (post_id, user_id, pseudo, content, parent_comment_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(3, $_SESSION['pseudo'], SQLITE3_TEXT);
    $stmt->bindValue(4, $content, SQLITE3_TEXT);
    $stmt->bindValue(5, $parentCommentId, SQLITE3_INTEGER);
    $stmt->bindValue(6, time(), SQLITE3_INTEGER);
    $stmt->execute();
    
    $commentId = $db->lastInsertRowID();
    
    // Incrémenter le compteur de commentaires du post
    $stmt = $db->prepare('UPDATE forum_posts SET comment_count = comment_count + 1 WHERE id = ?');
    $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Récupérer le commentaire créé avec les infos utilisateur
    $stmt = $db->prepare('
        SELECT c.id, c.post_id, c.user_id, c.pseudo, c.content, c.parent_comment_id, c.upvotes, c.downvotes, c.created_at, u.profile_pic
        FROM forum_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ');
    $stmt->bindValue(1, $commentId, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    $db->close();
    
    echo json_encode(['success' => true, 'comment' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
