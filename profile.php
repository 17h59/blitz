<?php
// Gestion des profils utilisateurs
header('Content-Type: application/json');
error_reporting(0);

session_start();

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function compressImage($source, $destination, $quality = 80) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } elseif ($info['mime'] == 'image/webp') {
        $image = imagecreatefromwebp($source);
    } else {
        return false;
    }
    
    // Redimensionner si trop grand
    $width = imagesx($image);
    $height = imagesy($image);
    $maxSize = 400;
    
    if ($width > $maxSize || $height > $maxSize) {
        $ratio = $width / $height;
        if ($width > $height) {
            $newWidth = $maxSize;
            $newHeight = $maxSize / $ratio;
        } else {
            $newHeight = $maxSize;
            $newWidth = $maxSize * $ratio;
        }
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Préserver la transparence pour PNG
        if ($info['mime'] == 'image/png') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }
    
    // Sauvegarder en JPEG pour réduire la taille
    imagejpeg($image, $destination, $quality);
    imagedestroy($image);
    
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = new SQLite3(__DIR__ . '/data/blitz.db');
        
        switch ($action) {
            case 'update_bio':
                if (!isset($_SESSION['user_id'])) {
                    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
                }

                $bio = sanitizeInput($_POST['bio'] ?? '');

                if (strlen($bio) > 1000) {
                    exit(json_encode(['success' => false, 'error' => 'Bio trop longue (max 1000 caractères)']));
                }

                $stmt = $db->prepare('UPDATE users SET bio = ? WHERE id = ?');
                $stmt->bindValue(1, $bio, SQLITE3_TEXT);
                $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();

                echo json_encode(['success' => true, 'bio' => $bio]);
                break;

            case 'update_emoji':
                if (!isset($_SESSION['user_id'])) {
                    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
                }

                $emoji = $_POST['emoji'] ?? '';

                // Validation basique : vérifier que c'est un seul caractère emoji
                if (mb_strlen($emoji) > 10) {
                    exit(json_encode(['success' => false, 'error' => 'Emoji invalide']));
                }

                $stmt = $db->prepare('UPDATE users SET emoji_avatar = ? WHERE id = ?');
                $stmt->bindValue(1, $emoji, SQLITE3_TEXT);
                $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();

                echo json_encode(['success' => true, 'emoji_avatar' => $emoji]);
                break;
                
            case 'upload_profile_pic':
                if (!isset($_SESSION['user_id'])) {
                    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
                }
                
                if (!isset($_FILES['profile_pic'])) {
                    exit(json_encode(['success' => false, 'error' => 'Aucun fichier']));
                }
                
                $file = $_FILES['profile_pic'];
                
                // Vérifications
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($file['type'], $allowedTypes)) {
                    exit(json_encode(['success' => false, 'error' => 'Format non supporté (JPG, PNG, WEBP uniquement)']));
                }
                
                if ($file['size'] > 200 * 1024) { // 200 Ko
                    // On va quand même essayer de compresser
                }
                
                // Supprimer l'ancienne photo
                $stmt = $db->prepare('SELECT profile_pic FROM users WHERE id = ?');
                $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
                $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if ($result && $result['profile_pic'] && file_exists(__DIR__ . $result['profile_pic'])) {
                    unlink(__DIR__ . $result['profile_pic']);
                }
                
                // Générer un nom unique
                $extension = 'jpg'; // Toujours en JPG après compression
                $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                $destination = __DIR__ . '/uploads/profile_pics/' . $filename;
                
                // Compresser et sauvegarder
                if (!compressImage($file['tmp_name'], $destination, 80)) {
                    exit(json_encode(['success' => false, 'error' => 'Erreur de compression']));
                }
                
                // Vérifier la taille finale
                if (filesize($destination) > 200 * 1024) {
                    // Compresser encore plus
                    compressImage($destination, $destination, 60);
                }
                
                $relativePath = '/uploads/profile_pics/' . $filename;
                
                // Mettre à jour la BDD
                $stmt = $db->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
                $stmt->bindValue(1, $relativePath, SQLITE3_TEXT);
                $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'profile_pic' => $relativePath]);
                break;
                
            case 'remove_profile_pic':
                if (!isset($_SESSION['user_id'])) {
                    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
                }
                
                // Supprimer l'ancienne photo
                $stmt = $db->prepare('SELECT profile_pic FROM users WHERE id = ?');
                $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
                $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if ($result && $result['profile_pic'] && file_exists(__DIR__ . $result['profile_pic'])) {
                    unlink(__DIR__ . $result['profile_pic']);
                }
                
                // Mettre à jour la BDD
                $stmt = $db->prepare('UPDATE users SET profile_pic = "" WHERE id = ?');
                $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();
                
                echo json_encode(['success' => true]);
                break;
                
            default:
                exit(json_encode(['success' => false, 'error' => 'Action invalide']));
        }
        
        $db->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer le profil d'un utilisateur
    $pseudo = $_GET['pseudo'] ?? '';
    
    if (empty($pseudo)) {
        exit(json_encode(['success' => false, 'error' => 'Pseudo requis']));
    }
    
    try {
        $db = new SQLite3(__DIR__ . '/data/blitz.db');

        $stmt = $db->prepare('SELECT pseudo, bio, profile_pic, emoji_avatar, created_at, is_admin FROM users WHERE pseudo = ?');
        $stmt->bindValue(1, $pseudo, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if (!$result) {
            exit(json_encode(['success' => false, 'error' => 'Utilisateur introuvable']));
        }
        
        // Compter les posts sur la page perso
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM personal_posts WHERE pseudo = ?');
        $stmt->bindValue(1, $pseudo, SQLITE3_TEXT);
        $countResult = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $result['post_count'] = $countResult['count'];
        
        $db->close();
        
        echo json_encode(['success' => true, 'user' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
    }
}
?>
