-- Initialisation de la base de données Blitz

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pseudo TEXT UNIQUE NOT NULL,
    password_hash TEXT,
    bio TEXT DEFAULT "",
    profile_pic TEXT DEFAULT "",
    emoji_avatar TEXT DEFAULT "",
    is_temporary INTEGER DEFAULT 0,
    is_admin INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL,
    last_login INTEGER NOT NULL
);

-- Table des utilisateurs en ligne
CREATE TABLE IF NOT EXISTS online_users (
    session_id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    is_ghost INTEGER DEFAULT 0,
    last_seen INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des messages du chat général
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    message TEXT NOT NULL,
    voice_url TEXT DEFAULT NULL,
    timestamp INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_messages_timestamp ON messages(timestamp DESC);

-- Table des messages privés
CREATE TABLE IF NOT EXISTS dms (
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
);
CREATE INDEX IF NOT EXISTS idx_dms_users ON dms(from_user_id, to_user_id, timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_dms_read ON dms(to_user_id, read);

-- Tables des groupes privés
CREATE TABLE IF NOT EXISTS groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    creator_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_members (
    group_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    joined_at INTEGER NOT NULL,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    message TEXT NOT NULL,
    voice_url TEXT DEFAULT NULL,
    timestamp INTEGER NOT NULL,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des réactions
CREATE TABLE IF NOT EXISTS reactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_type TEXT NOT NULL,
    message_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    pseudo TEXT NOT NULL,
    emoji TEXT NOT NULL,
    timestamp INTEGER NOT NULL,
    UNIQUE(message_type, message_id, user_id, emoji),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_reactions_message ON reactions(message_type, message_id);

-- Table des posts du forum
CREATE TABLE IF NOT EXISTS forum_posts (
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
);
CREATE INDEX IF NOT EXISTS idx_forum_posts_date ON forum_posts(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_forum_posts_votes ON forum_posts(upvotes DESC, downvotes ASC);

-- Table des commentaires du forum
CREATE TABLE IF NOT EXISTS forum_comments (
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
);
CREATE INDEX IF NOT EXISTS idx_forum_comments_post ON forum_comments(post_id, created_at DESC);

-- Tables des votes
CREATE TABLE IF NOT EXISTS post_votes (
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    vote_type INTEGER NOT NULL,
    PRIMARY KEY (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comment_votes (
    comment_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    vote_type INTEGER NOT NULL,
    PRIMARY KEY (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES forum_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des posts personnels
CREATE TABLE IF NOT EXISTS personal_posts (
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
);

-- Table des bans
CREATE TABLE IF NOT EXISTS bans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ban_type TEXT NOT NULL,
    value TEXT NOT NULL,
    reason TEXT DEFAULT "",
    banned_by INTEGER NOT NULL,
    banned_at INTEGER NOT NULL,
    expires_at INTEGER DEFAULT NULL,
    UNIQUE(ban_type, value),
    FOREIGN KEY (banned_by) REFERENCES users(id)
);

-- Table des logs admin
CREATE TABLE IF NOT EXISTS admin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    details TEXT,
    timestamp INTEGER NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Table des sessions CSRF
CREATE TABLE IF NOT EXISTS csrf_tokens (
    user_id INTEGER NOT NULL,
    token TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
