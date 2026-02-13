-- Migration scripts for Club Cortefina Database
-- Run these migrations in order to update existing database schema

USE habbo_clubcortefina;

-- Migration 1: Add missing columns to users table (if not already present)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS habbo_username VARCHAR(255) NOT NULL AFTER username,
ADD COLUMN IF NOT EXISTS ccf_code VARCHAR(255) NOT NULL AFTER habbo_username,
ADD COLUMN IF NOT EXISTS verified TINYINT(1) DEFAULT 0;

-- Migration 2: Add email column if missing
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email VARCHAR(100) NOT NULL AFTER ccf_code,
ADD UNIQUE KEY IF NOT EXISTS email (email);

-- Migration 3: Add role, avatar, and rank columns if missing
ALTER TABLE users
ADD COLUMN IF NOT EXISTS role ENUM('user','admin') DEFAULT 'user',
ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT '',
ADD COLUMN IF NOT EXISTS rank INT(11) DEFAULT 1;

-- Migration 4: Create events table if not exists
CREATE TABLE IF NOT EXISTS events (
    id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    time TIME NOT NULL,
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 5: Create photos table if not exists
CREATE TABLE IF NOT EXISTS photos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    description TEXT,
    uploaded_by VARCHAR(50) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 6: Create games table if not exists
CREATE TABLE IF NOT EXISTS games (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    room_link VARCHAR(500) NOT NULL,
    description TEXT,
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 7: Insert default admin user if not exists
INSERT IGNORE INTO users (username, habbo_username, ccf_code, email, password, role, rank, verified) VALUES
('admin', 'AdminHabbo', 'CCF-ADMIN-000', 'admin@clubcortefina.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 7, 1);

-- Migration 8: Insert sample data if tables are empty
INSERT INTO events (title, description, date, time, created_by)
SELECT 'DJ Night', 'Weekly DJ night with the best electronic music', '2024-01-15', '20:00:00', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM events LIMIT 1);

INSERT INTO photos (title, url, description, uploaded_by)
SELECT 'Dance Floor', 'https://via.placeholder.com/300x200/8a2be2/ffffff?text=Dance+Floor', 'Our amazing dance floor', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM photos LIMIT 1);

INSERT INTO games (name, room_link, description, created_by)
SELECT 'Battle Royale', 'https://www.habbo.es/room/123456789', 'Epic battle royale game', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM games LIMIT 1);
