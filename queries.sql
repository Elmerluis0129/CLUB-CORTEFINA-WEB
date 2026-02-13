-- Useful SQL queries for Club Cortefina Database
-- These queries can be used for maintenance, reporting, and debugging

USE habbo_clubcortefina;

-- 1. Get all users with their verification status
SELECT id, username, habbo_username, email, verified, created_at
FROM users
ORDER BY created_at DESC;

-- 2. Get user count statistics
SELECT
    COUNT(*) as total_users,
    SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users
FROM users;

-- 3. Get recent registrations (last 30 days)
SELECT username, habbo_username, email, created_at
FROM users
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY created_at DESC;

-- 4. Get users by rank
SELECT username, habbo_username, rank, role
FROM users
ORDER BY rank DESC, username ASC;

-- 5. Get all events with creator info
SELECT e.title, e.description, e.date, e.time, u.username as created_by, e.created_at
FROM events e
LEFT JOIN users u ON e.created_by = u.username
ORDER BY e.date DESC, e.time DESC;

-- 6. Get upcoming events (next 7 days)
SELECT title, description, date, time
FROM events
WHERE date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY date ASC, time ASC;

-- 7. Get all photos with uploader info
SELECT p.title, p.url, p.description, u.username as uploaded_by, p.uploaded_at
FROM photos p
LEFT JOIN users u ON p.uploaded_by = u.username
ORDER BY p.uploaded_at DESC;

-- 8. Get all games with creator info
SELECT g.name, g.room_link, g.description, u.username as created_by, g.created_at
FROM games g
LEFT JOIN users u ON g.created_by = u.username
ORDER BY g.created_at DESC;

-- 9. Get user activity summary
SELECT
    u.username,
    COUNT(DISTINCT e.id) as events_created,
    COUNT(DISTINCT p.id) as photos_uploaded,
    COUNT(DISTINCT g.id) as games_created
FROM users u
LEFT JOIN events e ON u.username = e.created_by
LEFT JOIN photos p ON u.username = p.uploaded_by
LEFT JOIN games g ON u.username = g.created_by
GROUP BY u.id, u.username
ORDER BY (events_created + photos_uploaded + games_created) DESC;

-- 10. Clean up old data (example: events older than 1 year)
-- WARNING: This will permanently delete data
-- DELETE FROM events WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- 11. Reset user verification status (for testing)
-- UPDATE users SET verified = 0 WHERE verified = 1;

-- 12. Get database size information
SELECT
    table_name,
    ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'habbo_clubcortefina'
ORDER BY (data_length + index_length) DESC;

-- 13. Find duplicate usernames (should not happen due to unique constraint)
SELECT username, COUNT(*) as count
FROM users
GROUP BY username
HAVING COUNT(*) > 1;

-- 14. Get users who haven't verified their accounts
SELECT username, habbo_username, ccf_code, created_at
FROM users
WHERE verified = 0
ORDER BY created_at DESC;

-- 15. Backup user data (export format)
SELECT
    CONCAT('INSERT INTO users (username, habbo_username, ccf_code, email, password, role, rank, verified) VALUES (',
           QUOTE(username), ', ',
           QUOTE(habbo_username), ', ',
           QUOTE(ccf_code), ', ',
           QUOTE(email), ', ',
           QUOTE(password), ', ',
           QUOTE(role), ', ',
           rank, ', ',
           verified, ');') as backup_sql
FROM users;
