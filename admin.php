 <?php
session_start();
require_once 'config.php';

// Check if user is logged in and has admin rank (5, 6, or 7)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Load user from database
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, username, habbo_username, rank FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
} else {
    echo "Usuario no encontrado.";
    exit;
}

$stmt->close();

// Check admin rank (5=Moderator, 6=Administrator, 7=Owner)
$user_rank = isset($user['rank']) ? $user['rank'] : 1;

if ($user_rank < 5) {
    echo "Acceso denegado. No tienes permisos de administrador.";
    exit;
}

// Get rank name
$rank_names = [
    5 => 'Moderador',
    6 => 'Administrador',
    7 => 'Dueño'
];
$user_rank_name = isset($rank_names[$user_rank]) ? $rank_names[$user_rank] : 'Usuario';
$rank_names[1] = 'Bartender';
$rank_names[2] = 'Diva';
$rank_names[3] = 'Seguridad';
$rank_names[4] = 'RH';
$rank_names[7] = 'Dueno';
$user_rank_name = isset($rank_names[$user_rank]) ? $rank_names[$user_rank] : 'Usuario';

// Normalized labels used across the UI
$rank_names = [
    1 => 'Bartender',
    2 => 'Diva',
    3 => 'Seguridad',
    4 => 'RH',
    5 => 'Moderador',
    6 => 'Administrador',
    7 => 'Dueno'
];
$user_rank_name = isset($rank_names[$user_rank]) ? $rank_names[$user_rank] : 'Usuario';

function getRequiredMissionByRank($rank) {
    $missions = [
        1 => 'BARTERDER [CLUB CORTEFINA]',
        2 => 'DIVA [CLUB CORTEFINA]',
        3 => 'SEGURIDAD [CLUB CORTEFINA]',
    ];

    return isset($missions[(int) $rank]) ? $missions[(int) $rank] : '';
}

function getMissionStatusLabel($rank, $mission_verified) {
    $required_mission = getRequiredMissionByRank((int) $rank);
    if ($required_mission === '') {
        return 'No requerida';
    }

    return ((int) $mission_verified === 1) ? 'Mision verificada' : 'Mision no anadida';
}

function generateTemporaryPassword($length = 12) {
    $lowercase = 'abcdefghjkmnpqrstuvwxyz';
    $uppercase = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    $numbers = '23456789';
    $symbols = '!@#$%*?';
    $all_chars = $lowercase . $uppercase . $numbers . $symbols;

    $length = max(8, (int) $length);
    $password_chars = [
        $lowercase[random_int(0, strlen($lowercase) - 1)],
        $uppercase[random_int(0, strlen($uppercase) - 1)],
        $numbers[random_int(0, strlen($numbers) - 1)],
        $symbols[random_int(0, strlen($symbols) - 1)]
    ];

    while (count($password_chars) < $length) {
        $password_chars[] = $all_chars[random_int(0, strlen($all_chars) - 1)];
    }

    for ($index = count($password_chars) - 1; $index > 0; $index--) {
        $swap_index = random_int(0, $index);
        $temp = $password_chars[$index];
        $password_chars[$index] = $password_chars[$swap_index];
        $password_chars[$swap_index] = $temp;
    }

    return implode('', $password_chars);
}

// Ensure required DB artifacts exist in environments without running migrations yet
$bootstrap_conn = getDBConnection();
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS total_time INT(11) DEFAULT 0");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS experiencia INT(11) DEFAULT 0");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS creditos INT(11) DEFAULT 0");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS horas_acumuladas INT(11) DEFAULT 0");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS horas_actuales INT(11) DEFAULT 0");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS encargado_admin_id INT(11) DEFAULT NULL");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS mission_verified TINYINT(1) DEFAULT 0");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS mission_verified_at DATETIME DEFAULT NULL");
$bootstrap_conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_suspended TINYINT(1) DEFAULT 0");
$bootstrap_conn->query("
    CREATE TABLE IF NOT EXISTS time_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        admin_id INT(11) NOT NULL,
        horas INT(11) NOT NULL DEFAULT 0,
        minutos INT(11) NOT NULL DEFAULT 0,
        total_minutos INT(11) NOT NULL DEFAULT 0,
        status ENUM('pendiente','completada','cancelada') NOT NULL DEFAULT 'pendiente',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        accepted_at DATETIME DEFAULT NULL,
        completed_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_time_requests_user_status (user_id, status),
        KEY idx_time_requests_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$bootstrap_conn->query("
    CREATE TABLE IF NOT EXISTS time_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        request_id INT(11) DEFAULT NULL,
        user_id INT(11) NOT NULL,
        admin_id INT(11) NOT NULL,
        total_minutos INT(11) NOT NULL DEFAULT 0,
        creditos_otorgados INT(11) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_time_logs_user (user_id),
        KEY idx_time_logs_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$bootstrap_conn->query("
    CREATE TABLE IF NOT EXISTS encargado_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        encargado_admin_id INT(11) NOT NULL,
        requested_by_id INT(11) NOT NULL,
        status ENUM('pendiente','aceptada','denegada','cancelada') NOT NULL DEFAULT 'pendiente',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        responded_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_encargado_requests_user_status (user_id, status),
        KEY idx_encargado_requests_admin_status (encargado_admin_id, status),
        KEY idx_encargado_requests_requested_by (requested_by_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$bootstrap_conn->query("
    CREATE TABLE IF NOT EXISTS time_activation_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        admin_id INT(11) NOT NULL,
        status ENUM('pendiente','aceptada','denegada','cancelada') NOT NULL DEFAULT 'pendiente',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        responded_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_time_activation_requests_user_status (user_id, status),
        KEY idx_time_activation_requests_admin_status (admin_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$bootstrap_conn->query("
    CREATE TABLE IF NOT EXISTS active_time_sessions (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        admin_id INT(11) NOT NULL,
        request_id INT(11) DEFAULT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_active_time_sessions_user_active (user_id, ended_at),
        KEY idx_active_time_sessions_admin_active (admin_id, ended_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$bootstrap_conn->close();

// Load events, photos, and games data
$events_file = 'events.json';
$photos_file = 'photos.json';
$games_file = 'games.json';

$events = file_exists($events_file) ? json_decode(file_get_contents($events_file), true) : [];
$photos = file_exists($photos_file) ? json_decode(file_get_contents($photos_file), true) : [];
$games = file_exists($games_file) ? json_decode(file_get_contents($games_file), true) : [];
$flash = isset($_GET['msg']) ? $_GET['msg'] : '';
$generated_password_flash = null;

if (isset($_SESSION['generated_password_flash']) && is_array($_SESSION['generated_password_flash'])) {
    $generated_password_flash = $_SESSION['generated_password_flash'];
    unset($_SESSION['generated_password_flash']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_news'])) {
        $news_file = 'news.json';
        $news = file_exists($news_file) ? json_decode(file_get_contents($news_file), true) : [];
        $new_news = [
            'id' => time(),
            'title' => $_POST['news_title'],
            'content' => $_POST['news_content'],
            'image' => $_POST['news_image'] ?: '',
            'date' => date('Y-m-d H:i:s'),
            'created_by' => $user['username']
        ];
        $news[] = $new_news;
        file_put_contents($news_file, json_encode($news, JSON_PRETTY_PRINT));
    }

    if (isset($_POST['add_event'])) {
        $new_event = [
            'id' => time(),
            'title' => $_POST['event_title'],
            'description' => $_POST['event_description'],
            'date' => $_POST['event_date'],
            'time' => $_POST['event_time'],
            'created_by' => $user['username']
        ];
        $events[] = $new_event;
        file_put_contents($events_file, json_encode($events, JSON_PRETTY_PRINT));
    }

    if (isset($_POST['add_photo'])) {
        $new_photo = [
            'id' => time(),
            'title' => $_POST['photo_title'],
            'url' => $_POST['photo_url'],
            'description' => $_POST['photo_description'],
            'uploaded_by' => $user['username']
        ];
        $photos[] = $new_photo;
        file_put_contents($photos_file, json_encode($photos, JSON_PRETTY_PRINT));
    }

    if (isset($_POST['add_game'])) {
        $new_game = [
            'id' => time(),
            'name' => $_POST['game_name'],
            'room_link' => $_POST['game_room_link'],
            'description' => $_POST['game_description'],
            'created_by' => $user['username']
        ];
        $games[] = $new_game;
        file_put_contents($games_file, json_encode($games, JSON_PRETTY_PRINT));
    }

    if (isset($_POST['create_time_request'])) {
        $target_user_id = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;
        $request_hours = isset($_POST['request_hours']) ? (int) $_POST['request_hours'] : 0;
        $request_minutes = isset($_POST['request_minutes']) ? (int) $_POST['request_minutes'] : 0;

        $request_hours = max(0, min(200, $request_hours));
        $request_minutes = max(0, min(59, $request_minutes));
        $total_minutes = ($request_hours * 60) + $request_minutes;

        if ($target_user_id > 0 && $total_minutes > 0) {
            $conn = getDBConnection();
            $target_stmt = $conn->prepare("SELECT encargado_admin_id FROM users WHERE id = ?");
            $target_stmt->bind_param("i", $target_user_id);
            $target_stmt->execute();
            $target = $target_stmt->get_result()->fetch_assoc();
            $target_stmt->close();

            if ($target && ($user_rank >= 7 || (int) $target['encargado_admin_id'] === (int) $user['id'])) {
                $request_stmt = $conn->prepare("
                    INSERT INTO time_requests (user_id, admin_id, horas, minutos, total_minutos, status)
                    VALUES (?, ?, ?, ?, ?, 'pendiente')
                ");
                $request_stmt->bind_param("iiiii", $target_user_id, $user['id'], $request_hours, $request_minutes, $total_minutes);
                $request_stmt->execute();
                $request_stmt->close();
            }
            $conn->close();
        }

        header("Location: admin.php?msg=solicitud_creada");
        exit;
    }

    if (isset($_POST['request_time_activation'])) {
        $target_user_id = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;
        if ($target_user_id <= 0) {
            header("Location: admin.php?msg=usuario_no_encontrado");
            exit;
        }

        $conn = getDBConnection();
        $target_stmt = $conn->prepare("SELECT id, encargado_admin_id FROM users WHERE id = ? LIMIT 1");
        $target_stmt->bind_param("i", $target_user_id);
        $target_stmt->execute();
        $target = $target_stmt->get_result()->fetch_assoc();
        $target_stmt->close();

        if (!$target) {
            $conn->close();
            header("Location: admin.php?msg=usuario_no_encontrado");
            exit;
        }

        $can_manage = ($user_rank >= 7) || ((int) $target['encargado_admin_id'] === (int) $user['id']);
        if (!$can_manage) {
            $conn->close();
            header("Location: admin.php?msg=usuario_no_encontrado");
            exit;
        }

        $active_stmt = $conn->prepare("SELECT id FROM active_time_sessions WHERE user_id = ? AND ended_at IS NULL LIMIT 1");
        $active_stmt->bind_param("i", $target_user_id);
        $active_stmt->execute();
        $active_exists = (bool) $active_stmt->get_result()->fetch_assoc();
        $active_stmt->close();

        if ($active_exists) {
            $conn->close();
            header("Location: admin.php?msg=time_ya_activo");
            exit;
        }

        $pending_stmt = $conn->prepare("SELECT id FROM time_activation_requests WHERE user_id = ? AND status = 'pendiente' LIMIT 1");
        $pending_stmt->bind_param("i", $target_user_id);
        $pending_stmt->execute();
        $pending_exists = (bool) $pending_stmt->get_result()->fetch_assoc();
        $pending_stmt->close();

        if ($pending_exists) {
            $conn->close();
            header("Location: admin.php?msg=solicitud_time_existente");
            exit;
        }

        $insert_stmt = $conn->prepare("
            INSERT INTO time_activation_requests (user_id, admin_id, status)
            VALUES (?, ?, 'pendiente')
        ");
        $insert_stmt->bind_param("ii", $target_user_id, $user['id']);
        $insert_stmt->execute();
        $insert_stmt->close();
        $conn->close();

        header("Location: admin.php?msg=solicitud_time_enviada");
        exit;
    }

    if (isset($_POST['confirm_hours'])) {
        $target_user_id = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;
        $hours_to_confirm = isset($_POST['hours_to_confirm']) ? (int) $_POST['hours_to_confirm'] : 0;
        $hours_to_confirm = max(1, min(200, $hours_to_confirm));

        $conn = getDBConnection();
        $target_stmt = $conn->prepare("SELECT rank, mission_verified, encargado_admin_id FROM users WHERE id = ?");
        $target_stmt->bind_param("i", $target_user_id);
        $target_stmt->execute();
        $target = $target_stmt->get_result()->fetch_assoc();
        $target_stmt->close();

        $can_confirm = false;
        if ($target && ($user_rank >= 7 || (int) $target['encargado_admin_id'] === (int) $user['id'])) {
            $required_mission = getRequiredMissionByRank((int) $target['rank']);
            if ($required_mission === '' || (int) $target['mission_verified'] === 1) {
                $can_confirm = true;
            }
        }

        if ($can_confirm) {
            $credits_to_add = $hours_to_confirm * 3;
            $total_minutes = $hours_to_confirm * 60;
            $experience_to_add = $total_minutes * 10;
            $update_stmt = $conn->prepare(
                "UPDATE users
                 SET horas_actuales = horas_actuales + ?,
                     horas_acumuladas = horas_acumuladas + ?,
                     creditos = creditos + ?,
                     experiencia = experiencia + ?,
                     total_time = total_time + ?
                 WHERE id = ?"
            );
            $update_stmt->bind_param("iiiiii", $hours_to_confirm, $hours_to_confirm, $credits_to_add, $experience_to_add, $total_minutes, $target_user_id);
            $update_stmt->execute();
            $update_stmt->close();

            $log_stmt = $conn->prepare("
                INSERT INTO time_logs (request_id, user_id, admin_id, total_minutos, creditos_otorgados)
                VALUES (NULL, ?, ?, ?, ?)
            ");
            $log_stmt->bind_param("iiii", $target_user_id, $user['id'], $total_minutes, $credits_to_add);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $conn->close();
            header("Location: admin.php?msg=mision_no_anadida");
            exit;
        }

        $conn->close();
        header("Location: admin.php?msg=horas_confirmadas");
        exit;
    }

    if (isset($_POST['update_user'])) {
        $user_id = isset($_POST['original_id']) ? (int) $_POST['original_id'] : 0;
        $new_rank = isset($_POST['new_rank']) ? (int) $_POST['new_rank'] : null;
        $new_encargado_admin_id = isset($_POST['encargado_admin_id']) ? (int) $_POST['encargado_admin_id'] : null;
        $encargado_request_created = false;

        // Only allow rank changes from 6 to 7, or lower ranks
        $conn = getDBConnection();
        $current_user_stmt = $conn->prepare("SELECT rank, encargado_admin_id FROM users WHERE id = ?");
        $current_user_stmt->bind_param("i", $user_id);
        $current_user_stmt->execute();
        $current_user_data = $current_user_stmt->get_result()->fetch_assoc();
        $current_user_stmt->close();
        $current_rank = (int) ($current_user_data['rank'] ?? 1);
        $current_encargado_admin_id = (int) ($current_user_data['encargado_admin_id'] ?? 0);

        // Allow rank changes only if current user has higher rank than target, or if changing from 6 to 7
        if ($new_rank !== null && ($user_rank > $current_rank || ($user_rank >= 7 && $current_rank == 6 && $new_rank == 7))) {
            $stmt = $conn->prepare("UPDATE users SET rank = ?, mission_verified = 0, mission_verified_at = NULL WHERE id = ?");
            $stmt->bind_param("ii", $new_rank, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        if ($user_rank >= 7 && $new_encargado_admin_id !== null) {
            if ($new_encargado_admin_id <= 0) {
                $set_encargado_stmt = $conn->prepare("UPDATE users SET encargado_admin_id = NULL WHERE id = ?");
                $set_encargado_stmt->bind_param("i", $user_id);
                $set_encargado_stmt->execute();
                $set_encargado_stmt->close();
            } else {
                $admin_check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND rank >= 5 LIMIT 1");
                $admin_check_stmt->bind_param("i", $new_encargado_admin_id);
                $admin_check_stmt->execute();
                $admin_check = $admin_check_stmt->get_result()->fetch_assoc();
                $admin_check_stmt->close();

                if ($admin_check && $current_encargado_admin_id !== $new_encargado_admin_id) {
                    $cancel_pending_stmt = $conn->prepare("UPDATE encargado_requests SET status = 'cancelada', responded_at = NOW() WHERE user_id = ? AND status = 'pendiente'");
                    $cancel_pending_stmt->bind_param("i", $user_id);
                    $cancel_pending_stmt->execute();
                    $cancel_pending_stmt->close();

                    $request_stmt = $conn->prepare("
                        INSERT INTO encargado_requests (user_id, encargado_admin_id, requested_by_id, status)
                        VALUES (?, ?, ?, 'pendiente')
                    ");
                    $request_stmt->bind_param("iii", $user_id, $new_encargado_admin_id, $user['id']);
                    $request_stmt->execute();
                    $request_stmt->close();
                    $encargado_request_created = true;
                }
            }
        }
        $conn->close();

        header("Location: admin.php?msg=" . ($encargado_request_created ? 'solicitud_encargado_enviada' : 'usuario_actualizado'));
        exit;
    }

    if (isset($_POST['regenerate_password'])) {
        $target_user_id = isset($_POST['original_id']) ? (int) $_POST['original_id'] : 0;
        if ($user_rank < 7 || $target_user_id <= 0) {
            header("Location: admin.php");
            exit;
        }

        $conn = getDBConnection();
        $target_stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
        $target_stmt->bind_param("i", $target_user_id);
        $target_stmt->execute();
        $target_user = $target_stmt->get_result()->fetch_assoc();
        $target_stmt->close();

        if (!$target_user) {
            $conn->close();
            header("Location: admin.php?msg=usuario_no_encontrado");
            exit;
        }

        $new_plain_password = generateTemporaryPassword(12);
        $new_hashed_password = password_hash($new_plain_password, PASSWORD_DEFAULT);

        $reset_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $reset_stmt->bind_param("si", $new_hashed_password, $target_user_id);
        $reset_stmt->execute();
        $reset_stmt->close();
        $conn->close();

        $_SESSION['generated_password_flash'] = [
            'username' => (string) $target_user['username'],
            'password' => $new_plain_password
        ];

        header("Location: admin.php?msg=password_regenerada");
        exit;
    }

    if (isset($_POST['toggle_user_status'])) {
        $target_user_id = isset($_POST['original_id']) ? (int) $_POST['original_id'] : 0;

        if ($user_rank < 7 || $target_user_id <= 0 || $target_user_id === (int) $_SESSION['user_id']) {
            header("Location: admin.php");
            exit;
        }

        $conn = getDBConnection();
        $target_stmt = $conn->prepare("SELECT id, rank, is_suspended FROM users WHERE id = ? LIMIT 1");
        $target_stmt->bind_param("i", $target_user_id);
        $target_stmt->execute();
        $target_user = $target_stmt->get_result()->fetch_assoc();
        $target_stmt->close();

        if (!$target_user || (int) $target_user['rank'] >= $user_rank) {
            $conn->close();
            header("Location: admin.php?msg=usuario_no_encontrado");
            exit;
        }

        $new_status = ((int) $target_user['is_suspended'] === 1) ? 0 : 1;
        $status_stmt = $conn->prepare("UPDATE users SET is_suspended = ? WHERE id = ?");
        $status_stmt->bind_param("ii", $new_status, $target_user_id);
        $status_stmt->execute();
        $status_stmt->close();
        $conn->close();

        header("Location: admin.php?msg=" . ($new_status === 1 ? 'usuario_suspendido' : 'usuario_activado'));
        exit;
    }

    // Redirect to avoid form resubmission
    header("Location: admin.php");
    exit;
}

$conn = getDBConnection();
$admin_users_query = $conn->query("SELECT id, username FROM users WHERE rank >= 5 ORDER BY rank DESC, username ASC");
$admin_users = $admin_users_query ? $admin_users_query->fetch_all(MYSQLI_ASSOC) : [];

if ($user_rank >= 7) {
    $stats_query = $conn->query("
        SELECT u.id, u.username, u.habbo_username, u.rank, u.verified, u.created_at, u.experiencia, u.creditos, u.horas_acumuladas, u.horas_actuales, u.total_time, u.mission_verified, u.encargado_admin_id,
               (SELECT COUNT(*) FROM time_requests tr WHERE tr.user_id = u.id AND tr.status = 'pendiente') AS pending_requests,
               (SELECT COUNT(*) FROM time_activation_requests tar WHERE tar.user_id = u.id AND tar.status = 'pendiente') AS pending_activation_requests,
               (SELECT ats.started_at FROM active_time_sessions ats WHERE ats.user_id = u.id AND ats.ended_at IS NULL ORDER BY ats.id DESC LIMIT 1) AS active_time_started_at,
               ea.username AS encargado_username
        FROM users u
        LEFT JOIN users ea ON ea.id = u.encargado_admin_id
        ORDER BY u.rank DESC, u.created_at DESC
    ");
} else {
    $stats_stmt = $conn->prepare("
        SELECT u.id, u.username, u.habbo_username, u.rank, u.verified, u.created_at, u.experiencia, u.creditos, u.horas_acumuladas, u.horas_actuales, u.total_time, u.mission_verified, u.encargado_admin_id,
               (SELECT COUNT(*) FROM time_requests tr WHERE tr.user_id = u.id AND tr.status = 'pendiente') AS pending_requests,
               (SELECT COUNT(*) FROM time_activation_requests tar WHERE tar.user_id = u.id AND tar.status = 'pendiente') AS pending_activation_requests,
               (SELECT ats.started_at FROM active_time_sessions ats WHERE ats.user_id = u.id AND ats.ended_at IS NULL ORDER BY ats.id DESC LIMIT 1) AS active_time_started_at,
               ea.username AS encargado_username
        FROM users u
        LEFT JOIN users ea ON ea.id = u.encargado_admin_id
        WHERE u.encargado_admin_id = ?
        ORDER BY u.created_at DESC
    ");
    $stats_stmt->bind_param("i", $user['id']);
    $stats_stmt->execute();
    $stats_query = $stats_stmt->get_result();
}
$tracked_users = $stats_query ? $stats_query->fetch_all(MYSQLI_ASSOC) : [];

if ($user_rank >= 7) {
    $recent_requests_query = $conn->query("
        SELECT tr.id, tr.user_id, tr.admin_id, tr.horas, tr.minutos, tr.total_minutos, tr.status, tr.created_at, tr.completed_at,
               u.username AS user_username, a.username AS admin_username
        FROM time_requests tr
        LEFT JOIN users u ON u.id = tr.user_id
        LEFT JOIN users a ON a.id = tr.admin_id
        ORDER BY tr.created_at DESC
        LIMIT 20
    ");
} else {
    $recent_requests_stmt = $conn->prepare("
        SELECT tr.id, tr.user_id, tr.admin_id, tr.horas, tr.minutos, tr.total_minutos, tr.status, tr.created_at, tr.completed_at,
               u.username AS user_username, a.username AS admin_username
        FROM time_requests tr
        LEFT JOIN users u ON u.id = tr.user_id
        LEFT JOIN users a ON a.id = tr.admin_id
        WHERE tr.admin_id = ?
        ORDER BY tr.created_at DESC
        LIMIT 20
    ");
    $recent_requests_stmt->bind_param("i", $user['id']);
    $recent_requests_stmt->execute();
    $recent_requests_query = $recent_requests_stmt->get_result();
}
$recent_requests = $recent_requests_query ? $recent_requests_query->fetch_all(MYSQLI_ASSOC) : [];

if (isset($stats_stmt) && $stats_stmt) {
    $stats_stmt->close();
}
if (isset($recent_requests_stmt) && $recent_requests_stmt) {
    $recent_requests_stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Club Cortefina</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Press Start 2P', monospace;
            background: linear-gradient(135deg, #1a0033 0%, #000000 50%, #0a0a2e 100%);
            color: #ffffff;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(138, 43, 226, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 40% 40%, rgba(255, 0, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
            animation: discoLights 8s ease-in-out infinite alternate;
        }

        @keyframes discoLights {
            0% { opacity: 0.3; }
            100% { opacity: 0.7; }
        }

        .top-nav {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px);
            max-width: 1500px;
            background: rgba(0, 0, 0, 0.65);
            border: 1px solid rgba(138, 43, 226, 0.65);
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            z-index: 6;
            margin: 0 auto;
        }

        .brand {
            color: #ffd700;
            font-size: 0.7rem;
            text-shadow: 0 0 10px #ffd700;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            border: 1px solid #8a2be2;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.55rem;
            background: rgba(138, 43, 226, 0.18);
            transition: all 0.2s ease;
        }

        .nav-links a:hover {
            background: #8a2be2;
            transform: translateY(-1px);
        }

        .container {
            max-width: 1500px;
            width: calc(100% - 40px);
            margin: 0 auto;
            padding: 96px 0 24px 0;
        }

        .header {
            text-align: center;
            margin-bottom: 26px;
        }

        .title {
            font-size: 2.5rem;
            color: #ffd700;
            text-shadow: 0 0 20px #ffd700;
            margin-bottom: 14px;
        }

        .subtitle {
            font-size: 1rem;
            color: #8a2be2;
            margin-bottom: 20px;
        }

        .quick-jump {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0 auto 14px auto;
        }

        .quick-jump-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 280px;
            max-width: 100%;
        }

        .quick-jump-label {
            color: #ffd700;
            font-size: 0.58rem;
            text-align: left;
        }

        .quick-jump-select {
            min-width: 0;
            width: 100%;
            max-width: 100%;
            padding: 10px 12px;
            border: 2px solid #8a2be2;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.55);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.54rem;
            text-align: center;
        }

        .quick-jump-select:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 12px rgba(255, 215, 0, 0.35);
        }

        .section-toggle-cell .quick-jump {
            margin: 4px auto 0 auto;
        }

        .section-toggle-cell .quick-jump-label {
            text-align: center;
        }

        .back-link {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            color: #ffffff;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
        }

        .back-link:hover {
            background: linear-gradient(45deg, #4b0082, #8a2be2);
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(138, 43, 226, 0.6);
        }

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }

        .admin-section {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            padding: 22px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .admin-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255, 215, 0, 0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .admin-section:hover::before {
            transform: translateX(100%);
        }

        .section-title {
            font-size: 1.5rem;
            color: #ffd700;
            text-shadow: 0 0 10px #ffd700;
            margin-bottom: 16px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            color: #8a2be2;
            margin-bottom: 8px;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #8a2be2;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.6rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .form-input-small {
            width: 100%;
            padding: 8px;
            border: 2px solid #8a2be2;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.55rem;
            min-width: 210px;
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            border: none;
            color: #000;
            font-size: 0.8rem;
            font-family: 'Press Start 2P', monospace;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .submit-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.8);
        }

        .items-list {
            margin-top: 12px;
        }

        .item {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid rgba(138, 43, 226, 0.3);
        }

        .item-title {
            font-size: 0.9rem;
            color: #ffd700;
            margin-bottom: 8px;
        }

        .item-meta {
            font-size: 0.6rem;
            color: #8a2be2;
        }

        .flash-message {
            margin: 0 auto 16px auto;
            max-width: 920px;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #8a2be2;
            background: rgba(0, 0, 0, 0.45);
            color: #ffd700;
            font-size: 0.65rem;
            text-align: center;
        }

        .flash-message.password-flash {
            border-color: #00d4ff;
            background: rgba(0, 40, 55, 0.55);
            color: #ffffff;
        }

        .generated-password-box {
            display: inline-block;
            margin: 10px auto 8px auto;
            padding: 10px 14px;
            border: 1px dashed #00d4ff;
            border-radius: 8px;
            color: #7dfcff;
            background: rgba(0, 0, 0, 0.45);
            letter-spacing: 1px;
            word-break: break-all;
        }

        .generated-password-note {
            color: #ffd700;
            font-size: 0.55rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 12px;
            width: 100%;
        }

        .stat-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(138, 43, 226, 0.35);
            border-radius: 8px;
            padding: 14px 12px;
            font-size: 0.62rem;
            color: #ffd700;
            min-height: 64px;
            display: flex;
            align-items: center;
        }

        .hours-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
            width: 100%;
        }

        .hours-input {
            width: 160px;
            padding: 8px;
            border: 2px solid #8a2be2;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.6rem;
        }

        .search-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 4px 0 10px 0;
        }

        .search-input-panel {
            flex: 1;
            min-width: 250px;
            padding: 10px 12px;
            border: 2px solid #8a2be2;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.55);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.58rem;
        }

        .search-input-panel:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 12px rgba(255, 215, 0, 0.35);
        }

        .search-empty {
            color: #ff6b6b;
            font-size: 0.62rem;
            margin-bottom: 10px;
            display: none;
        }

        /* Center all content in admin cells */
        .admin-section,
        .admin-section .item,
        .admin-section .item-title,
        .admin-section .item-meta,
        .admin-section .user-item,
        .admin-section .user-field,
        .admin-section .field-content,
        .admin-section .user-actions,
        .admin-section .stat-box,
        .admin-section .hours-form,
        .admin-section .search-toolbar {
            text-align: center;
            justify-content: center;
        }

        .admin-section .user-info,
        .admin-section .user-edit-form {
            justify-content: center;
        }

        .admin-section .stats-grid {
            justify-items: center;
            text-align: center;
        }

        .admin-section .form-input,
        .admin-section .form-textarea,
        .admin-section .form-input-small,
        .admin-section .hours-input,
        .admin-section .search-input-panel,
        .admin-section select {
            text-align: center;
        }

        /* Pixel decorations */
        .pixel-decoration {
            position: absolute;
            font-size: 1rem;
            color: #8a2be2;
            opacity: 0.3;
        }

        .pixel-decoration.top-left {
            top: 20px;
            left: 20px;
        }

        .pixel-decoration.top-right {
            top: 20px;
            right: 20px;
        }

        .pixel-decoration.bottom-left {
            bottom: 20px;
            left: 20px;
        }

        .pixel-decoration.bottom-right {
            bottom: 20px;
            right: 20px;
        }

        /* User Management Styles */
        .user-management {
            grid-column: span 2 !important;
        }


        .user-list {
            max-height: 600px;
            overflow-y: auto;
            margin-top: 10px;
        }

        .user-item {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 10px;
            border: 1px solid rgba(138, 43, 226, 0.4);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
        }

        .id-field {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 6px;
            padding: 10px;
        }

        .id-field label {
            color: #8a2be2;
        }

        .id-field .form-input {
            color: #ffffff;
        }

        .field-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            width: 100%;
        }

        .user-edit-form {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            flex-wrap: wrap;
            gap: 15px;
        }

        .user-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            flex: 1;
            width: 100%;
        }

        .user-field {
            display: flex;
            flex-direction: column;
            min-width: 190px;
            flex: 1 1 230px;
        }

        .avatar-user-field {
            flex: 0 0 auto;
            min-width: 130px;
            max-width: 130px;
            align-items: center;
        }

        .avatar-user-field .field-content {
            justify-content: center;
        }

        .habbo-avatar-cell {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            border: 2px solid #8a2be2;
            box-shadow: 0 0 12px rgba(138, 43, 226, 0.45);
            object-fit: cover;
            display: block;
        }

        .user-field label {
            font-size: 0.6rem;
            color: #8a2be2;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .user-actions {
            display: flex;
            gap: 10px;
        }

        .update-btn {
            padding: 8px 15px;
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            border: none;
            color: #000;
            font-size: 0.6rem;
            font-family: 'Press Start 2P', monospace;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        }

        .update-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .password-btn {
            padding: 8px 15px;
            background: linear-gradient(45deg, #00d4ff, #0077b6);
            border: none;
            color: #001426;
            font-size: 0.6rem;
            font-family: 'Press Start 2P', monospace;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.35);
        }

        .password-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 16px rgba(0, 212, 255, 0.55);
        }

        /* Limpia acciones duplicadas en Gestion de Usuarios */
        .user-management .user-info .field-content .user-actions {
            display: none !important;
        }

        .user-management .user-info .user-actions .update-btn,
        .user-management .user-info .user-actions .delete-btn,
        .user-management .user-info .user-actions .password-btn {
            display: none !important;
        }

        .user-management .password-action-field {
            flex: 1 1 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .user-management .password-action-field .password-btn {
            width: 100%;
        }

        .user-management .user-management-actions {
            width: 100%;
            justify-content: center;
            margin-top: 8px;
        }

        .delete-btn {
            padding: 8px 15px;
            background: linear-gradient(45deg, #ff6b6b, #8a2be2);
            border: none;
            color: #fff;
            font-size: 0.6rem;
            font-family: 'Press Start 2P', monospace;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(255, 107, 107, 0.3);
        }

        .delete-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(255, 107, 107, 0.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-nav {
                padding: 10px 10px;
            }

            .admin-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 122px 0 18px 0;
            }

            .title {
                font-size: 2rem;
            }

            .quick-jump {
                width: 100%;
                gap: 8px;
            }

            .quick-jump-group {
                width: 100%;
                min-width: 0;
            }

            .quick-jump-select {
                min-width: 0;
                width: 100%;
            }

            .user-management {
                grid-column: span 1 !important;
            }

            .user-info {
                flex-direction: column;
                width: 100%;
            }

            .user-actions {
                width: 100%;
                justify-content: center;
            }

            .form-input-small {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="pixel-decoration top-left">■</div>
    <div class="pixel-decoration top-right">■</div>
    <div class="pixel-decoration bottom-left">■</div>
    <div class="pixel-decoration bottom-right">■</div>

    <div class="top-nav">
        <div class="brand">Club Cortefina | Panel Admin</div>
        <div class="nav-links">
            <a href="index.php">Inicio</a>
            <a href="perfil.php">Perfil</a>
            <a href="time.php">Time</a>
            <a href="admin.php">Admin</a>
            <a href="logout.php">Cerrar</a>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <h1 class="title">Panel Administrativo</h1>
            <p class="subtitle">Bienvenido, <?php echo htmlspecialchars($user['username']); ?> | Rango: <?php echo $user_rank_name; ?></p>
        </div>

        <?php if ($flash === 'horas_confirmadas'): ?>
            <div class="flash-message">Horas confirmadas. Se sumaron 3 creditos por cada hora.</div>
        <?php elseif ($flash === 'solicitud_creada'): ?>
            <div class="flash-message">Solicitud de time enviada al usuario.</div>
        <?php elseif ($flash === 'mision_no_anadida'): ?>
            <div class="flash-message">No se puede sumar tiempo: el usuario tiene mision no anadida.</div>
        <?php elseif ($flash === 'usuario_actualizado'): ?>
            <div class="flash-message">Usuario actualizado correctamente.</div>
        <?php elseif ($flash === 'solicitud_encargado_enviada'): ?>
            <div class="flash-message">Solicitud de encargado enviada al usuario. Debe aceptar o denegar en su perfil.</div>
        <?php elseif ($flash === 'solicitud_time_enviada'): ?>
            <div class="flash-message">Solicitud de activacion de time enviada al usuario.</div>
        <?php elseif ($flash === 'solicitud_time_existente'): ?>
            <div class="flash-message">Ya existe una solicitud de activacion pendiente para este usuario.</div>
        <?php elseif ($flash === 'time_ya_activo'): ?>
            <div class="flash-message">El time ya esta activo para este usuario.</div>
        <?php elseif ($flash === 'password_regenerada'): ?>
            <div class="flash-message password-flash">
                <?php if ($generated_password_flash): ?>
                    <div>Nueva contrasena para <strong><?php echo htmlspecialchars($generated_password_flash['username']); ?></strong>:</div>
                    <div class="generated-password-box"><?php echo htmlspecialchars($generated_password_flash['password']); ?></div>
                    <div class="generated-password-note">Copiala y compartela con el usuario.</div>
                <?php else: ?>
                    Contrasena regenerada correctamente.
                <?php endif; ?>
            </div>
        <?php elseif ($flash === 'usuario_suspendido'): ?>
            <div class="flash-message">Usuario suspendido correctamente.</div>
        <?php elseif ($flash === 'usuario_activado'): ?>
            <div class="flash-message">Usuario activado correctamente.</div>
        <?php elseif ($flash === 'usuario_eliminado'): ?>
            <div class="flash-message">Usuario eliminado correctamente.</div>
        <?php elseif ($flash === 'usuario_no_encontrado'): ?>
            <div class="flash-message">No se encontro el usuario solicitado.</div>
        <?php endif; ?>

        <div class="admin-grid">
            <!-- News Management -->
            <div class="admin-section">
                <h3 class="section-title">Gestión de Noticias</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Título de la Noticia:</label>
                        <input type="text" name="news_title" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contenido:</label>
                        <textarea name="news_content" class="form-textarea" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Imagen (URL):</label>
                        <input type="url" name="news_image" class="form-input">
                    </div>
                    <button type="submit" name="add_news" class="submit-btn">Publicar Noticia</button>
                </form>

                <div class="items-list">
                    <h4 style="color: #ffd700; margin-bottom: 15px;">Noticias Recientes:</h4>
                    <?php
                    $news_file = 'news.json';
                    $news = file_exists($news_file) ? json_decode(file_get_contents($news_file), true) : [];
                    foreach (array_slice(array_reverse($news), 0, 3) as $item): ?>
                        <div class="item">
                            <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="item-meta">Por: <?php echo htmlspecialchars($item['created_by']); ?> - <?php echo htmlspecialchars($item['date']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Events Management -->
            <div class="admin-section">
                <h3 class="section-title">Gestión de Eventos</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Título del Evento:</label>
                        <input type="text" name="event_title" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción:</label>
                        <textarea name="event_description" class="form-textarea" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha:</label>
                        <input type="date" name="event_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hora:</label>
                        <input type="time" name="event_time" class="form-input" required>
                    </div>
                    <button type="submit" name="add_event" class="submit-btn">Añadir Evento</button>
                </form>

                <div class="items-list">
                    <h4 style="color: #ffd700; margin-bottom: 15px;">Eventos Recientes:</h4>
                    <?php foreach (array_slice(array_reverse($events), 0, 3) as $event): ?>
                        <div class="item">
                            <div class="item-title"><?php echo htmlspecialchars($event['title']); ?></div>
                            <div class="item-meta"><?php echo htmlspecialchars($event['date'] . ' ' . $event['time']); ?> - Por: <?php echo htmlspecialchars($event['created_by']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Photos Management -->
            <div class="admin-section">
                <h3 class="section-title">Gestión de Fotos</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Título de la Foto:</label>
                        <input type="text" name="photo_title" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">URL de la Imagen:</label>
                        <input type="url" name="photo_url" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción:</label>
                        <textarea name="photo_description" class="form-textarea"></textarea>
                    </div>
                    <button type="submit" name="add_photo" class="submit-btn">Añadir Foto</button>
                </form>

                <div class="items-list">
                    <h4 style="color: #ffd700; margin-bottom: 15px;">Fotos Recientes:</h4>
                    <?php foreach (array_slice(array_reverse($photos), 0, 3) as $photo): ?>
                        <div class="item">
                            <div class="item-title"><?php echo htmlspecialchars($photo['title']); ?></div>
                            <div class="item-meta">Por: <?php echo htmlspecialchars($photo['uploaded_by']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Games Management -->
            <div class="admin-section">
                <h3 class="section-title">Gestión de Minijuegos</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nombre del Juego:</label>
                        <input type="text" name="game_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link de Sala (Habbo.es):</label>
                        <input type="url" name="game_room_link" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción:</label>
                        <textarea name="game_description" class="form-textarea" required></textarea>
                    </div>
                    <button type="submit" name="add_game" class="submit-btn">Añadir Juego</button>
                </form>

                <div class="items-list">
                    <h4 style="color: #ffd700; margin-bottom: 15px;">Juegos Recientes:</h4>
                    <?php foreach (array_slice(array_reverse($games), 0, 3) as $game): ?>
                        <div class="item">
                            <div class="item-title"><?php echo htmlspecialchars($game['name']); ?></div>
                            <div class="item-meta">
                                <a href="<?php echo htmlspecialchars($game['room_link']); ?>" target="_blank" style="color: #8a2be2; text-decoration: none;">Ir al Juego</a> - Por: <?php echo htmlspecialchars($game['created_by']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-section section-toggle-cell" style="grid-column: span 2;">
                <h3 class="section-title">Mostrar u Ocultar Celdas</h3>
                <div class="quick-jump">
                    <div class="quick-jump-group">
                        <label for="toggle-control-horas" class="quick-jump-label">Control de Horas y Creditos</label>
                        <select id="toggle-control-horas" class="quick-jump-select" data-target="control-horas">
                            <option value="show">Mostrar celda</option>
                            <option value="hide" selected>Ocultar celda</option>
                        </select>
                    </div>
                    <?php if ($user_rank >= 6): ?>
                    <div class="quick-jump-group">
                        <label for="toggle-gestion-usuarios" class="quick-jump-label">Gestion de Usuarios</label>
                        <select id="toggle-gestion-usuarios" class="quick-jump-select" data-target="gestion-usuarios">
                            <option value="show">Mostrar celda</option>
                            <option value="hide" selected>Ocultar celda</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="control-horas" class="admin-section user-management" style="grid-column: span 2; display: none;">
                <h3 class="section-title">Control de Horas, Creditos y Experiencia</h3>
                <p style="text-align: center; color: #8a2be2; font-size: 0.7rem; margin-bottom: 16px;">Consulta el estado general de horas, creditos y experiencia por usuario.</p>

                <div class="search-toolbar">
                    <input type="text" id="search-hours-input" class="search-input-panel" placeholder="Buscar usuario o Habbo en gestion de horas">
                    <button type="button" id="search-hours-btn" class="update-btn">Buscar</button>
                    <button type="button" id="clear-hours-btn" class="delete-btn">Limpiar</button>
                </div>
                <p id="search-hours-empty" class="search-empty">No se encontraron usuarios en gestion de horas.</p>

                <div class="user-list">
                    <?php foreach ($tracked_users as $tracked): ?>
                    <div class="user-item tracked-user-item" data-search="<?php echo htmlspecialchars(strtolower($tracked['username'] . ' ' . $tracked['habbo_username'])); ?>" data-username="<?php echo htmlspecialchars(strtolower($tracked['username'])); ?>" data-habbo="<?php echo htmlspecialchars(strtolower($tracked['habbo_username'])); ?>">
                        <div class="user-edit-form">
                            <div class="user-info">
                                <div class="user-field avatar-user-field">
                                    <label>Avatar:</label>
                                    <div class="field-content">
                                        <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($tracked['habbo_username']); ?>" alt="Avatar Habbo" class="habbo-avatar-cell" loading="lazy">
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Usuario:</label>
                                    <div class="field-content">
                                        <input type="text" value="<?php echo htmlspecialchars($tracked['username']); ?>" class="form-input-small" readonly>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Habbo:</label>
                                    <div class="field-content">
                                        <input type="text" value="<?php echo htmlspecialchars($tracked['habbo_username']); ?>" class="form-input-small" readonly>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Encargado:</label>
                                    <div class="field-content">
                                        <input type="text" value="<?php echo htmlspecialchars($tracked['encargado_username'] ?: 'Sin asignar'); ?>" class="form-input-small" readonly>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Estado de mision:</label>
                                    <?php $mission_state = getMissionStatusLabel((int) $tracked['rank'], (int) $tracked['mission_verified']); ?>
                                    <span style="color: <?php echo $mission_state === 'Mision no anadida' ? '#ff6b6b' : '#00ff99'; ?>;">
                                        <?php echo htmlspecialchars($mission_state); ?>
                                    </span>
                                </div>
                            </div>

                            <?php
                            $tracked_active_started_ts = !empty($tracked['active_time_started_at']) ? strtotime($tracked['active_time_started_at']) : 0;
                            $tracked_elapsed_seconds = $tracked_active_started_ts > 0 ? max(0, time() - $tracked_active_started_ts) : 0;
                            $tracked_live_total_minutes = (int) $tracked['total_time'] + intdiv($tracked_elapsed_seconds, 60);
                            $can_request_activation = ($user_rank >= 7) || ((int) $tracked['encargado_admin_id'] === (int) $user['id']);
                            $pending_activation_requests = (int) ($tracked['pending_activation_requests'] ?? 0);
                            $is_time_active = $tracked_active_started_ts > 0;
                            ?>
                            <div class="stats-grid">
                                <div class="stat-box">Experiencia: <?php echo number_format((int) $tracked['experiencia']); ?></div>
                                <div class="stat-box">Creditos: <?php echo number_format((int) $tracked['creditos']); ?></div>
                                <div class="stat-box">Horas Acumuladas: <?php echo number_format((int) $tracked['horas_acumuladas']); ?></div>
                                <div class="stat-box">Horas Actuales: <?php echo number_format((int) $tracked['horas_actuales']); ?></div>
                                <div
                                    class="stat-box tracked-total-time"
                                    data-base-minutes="<?php echo (int) $tracked['total_time']; ?>"
                                    data-active-start="<?php echo $tracked_active_started_ts > 0 ? (int) $tracked_active_started_ts : ''; ?>"
                                >Tiempo Total: <?php echo number_format($tracked_live_total_minutes); ?> min</div>
                            </div>

                            <?php if ($can_request_activation): ?>
                                <form method="POST" class="hours-form" style="margin-top: 10px; display: flex; justify-content: center;">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $tracked['id']; ?>">
                                    <button
                                        type="submit"
                                        name="request_time_activation"
                                        class="update-btn"
                                        <?php echo ($is_time_active || $pending_activation_requests > 0) ? 'disabled' : ''; ?>
                                    >
                                        <?php
                                        if ($is_time_active) {
                                            echo 'Time activo';
                                        } elseif ($pending_activation_requests > 0) {
                                            echo 'Solicitud enviada';
                                        } else {
                                            echo 'Activar Time';
                                        }
                                        ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="items-list">
                    <h4 style="color: #ffd700; margin-bottom: 15px;">Solicitudes recientes</h4>
                    <?php if (empty($recent_requests)): ?>
                        <div class="item"><div class="item-title">Sin solicitudes por ahora</div></div>
                    <?php else: ?>
                        <?php foreach ($recent_requests as $request): ?>
                            <div class="item">
                                <div class="item-title">
                                    Usuario: <?php echo htmlspecialchars($request['user_username'] ?: 'N/A'); ?> |
                                    Time: <?php echo (int) $request['horas']; ?>h <?php echo (int) $request['minutos']; ?>m
                                </div>
                                <div class="item-meta">
                                    Estado: <?php echo htmlspecialchars($request['status']); ?> |
                                    Admin: <?php echo htmlspecialchars($request['admin_username'] ?: 'N/A'); ?> |
                                    Fecha: <?php echo htmlspecialchars($request['created_at']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Management (Admin and Founder) -->
            <?php if ($user_rank >= 6): ?>
            <div id="gestion-usuarios" class="admin-section user-management" style="grid-column: span 2; display: none;">
                <h3 class="section-title">Gestion de Usuarios</h3>
                <p style="text-align: center; color: #8a2be2; font-size: 0.7rem; margin-bottom: 18px;">Funciones de gestion (algunas opciones solo para fundador).</p>

                <div class="search-toolbar">
                    <input type="text" id="search-users-input" class="search-input-panel" placeholder="Buscar usuario o Habbo en gestion de usuarios">
                    <button type="button" id="search-users-btn" class="update-btn">Buscar</button>
                    <button type="button" id="clear-users-btn" class="delete-btn">Limpiar</button>
                </div>
                <p id="search-users-empty" class="search-empty">No se encontraron usuarios en gestion de usuarios.</p>

                <!-- User List -->
                <div class="user-list">
                    <h4 style="color: #ffd700; margin-bottom: 14px;">Lista de usuarios</h4>
                    <?php
                    $conn = getDBConnection();
                    $users_query = $conn->query("SELECT id, username, habbo_username, rank, verified, created_at, experiencia, creditos, horas_acumuladas, horas_actuales, mission_verified, encargado_admin_id, is_suspended FROM users ORDER BY rank DESC, created_at DESC");
                    $all_users = $users_query->fetch_all(MYSQLI_ASSOC);
                    $conn->close();
                    ?>

                    <?php foreach ($all_users as $usr): ?>
                    <div class="user-item managed-user-item" data-search="<?php echo htmlspecialchars(strtolower($usr['username'] . ' ' . $usr['habbo_username'])); ?>" data-username="<?php echo htmlspecialchars(strtolower($usr['username'])); ?>" data-habbo="<?php echo htmlspecialchars(strtolower($usr['habbo_username'])); ?>">
                        <form method="POST" class="user-edit-form">
                            <div class="user-info">
                                <div class="user-field avatar-user-field">
                                    <label>Avatar:</label>
                                    <div class="field-content">
                                        <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($usr['habbo_username']); ?>" alt="Avatar Habbo" class="habbo-avatar-cell" loading="lazy">
                                    </div>
                                </div>
                                <div class="user-field id-field">
                                    <label>ID:</label>
                                    <div class="id-content">
                                        <input type="number" name="user_id" value="<?php echo $usr['id']; ?>" class="form-input-small" readonly>

                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Usuario:</label>
                                    <div class="field-content">
                                        <input type="text" name="username" value="<?php echo htmlspecialchars($usr['username']); ?>" class="form-input-small" readonly>
                                        <div class="user-actions">
                                            <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                            <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                            <?php if ($user_rank >= 7): ?>
                                            <button type="submit" name="regenerate_password" class="password-btn" onclick="return confirm('Se generara una nueva contrasena y la anterior dejara de funcionar. Continuar?')">Regenerar Clave</button>
                                            <?php endif; ?>
                                            <?php if ($user_rank >= 7 && $usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="toggle_user_status" class="delete-btn" onclick="return confirm('<?php echo ((int) $usr['is_suspended'] === 1) ? 'Deseas activar este usuario?' : 'Deseas suspender este usuario?'; ?>')"><?php echo ((int) $usr['is_suspended'] === 1) ? 'Activar' : 'Suspender'; ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Habbo:</label>
                                    <div class="field-content">
                                        <input type="text" name="habbo_username" value="<?php echo htmlspecialchars($usr['habbo_username']); ?>" class="form-input-small" readonly>
                                        <div class="user-actions">
                                            <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                            <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                            <?php if ($user_rank >= 7 && $usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="toggle_user_status" class="delete-btn" onclick="return confirm('<?php echo ((int) $usr['is_suspended'] === 1) ? 'Deseas activar este usuario?' : 'Deseas suspender este usuario?'; ?>')"><?php echo ((int) $usr['is_suspended'] === 1) ? 'Activar' : 'Suspender'; ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Rango:</label>
                                    <div class="field-content">
                                        <select name="new_rank" class="form-input-small">
                                            <option value="1" <?php echo $usr['rank'] == 1 ? 'selected' : ''; ?>>Usuario</option>
                                            <option value="5" <?php echo $usr['rank'] == 5 ? 'selected' : ''; ?>>Moderador</option>
                                            <?php if ($user_rank >= 7): ?>
                                            <option value="6" <?php echo $usr['rank'] == 6 ? 'selected' : ''; ?>>Administrador</option>
                                            <option value="7" <?php echo $usr['rank'] == 7 ? 'selected' : ''; ?>>Dueno</option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="user-actions">
                                            <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                            <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                            <?php if ($user_rank >= 7 && $usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="toggle_user_status" class="delete-btn" onclick="return confirm('<?php echo ((int) $usr['is_suspended'] === 1) ? 'Deseas activar este usuario?' : 'Deseas suspender este usuario?'; ?>')"><?php echo ((int) $usr['is_suspended'] === 1) ? 'Activar' : 'Suspender'; ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Horas Actuales:</label>
                                    <div class="field-content">
                                        <input type="number" value="<?php echo (int) $usr['horas_actuales']; ?>" class="form-input-small" readonly>
                                        <div class="user-actions">
                                            <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                            <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                            <?php if ($user_rank >= 7 && $usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="toggle_user_status" class="delete-btn" onclick="return confirm('<?php echo ((int) $usr['is_suspended'] === 1) ? 'Deseas activar este usuario?' : 'Deseas suspender este usuario?'; ?>')"><?php echo ((int) $usr['is_suspended'] === 1) ? 'Activar' : 'Suspender'; ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Creditos:</label>
                                    <div class="field-content">
                                        <input type="number" value="<?php echo (int) $usr['creditos']; ?>" class="form-input-small" readonly>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Experiencia:</label>
                                    <div class="field-content">
                                        <input type="number" value="<?php echo (int) $usr['experiencia']; ?>" class="form-input-small" readonly>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Encargado Admin:</label>
                                    <div class="field-content">
                                        <select name="encargado_admin_id" class="form-input-small">
                                            <option value="0">Sin asignar</option>
                                            <?php foreach ($admin_users as $admin_user): ?>
                                                <option value="<?php echo (int) $admin_user['id']; ?>" <?php echo ((int) $usr['encargado_admin_id'] === (int) $admin_user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($admin_user['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <?php if ($user_rank >= 7): ?>
                                <div class="user-field password-action-field">
                                    <label>Regenerar Clave:</label>
                                    <div class="field-content">
                                        <button type="submit" name="regenerate_password" class="password-btn" onclick="return confirm('Se generara una nueva contrasena y la anterior dejara de funcionar. Continuar?')">Regenerar Clave</button>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="user-field">
                                    <label>Verificado:</label>
                                    <span style="color: <?php echo $usr['verified'] ? '#00ff00' : '#ff6b6b'; ?>;">
                                        <?php echo $usr['verified'] ? 'Sí' : 'No'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="user-actions user-management-actions">
                                <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                <?php if ($user_rank >= 7 && $usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                <button type="submit" name="toggle_user_status" class="delete-btn" onclick="return confirm('<?php echo ((int) $usr['is_suspended'] === 1) ? 'Deseas activar este usuario?' : 'Deseas suspender este usuario?'; ?>')"><?php echo ((int) $usr['is_suspended'] === 1) ? 'Activar' : 'Suspender'; ?></button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        (function() {
            function applySectionVisibility(targetId, mode) {
                const section = document.getElementById(targetId);
                if (!section) {
                    return;
                }

                section.style.display = mode === 'hide' ? 'none' : '';
            }

            document.querySelectorAll('.quick-jump-select[data-target]').forEach((select) => {
                const targetId = select.getAttribute('data-target');
                applySectionVisibility(targetId, select.value);

                select.addEventListener('change', function() {
                    applySectionVisibility(targetId, this.value);
                });
            });

            document.querySelectorAll('.user-management .user-info .user-actions button').forEach((button) => {
                button.type = 'button';
                button.disabled = true;
            });

            function normalizeText(value) {
                return (value || '').toString().toLowerCase().trim();
            }

            const serverNow = <?php echo time(); ?>;
            const nowOffset = serverNow - Math.floor(Date.now() / 1000);

            function nowSeconds() {
                return Math.floor(Date.now() / 1000) + nowOffset;
            }

            function refreshTrackedTotals() {
                document.querySelectorAll('.tracked-total-time').forEach((node) => {
                    const baseMinutes = parseInt(node.getAttribute('data-base-minutes') || '0', 10);
                    const activeStart = parseInt(node.getAttribute('data-active-start') || '0', 10);
                    const elapsedSeconds = activeStart > 0 ? Math.max(0, nowSeconds() - activeStart) : 0;
                    const liveMinutes = baseMinutes + Math.floor(elapsedSeconds / 60);
                    node.textContent = 'Tiempo Total: ' + liveMinutes.toLocaleString('es-ES') + ' min';
                });
            }

            function initUserSearch(inputId, buttonId, clearId, itemSelector, emptyId) {
                const input = document.getElementById(inputId);
                const button = document.getElementById(buttonId);
                const clear = document.getElementById(clearId);
                const empty = document.getElementById(emptyId);

                if (!input || !button || !clear) {
                    return;
                }

                function applyFilter() {
                    const query = normalizeText(input.value);
                    const items = document.querySelectorAll(itemSelector);
                    let visible = 0;

                    if (query === '') {
                        items.forEach((item) => {
                            item.style.display = '';
                            visible++;
                        });
                    } else {
                        let chosen = null;

                        // 1) exact match by username or habbo
                        items.forEach((item) => {
                            if (chosen) return;
                            const username = normalizeText(item.getAttribute('data-username'));
                            const habbo = normalizeText(item.getAttribute('data-habbo'));
                            if (username === query || habbo === query) {
                                chosen = item;
                            }
                        });

                        // 2) fallback: first partial match
                        if (!chosen) {
                            items.forEach((item) => {
                                if (chosen) return;
                                const haystack = normalizeText(item.getAttribute('data-search') || item.textContent);
                                if (haystack.includes(query)) {
                                    chosen = item;
                                }
                            });
                        }

                        items.forEach((item) => {
                            const show = chosen === item;
                            item.style.display = show ? '' : 'none';
                            if (show) {
                                visible++;
                            }
                        });
                    }

                    if (empty) {
                        empty.style.display = visible === 0 ? 'block' : 'none';
                    }
                }

                button.addEventListener('click', applyFilter);
                clear.addEventListener('click', function() {
                    input.value = '';
                    applyFilter();
                });
                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyFilter();
                    }
                });

                // Run once to initialize empty-state visibility
                applyFilter();
            }

            document.addEventListener('DOMContentLoaded', function() {
                initUserSearch('search-hours-input', 'search-hours-btn', 'clear-hours-btn', '.tracked-user-item', 'search-hours-empty');
                initUserSearch('search-users-input', 'search-users-btn', 'clear-users-btn', '.managed-user-item', 'search-users-empty');
                refreshTrackedTotals();
                setInterval(refreshTrackedTotals, 1000);
            });
        })();
    </script>
</body>
</html>
