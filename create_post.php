<?php
// Création d'un post sur le forum général
header('Content-Type: application/json');
error_reporting(0);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'error' => 'Méthode invalide']));
}

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$youtubeUrl = trim($_POST['youtube_url'] ?? '');

// Validation
if (empty($title) || strlen($title) > 200) {
    exit(json_encode(['success' => false, 'error' => 'Titre invalide (max 200 caractères)']));
}

if (empty($content) || strlen($content) > 5000) {
    exit(json_encode(['success' => false, 'error' => 'Contenu invalide (max 5000 caractères)']));
}

// Échapper pour XSS
$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
$youtubeUrl = htmlspecialchars($youtubeUrl, ENT_QUOTES, 'UTF-8');

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Gérer l'upload d'image si présent
    $mediaUrl = null;
    $mediaType = null;
    
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media'];
        
        // Vérifier le type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            exit(json_encode(['success' => false, 'error' => 'Format non supporté (JPG, PNG, WEBP, GIF uniquement)']));
        }
        
        // Vérifier la taille (max 3Mo)
        if ($file['size'] > 3 * 1024 * 1024) {
            exit(json_encode(['success' => false, 'error' => 'Image trop volumineuse (max 3MB)']));
        }
        
        // Créer le dossier si nécessaire
        if (!file_exists(__DIR__ . '/uploads/forum')) {
            mkdir(__DIR__ . '/uploads/forum', 0755, true);
        }
        
        // Nom unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'post_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
        $destination = __DIR__ . '/uploads/forum/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $mediaUrl = '/uploads/forum/' . $filename;
            $mediaType = 'image';
        }
    }
    
    // Insérer le post
    $stmt = $db->prepare('INSERT INTO forum_posts (user_id, pseudo, title, content, media_url, media_type, youtube_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $_SESSION['pseudo'], SQLITE3_TEXT);
    $stmt->bindValue(3, $title, SQLITE3_TEXT);
    $stmt->bindValue(4, $content, SQLITE3_TEXT);
    $stmt->bindValue(5, $mediaUrl, SQLITE3_TEXT);
    $stmt->bindValue(6, $mediaType, SQLITE3_TEXT);
    $stmt->bindValue(7, $youtubeUrl, SQLITE3_TEXT);
    $stmt->bindValue(8, time(), SQLITE3_INTEGER);
    $stmt->bindValue(9, time(), SQLITE3_INTEGER);
    $stmt->execute();
    
    $postId = $db->lastInsertRowID();
    
    $db->close();
    
    echo json_encode(['success' => true, 'post_id' => $postId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
