<?php
require_once 'config.php';

$conn = getDBConnection();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Database connected successfully!<br>";

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "Users table exists!<br>";

    // Count users
    $count_result = $conn->query("SELECT COUNT(*) as count FROM users");
    $count = $count_result->fetch_assoc()['count'];
    echo "Total users: $count<br>";

    if ($count > 0) {
        // Show first user
        $user_result = $conn->query("SELECT id, username, habbo_username, verified FROM users LIMIT 1");
        $user = $user_result->fetch_assoc();
        echo "Sample user: ID=" . $user['id'] . ", Username=" . $user['username'] . ", Habbo=" . $user['habbo_username'] . ", Verified=" . ($user['verified'] ? 'Yes' : 'No') . "<br>";
    }
} else {
    echo "Users table does not exist!<br>";
}

$conn->close();
?>
