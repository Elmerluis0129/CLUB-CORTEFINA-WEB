-- Schema for Club Cortefina Database
-- MySQL Database: habbo_clubcortefina

CREATE DATABASE IF NOT EXISTS habbo_clubcortefina;
USE habbo_clubcortefina;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    habbo_username VARCHAR(255) NOT NULL,
    ccf_code VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    role ENUM('user','admin') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT '',
    rank INT(11) DEFAULT 1,
    verified TINYINT(1) DEFAULT 0,
    total_time INT(11) DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;