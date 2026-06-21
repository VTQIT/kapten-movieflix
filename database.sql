-- Netflix-Style Video Sharing Platform - Database Schema
-- Created: 2026-06-20

CREATE DATABASE IF NOT EXISTS video_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE video_platform;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    bio TEXT,
    role ENUM('user', 'admin', 'moderator') DEFAULT 'user',
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 0,
    storage_used BIGINT DEFAULT 0,
    storage_limit BIGINT DEFAULT 10737418240, -- 10GB default
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- CATEGORIES TABLE
-- ============================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'film',
    color VARCHAR(7) DEFAULT '#E50914',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- VIDEOS TABLE
-- ============================================
CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    filename VARCHAR(255) NOT NULL,
    thumbnail VARCHAR(255) DEFAULT 'default-thumb.jpg',
    duration INT DEFAULT 0, -- in seconds
    file_size BIGINT DEFAULT 0,
    file_type VARCHAR(50) DEFAULT 'video/mp4',
    resolution VARCHAR(20) DEFAULT '1920x1080',
    category_id INT,
    tags VARCHAR(500),
    status ENUM('public', 'private', 'unlisted', 'processing', 'blocked') DEFAULT 'processing',
    views INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    featured TINYINT(1) DEFAULT 0,
    allow_comments TINYINT(1) DEFAULT 1,
    processing_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id),
    INDEX idx_status (status),
    INDEX idx_featured (featured),
    INDEX idx_created_at (created_at),
    FULLTEXT INDEX idx_search (title, description, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- COMMENTS TABLE
-- ============================================
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    likes_count INT DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    status ENUM('active', 'hidden', 'flagged') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_video_id (video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- LIKES TABLE
-- ============================================
CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('like', 'dislike') DEFAULT 'like',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (video_id, user_id),
    INDEX idx_video_id (video_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- WATCH HISTORY TABLE
-- ============================================
CREATE TABLE watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    progress INT DEFAULT 0, -- seconds watched
    completed TINYINT(1) DEFAULT 0,
    watched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_watch (user_id, video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_watched_at (watched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('comment', 'like', 'subscribe', 'system', 'mention') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SETTINGS TABLE
-- ============================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    is_public TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SUBSCRIPTIONS TABLE (Followers)
-- ============================================
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    channel_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sub (subscriber_id, channel_id),
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- REPORTS TABLE (Moderation)
-- ============================================
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    target_type ENUM('video', 'comment', 'user') NOT NULL,
    target_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    details TEXT,
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    resolved_by INT,
    resolved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_target (target_type, target_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Default admin user (password: admin123 - CHANGE THIS!)
INSERT INTO users (username, email, password, role, status, email_verified) VALUES 
('admin', 'admin@localhost', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1);

-- Default categories
INSERT INTO categories (name, slug, description, icon, color, sort_order) VALUES
('Action', 'action', 'High-energy action videos', 'zap', '#E50914', 1),
('Comedy', 'comedy', 'Funny and entertaining content', 'smile', '#F5C518', 2),
('Drama', 'drama', 'Emotional and dramatic stories', 'heart', '#564D4D', 3),
('Documentary', 'documentary', 'Real-world documentaries', 'book-open', '#46D369', 4),
('Education', 'education', 'Learning and tutorials', 'graduation-cap', '#0071EB', 5),
('Gaming', 'gaming', 'Video game content', 'gamepad-2', '#E50914', 6),
('Music', 'music', 'Music videos and performances', 'music', '#E50914', 7),
('Science', 'science', 'Science and technology', 'atom', '#46D369', 8),
('Sports', 'sports', 'Sports and athletics', 'trophy', '#F5C518', 9),
('Technology', 'technology', 'Tech reviews and news', 'cpu', '#0071EB', 10);

-- Default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'StreamFlix', 'string', 'Website name', 1),
('site_description', 'Your personal video streaming platform', 'string', 'Site description', 1),
('max_upload_size', '1073741824', 'integer', 'Max upload size in bytes (1GB)', 1),
('allowed_formats', '["mp4","webm","mov","mkv"]', 'json', 'Allowed video formats', 1),
('thumb_width', '640', 'integer', 'Thumbnail width', 0),
('thumb_height', '360', 'integer', 'Thumbnail height', 0),
('comments_enabled', '1', 'boolean', 'Enable comments globally', 1),
('registration_enabled', '1', 'boolean', 'Allow new registrations', 1),
('maintenance_mode', '0', 'boolean', 'Maintenance mode', 0),
('default_storage', '10737418240', 'integer', 'Default storage per user (10GB)', 0),
('videos_per_page', '24', 'integer', 'Videos per page', 1),
('featured_videos_count', '6', 'integer', 'Number of featured videos', 0);

-- ============================================
-- TRIGGERS FOR COUNTERS
-- ============================================
DELIMITER //

CREATE TRIGGER after_like_insert 
AFTER INSERT ON likes
FOR EACH ROW
BEGIN
    UPDATE videos SET likes_count = likes_count + 1 WHERE id = NEW.video_id;
END//

CREATE TRIGGER after_like_delete 
AFTER DELETE ON likes
FOR EACH ROW
BEGIN
    UPDATE videos SET likes_count = likes_count - 1 WHERE id = OLD.video_id;
END//

CREATE TRIGGER after_comment_insert 
AFTER INSERT ON comments
FOR EACH ROW
BEGIN
    UPDATE videos SET comments_count = comments_count + 1 WHERE id = NEW.video_id;
END//

CREATE TRIGGER after_comment_delete 
AFTER DELETE ON comments
FOR EACH ROW
BEGIN
    UPDATE videos SET comments_count = comments_count - 1 WHERE id = OLD.video_id;
END//

DELIMITER ;
