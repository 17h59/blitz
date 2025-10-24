<?php
// Vote sur un post (upvote ou downvote)
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
$voteType = intval($_POST['vote_type'] ?? 0); // 1 = upvote, -1 = downvote, 0 = remove vote

if ($postId <= 0 || !in_array($voteType, [-1, 0, 1])) {
    exit(json_encode(['success' => false, 'error' => 'Données invalides']));
}

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Vérifier si l'utilisateur a déjà voté
    $stmt = $db->prepare('SELECT vote_type FROM post_votes WHERE post_id = ? AND user_id = ?');
    $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    $oldVote = $result ? $result['vote_type'] : 0;
    
    // Transaction pour cohérence
    $db->exec('BEGIN TRANSACTION');
    
    try {
        if ($voteType === 0) {
            // Supprimer le vote
            $stmt = $db->prepare('DELETE FROM post_votes WHERE post_id = ? AND user_id = ?');
            $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            // Insérer ou mettre à jour le vote
            $stmt = $db->prepare('INSERT OR REPLACE INTO post_votes (post_id, user_id, vote_type) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(3, $voteType, SQLITE3_INTEGER);
            $stmt->execute();
        }
        
        // Recalculer les votes
        $upvotes = $db->querySingle("SELECT COUNT(*) FROM post_votes WHERE post_id = $postId AND vote_type = 1");
        $downvotes = $db->querySingle("SELECT COUNT(*) FROM post_votes WHERE post_id = $postId AND vote_type = -1");
        
        // Mettre à jour le post
        $stmt = $db->prepare('UPDATE forum_posts SET upvotes = ?, downvotes = ? WHERE id = ?');
        $stmt->bindValue(1, $upvotes, SQLITE3_INTEGER);
        $stmt->bindValue(2, $downvotes, SQLITE3_INTEGER);
        $stmt->bindValue(3, $postId, SQLITE3_INTEGER);
        $stmt->execute();
        
        $db->exec('COMMIT');
        
        $db->close();
        
        echo json_encode([
            'success' => true,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'user_vote' => $voteType
        ]);
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
