<?php
// Initialise la base de données SQLite avec toutes les tables nécessaires
$dbFile = __DIR__ . '/data/blitz.db';

// Créer le dossier data si nécessaire
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
    echo "✅ Dossier data/ créé<br>";
}

// Créer le dossier uploads pour les médias
if (!file_exists(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
}
if (!file_exists(__DIR__ . '/uploads/profile_pics')) {
    mkdir(__DIR__ . '/uploads/profile_pics', 0755, true);
}
if (!file_exists(__DIR__ . '/uploads/voice')) {
    mkdir(__DIR__ . '/uploads/voice', 0755, true);
}
if (!file_exists(__DIR__ . '/uploads/forum')) {
    mkdir(__DIR__ . '/uploads/forum', 0755, true);
}
echo "✅ Dossiers uploads créés<br>";

// Supprimer l'ancienne base si elle existe
if (file_exists($dbFile)) {
    unlink($dbFile);
    echo "🗑️ Ancienne base supprimée<br>";
}

// Créer ou ouvrir la base
$db = new SQLite3($dbFile);
echo "✅ Nouvelle base créée<br>";

// Table des utilisateurs (persistants et temporaires)
$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pseudo TEXT UNIQUE NOT NULL,
    password_hash TEXT,
    bio TEXT DEFAULT "",
    profile_pic TEXT DEFAULT "",
    is_temporary INTEGER DEFAULT 0,
    is_admin INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL,
    last_login INTEGER NOT NULL
)');
echo "✅ Table 'users' créée<br>";

// Table des utilisateurs en ligne (heartbeat)
$db->exec('CREATE TABLE IF NOT EXISTS online_users (
    session_id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    is_ghost INTEGER DEFAULT 0,
    last_seen INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
echo "✅ Table 'online_users' créée<br>";

// Table des messages du chat général (limité à 300)
$db->exec('CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    message TEXT NOT NULL,
    voice_url TEXT DEFAULT NULL,
    timestamp INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_messages_timestamp ON messages(timestamp DESC)');
echo "✅ Table 'messages' créée<br>";

// Table des messages privés
$db->exec('CREATE TABLE IF NOT EXISTS dms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    from_user_id INTEGER NOT NULL,
    from_user TEXT NOT NULL,
    to_user_id INTEGER NOT NULL,
    to_user TEXT NOT NULL,
    message TEXT NOT NULL,
    voice_url TEXT DEFAULT NULL,
    timestamp INTEGER NOT NULL,
    read INTEGER DEFAULT 0,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_dms_users ON dms(from_user_id, to_user_id, timestamp DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_dms_read ON dms(to_user_id, read)');
echo "✅ Table 'dms' créée<br>";

// Table des groupes privés (max 10 membres)
$db->exec('CREATE TABLE IF NOT EXISTS groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    creator_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
)');
$db->exec('CREATE TABLE IF NOT EXISTS group_members (
    group_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    joined_at INTEGER NOT NULL,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
$db->exec('CREATE TABLE IF NOT EXISTS group_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    message TEXT NOT NULL,
    voice_url TEXT DEFAULT NULL,
    timestamp INTEGER NOT NULL,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
echo "✅ Tables de groupes créées<br>";

// Table des réactions (pour tous types de messages)
$db->exec('CREATE TABLE IF NOT EXISTS reactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_type TEXT NOT NULL,
    message_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    emoji TEXT NOT NULL,
    timestamp INTEGER NOT NULL,
    UNIQUE(message_type, message_id, user_id, emoji),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_reactions_message ON reactions(message_type, message_id)');
echo "✅ Table 'reactions' créée<br>";

// Table des posts du forum général
$db->exec('CREATE TABLE IF NOT EXISTS forum_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    media_url TEXT DEFAULT NULL,
    media_type TEXT DEFAULT NULL,
    youtube_url TEXT DEFAULT NULL,
    is_repost INTEGER DEFAULT 0,
    original_post_id INTEGER DEFAULT NULL,
    upvotes INTEGER DEFAULT 0,
    downvotes INTEGER DEFAULT 0,
    comment_count INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_forum_posts_date ON forum_posts(created_at DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_forum_posts_votes ON forum_posts(upvotes DESC, downvotes ASC)');
echo "✅ Table 'forum_posts' créée<br>";

// Table des commentaires du forum (imbriqués)
$db->exec('CREATE TABLE IF NOT EXISTS forum_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    content TEXT NOT NULL,
    parent_comment_id INTEGER DEFAULT NULL,
    upvotes INTEGER DEFAULT 0,
    downvotes INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES forum_comments(id) ON DELETE CASCADE
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_forum_comments_post ON forum_comments(post_id, created_at DESC)');
echo "✅ Table 'forum_comments' créée<br>";

// Table des votes sur les posts
$db->exec('CREATE TABLE IF NOT EXISTS post_votes (
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    vote_type INTEGER NOT NULL,
    PRIMARY KEY (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');

// Table des votes sur les commentaires
$db->exec('CREATE TABLE IF NOT EXISTS comment_votes (
    comment_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    vote_type INTEGER NOT NULL,
    PRIMARY KEY (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES forum_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
echo "✅ Tables de votes créées<br>";

// Table des posts personnels (pages utilisateurs)
$db->exec('CREATE TABLE IF NOT EXISTS personal_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    media_url TEXT DEFAULT NULL,
    media_type TEXT DEFAULT NULL,
    youtube_url TEXT DEFAULT NULL,
    is_repost INTEGER DEFAULT 0,
    original_post_id INTEGER DEFAULT NULL,
    original_post_type TEXT DEFAULT NULL,
    upvotes INTEGER DEFAULT 0,
    downvotes INTEGER DEFAULT 0,
    comment_count INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
echo "✅ Table 'personal_posts' créée<br>";

// Table des bans (utilisateurs et IP)
$db->exec('CREATE TABLE IF NOT EXISTS bans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ban_type TEXT NOT NULL,
    value TEXT NOT NULL,
    reason TEXT DEFAULT "",
    banned_by INTEGER NOT NULL,
    banned_at INTEGER NOT NULL,
    expires_at INTEGER DEFAULT NULL,
    UNIQUE(ban_type, value),
    FOREIGN KEY (banned_by) REFERENCES users(id)
)');
echo "✅ Table 'bans' créée<br>";

// Table des logs admin
$db->exec('CREATE TABLE IF NOT EXISTS admin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    details TEXT,
    timestamp INTEGER NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id)
)');
echo "✅ Table 'admin_logs' créée<br>";

// Table des sessions CSRF
$db->exec('CREATE TABLE IF NOT EXISTS csrf_tokens (
    user_id INTEGER NOT NULL,
    token TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)');
echo "✅ Table 'csrf_tokens' créée<br>";

// Créer un compte admin par défaut (pseudo: admin, mdp: admin123)
$adminExists = $db->querySingle('SELECT COUNT(*) FROM users WHERE is_admin = 1');
if ($adminExists == 0) {
    $stmt = $db->prepare('INSERT INTO users (pseudo, password_hash, is_temporary, is_admin, created_at, last_login) VALUES (?, ?, 0, 1, ?, ?)');
    $stmt->bindValue(1, 'admin', SQLITE3_TEXT);
    $stmt->bindValue(2, password_hash('admin123', PASSWORD_DEFAULT), SQLITE3_TEXT);
    $stmt->bindValue(3, time(), SQLITE3_INTEGER);
    $stmt->bindValue(4, time(), SQLITE3_INTEGER);
    $stmt->execute();
    echo "✅ Compte admin créé (pseudo: admin, mdp: admin123)<br>";
}

$db->close();

echo "<br><h2 style='color: green;'>🎉 INITIALISATION RÉUSSIE !</h2>";
echo "<p><a href='index.html' style='padding: 10px 20px; background: #8b5cf6; color: white; text-decoration: none; border-radius: 8px;'>Aller sur Blitz →</a></p>";
?>
