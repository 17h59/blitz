<?php
// Gestion de l'authentification (inscription, connexion)
header('Content-Type: application/json');
error_reporting(0);

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken($db, $userId) {
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare('INSERT OR REPLACE INTO csrf_tokens (user_id, token, created_at) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $token, SQLITE3_TEXT);
    $stmt->bindValue(3, time(), SQLITE3_INTEGER);
    $stmt->execute();
    return $token;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'error' => 'Méthode invalide']));
}

$action = $_POST['action'] ?? '';

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    switch ($action) {
        case 'register':
            $pseudo = sanitizeInput($_POST['pseudo'] ?? '');
            $password = $_POST['password'] ?? '';
            $isTemporary = intval($_POST['is_temporary'] ?? 0);
            
            // Validation
            if (empty($pseudo) || strlen($pseudo) > 20 || strlen($pseudo) < 2) {
                exit(json_encode(['success' => false, 'error' => 'Pseudo invalide (2-20 caractères)']));
            }
            
            if (!$isTemporary && (empty($password) || strlen($password) < 6)) {
                exit(json_encode(['success' => false, 'error' => 'Mot de passe trop court (min 6 caractères)']));
            }
            
            // Vérifier si le pseudo existe déjà
            $stmt = $db->prepare('SELECT id FROM users WHERE pseudo = ?');
            $stmt->bindValue(1, $pseudo, SQLITE3_TEXT);
            $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            
            if ($result) {
                exit(json_encode(['success' => false, 'error' => 'Ce pseudo est déjà pris']));
            }
            
            // Créer l'utilisateur
            $passwordHash = $isTemporary ? null : password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (pseudo, password_hash, is_temporary, created_at, last_login) VALUES (?, ?, ?, ?, ?)');
            $stmt->bindValue(1, $pseudo, SQLITE3_TEXT);
            $stmt->bindValue(2, $passwordHash, SQLITE3_TEXT);
            $stmt->bindValue(3, $isTemporary, SQLITE3_INTEGER);
            $stmt->bindValue(4, time(), SQLITE3_INTEGER);
            $stmt->bindValue(5, time(), SQLITE3_INTEGER);
            $stmt->execute();
            
            $userId = $db->lastInsertRowID();
            $sessionId = bin2hex(random_bytes(16));
            $csrfToken = generateCSRFToken($db, $userId);
            
            // Créer la session
            session_start();
            $_SESSION['user_id'] = $userId;
            $_SESSION['pseudo'] = $pseudo;
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['csrf_token'] = $csrfToken;
            $_SESSION['is_temporary'] = $isTemporary;
            
            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'pseudo' => $pseudo,
                'session_id' => $sessionId,
                'csrf_token' => $csrfToken,
                'is_temporary' => $isTemporary,
                'bio' => '',
                'profile_pic' => '',
                'emoji_avatar' => ''
            ]);
            break;
            
        case 'login':
            $pseudo = sanitizeInput($_POST['pseudo'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($pseudo) || empty($password)) {
                exit(json_encode(['success' => false, 'error' => 'Pseudo et mot de passe requis']));
            }
            
            // Récupérer l'utilisateur
            $stmt = $db->prepare('SELECT id, pseudo, password_hash, is_temporary, is_admin, bio, profile_pic, emoji_avatar FROM users WHERE pseudo = ?');
            $stmt->bindValue(1, $pseudo, SQLITE3_TEXT);
            $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

            if (!$result) {
                exit(json_encode(['success' => false, 'error' => 'Pseudo ou mot de passe incorrect']));
            }

            // Vérifier le mot de passe
            if (!password_verify($password, $result['password_hash'])) {
                exit(json_encode(['success' => false, 'error' => 'Pseudo ou mot de passe incorrect']));
            }

            // Mettre à jour last_login
            $stmt = $db->prepare('UPDATE users SET last_login = ? WHERE id = ?');
            $stmt->bindValue(1, time(), SQLITE3_INTEGER);
            $stmt->bindValue(2, $result['id'], SQLITE3_INTEGER);
            $stmt->execute();

            $sessionId = bin2hex(random_bytes(16));
            $csrfToken = generateCSRFToken($db, $result['id']);

            // Créer la session
            session_start();
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['pseudo'] = $result['pseudo'];
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['csrf_token'] = $csrfToken;
            $_SESSION['is_temporary'] = $result['is_temporary'];
            $_SESSION['is_admin'] = $result['is_admin'];

            echo json_encode([
                'success' => true,
                'user_id' => $result['id'],
                'pseudo' => $result['pseudo'],
                'session_id' => $sessionId,
                'csrf_token' => $csrfToken,
                'is_temporary' => $result['is_temporary'],
                'is_admin' => $result['is_admin'],
                'bio' => $result['bio'],
                'profile_pic' => $result['profile_pic'],
                'emoji_avatar' => $result['emoji_avatar'] ?? ''
            ]);
            break;
            
        case 'delete_account':
            session_start();
            if (!isset($_SESSION['user_id'])) {
                exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
            }
            
            $userId = $_SESSION['user_id'];
            
            // Supprimer la photo de profil
            $stmt = $db->prepare('SELECT profile_pic FROM users WHERE id = ?');
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($result && $result['profile_pic'] && file_exists(__DIR__ . $result['profile_pic'])) {
                unlink(__DIR__ . $result['profile_pic']);
            }
            
            // Supprimer l'utilisateur (CASCADE supprimera tout le reste)
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->execute();
            
            // Détruire la session
            session_destroy();
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            exit(json_encode(['success' => false, 'error' => 'Action invalide']));
    }
    
    $db->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
