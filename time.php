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

// Check admin rank (5=Gerente, 6=Heredero, 7=Founder)
$user_rank = isset($user['rank']) ? $user['rank'] : 1;

if ($user_rank < 5) {
    echo "Acceso denegado. No tienes permisos de administrador.";
    exit;
}

// Get rank name
$rank_names = [
    1 => 'Bartender',
    2 => 'Diva',
    3 => 'Seguridad',
    4 => 'RH',
    5 => 'Gerente',
    6 => 'Heredero',
    7 => 'Founder'
];
$user_rank_name = isset($rank_names[$user_rank]) ? $rank_names[$user_rank] : 'Usuario';

// Load users with ranks 5, 6, or 7
$conn = getDBConnection();
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS encargado_admin_id INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS mission_verified TINYINT(1) DEFAULT 0");
$conn->query("
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
$conn->query("
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

$users_query = $conn->prepare("SELECT id, username, habbo_username, rank, verified, created_at, avatar FROM users WHERE rank IN (5, 6, 7) ORDER BY rank DESC, created_at DESC");
$users_query->execute();
$all_users = $users_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Load all users for the time system
$all_users_query = $conn->prepare("SELECT id, username, habbo_username, verified, rank, total_time, encargado_admin_id FROM users ORDER BY id DESC");
$all_users_query->execute();
$all_registered_users = $all_users_query->get_result()->fetch_all(MYSQLI_ASSOC);

$active_sessions_by_user = [];
$active_sessions_query = $conn->query("SELECT user_id, admin_id, started_at FROM active_time_sessions WHERE ended_at IS NULL");
if ($active_sessions_query) {
    while ($active_row = $active_sessions_query->fetch_assoc()) {
        $active_sessions_by_user[(int) $active_row['user_id']] = $active_row;
    }
}

$pending_activation_by_user = [];
$pending_activation_query = $conn->query("SELECT user_id, COUNT(*) AS total_pending FROM time_activation_requests WHERE status = 'pendiente' GROUP BY user_id");
if ($pending_activation_query) {
    while ($pending_row = $pending_activation_query->fetch_assoc()) {
        $pending_activation_by_user[(int) $pending_row['user_id']] = (int) $pending_row['total_pending'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Time - Club Cortefina</title>
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
            max-width: 1200px;
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
            max-width: 1200px;
            width: calc(100% - 40px);
            margin: 0 auto;
            padding: 120px 0 50px 0;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
        }

        .title {
            font-size: 2.5rem;
            color: #ffd700;
            text-shadow: 0 0 20px #ffd700;
            margin-bottom: 20px;
        }

        .subtitle {
            font-size: 1rem;
            color: #8a2be2;
            margin-bottom: 30px;
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

        .time-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 30px;
        }

        .time-buttons button {
            padding: 10px 20px;
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            border: none;
            color: #000;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.7rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .time-buttons button:hover {
            transform: scale(1.05);
        }

        .logout-btn {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            color: #ffffff;
            border: 2px solid #8a2be2;
            padding: 12px 20px;
            font-size: 0.7rem;
            font-family: 'Press Start 2P', monospace;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: linear-gradient(45deg, #4b0082, #8a2be2);
            box-shadow: 0 0 25px rgba(138, 43, 226, 0.6);
            transform: scale(1.05);
        }

        .timer-display {
            color: #ffd700;
            font-size: 0.8rem;
            margin-top: 10px;
        }

        .search-container {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .search-input {
            padding: 10px 20px;
            border: 2px solid #8a2be2;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.7rem;
            width: 300px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .rank-list {
            margin-top: 20px;
        }

        .rank-list p {
            color: #ffffff;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }

        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
            align-items: stretch;
        }

        .time-section {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            padding: 22px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .time-grid > .time-section:nth-child(3) {
            grid-column: 1 / -1;
        }

        .time-section::before {
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

        .time-section:hover::before {
            transform: translateX(100%);
        }

        .section-title {
            font-size: 1.5rem;
            color: #ffd700;
            text-shadow: 0 0 10px #ffd700;
            margin-bottom: 25px;
            text-align: center;
        }

        .user-list {
            max-height: 600px;
            overflow-y: auto;
            margin-top: 20px;
        }

        .user-item {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 10px;
            border: 1px solid rgba(138, 43, 226, 0.4);
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 15px;
            min-height: 120px;
        }

        .time-column {
            display: flex;
            flex-direction: column;
        }

        .time-column h4 {
            color: #ffd700;
            font-size: 0.7rem;
            margin-bottom: 5px;
        }

        .time-column span {
            color: #8a2be2;
            font-size: 0.6rem;
        }

        .owner-item {
            justify-content: flex-start;
        }

        .user-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            flex: 1;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
        }

        .time-grid > .time-section:nth-child(3) .user-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
            max-height: none;
            overflow: visible;
        }

        .time-grid > .time-section:nth-child(3) .user-item {
            margin-bottom: 0;
        }

        .user-field {
            display: flex;
            flex-direction: column;
            min-width: 120px;
        }

        .user-field label {
            font-size: 0.6rem;
            color: #8a2be2;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .user-field input, .user-field select {
            padding: 8px;
            border: 2px solid #8a2be2;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.5rem;
            transition: all 0.3s ease;
        }

        .user-field input:focus, .user-field select:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
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

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.3);
        }

        .user-table th {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            color: #ffd700;
            padding: 15px;
            text-align: left;
            font-size: 0.8rem;
            border-bottom: 2px solid #ffd700;
        }

        .user-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(138, 43, 226, 0.2);
        }

        .user-table th.rank-user-col,
        .user-table td.rank-user-col-cell {
            width: 48%;
            min-width: 520px;
            padding-left: 24px;
            padding-right: 24px;
        }

        .user-row:hover {
            background: rgba(138, 43, 226, 0.1);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 16px;
            position: static;
            width: 100%;
        }

        .user-cell img {
            position: static;
            left: auto;
            top: auto;
            transform: none;
            flex: 0 0 auto;
        }

        .user-cell div {
            margin-left: 0;
            flex: 1;
        }

        .user-name-line {
            color: #ffd700;
            font-size: 0.8rem;
            line-height: 1.35;
        }

        .user-rank-line {
            color: #8a2be2;
            font-size: 0.58rem;
            margin-top: 6px;
            line-height: 1.3;
        }

        .time-cell {
            text-align: center;
        }

        .action-cell {
            text-align: center;
            white-space: nowrap;
        }

        .action-cell .activate-btn,
        .action-cell .pause-btn {
            border: 1px solid #8a2be2;
            border-radius: 8px;
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            color: #000;
            padding: 10px 14px;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.55rem;
            cursor: pointer;
            margin: 4px 5px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .action-cell .activate-btn:hover,
        .action-cell .pause-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 12px rgba(138, 43, 226, 0.45);
        }

        .not-assigned-text {
            color: #ff9e9e;
            font-size: 0.55rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-nav {
                padding: 10px 10px;
            }

            .time-grid {
                grid-template-columns: 1fr;
            }

            .time-grid > .time-section:nth-child(3) {
                grid-column: auto;
            }

            .time-grid > .time-section:nth-child(3) .user-list {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 122px 0 30px 0;
            }

            .title {
                font-size: 2rem;
            }

            .user-info {
                flex-direction: column;
                width: 100%;
            }

            .user-actions {
                width: 100%;
                justify-content: center;
            }

            .user-table th, .user-table td {
                padding: 10px;
                font-size: 0.6rem;
            }

            .user-table th.rank-user-col,
            .user-table td.rank-user-col-cell {
                min-width: 320px;
                padding-left: 10px;
                padding-right: 10px;
            }

            .user-cell {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .user-cell img {
                margin-bottom: 10px;
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
        <div class="brand">Club Cortefina | Panel Time</div>
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
            <h1 class="title">Panel Time</h1>
            <p class="subtitle">Bienvenido, <?php echo htmlspecialchars($user['username']); ?> (Rango: <?php echo $user_rank_name; ?>)</p>
        </div>

        <div class="time-grid">
            <div class="time-section">
                <h3 class="section-title">Moderadores</h3>
                <div class="user-list">
                    <?php foreach ($all_users as $usr): ?>
                        <?php if ($usr['rank'] == 5): ?>
                        <div class="user-item">
                            <div class="user-info" style="flex-direction: row; align-items: center; justify-content: flex-start;">
                                <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($usr['habbo_username']); ?>" alt="Foto de Perfil" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #8a2be2; object-fit: cover;">
                                <span style="color: #ffd700; font-size: 0.8rem; margin-left: 10px;"><?php echo htmlspecialchars($usr['username']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="time-section">
                <h3 class="section-title">Admin</h3>
                <div class="user-list">
                    <?php foreach ($all_users as $usr): ?>
                        <?php if ($usr['rank'] == 6): ?>
                        <div class="user-item">
                            <div class="user-info" style="flex-direction: row; align-items: center; justify-content: flex-start;">
                                <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($usr['habbo_username']); ?>" alt="Foto de Perfil" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #8a2be2; object-fit: cover;">
                                <span style="color: #ffd700; font-size: 0.8rem; margin-left: 10px;"><?php echo htmlspecialchars($usr['username']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="time-section">
                <h3 class="section-title">Dueños</h3>
                <div class="user-list">
                    <?php foreach ($all_users as $usr): ?>
                        <?php if ($usr['rank'] == 7): ?>
                        <div class="user-item">
                            <div class="user-info" style="flex-direction: row; align-items: center; justify-content: flex-start;">
                                <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($usr['habbo_username']); ?>" alt="Foto de Perfil" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #8a2be2; object-fit: cover;">
                                <span style="color: #ffd700; font-size: 0.8rem; margin-left: 10px;"><?php echo htmlspecialchars($usr['username']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($user_rank >= 5): ?>
        <div class="search-container">
            <input type="text" class="search-input" placeholder="Buscar usuarios...">
        </div>
        <?php endif; ?>

        <table class="user-table">
            <thead>
                <tr>
                    <th class="rank-user-col">Usuario con Rango</th>
                    <th>Tiempo Total</th>
                    <th>Tiempo Actual</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_registered_users as $usr): ?>
                    <?php
                    $total_minutes = isset($usr['total_time']) ? $usr['total_time'] : 0;
                    $total_seconds = $total_minutes * 60;
                    $active_session = isset($active_sessions_by_user[(int) $usr['id']]) ? $active_sessions_by_user[(int) $usr['id']] : null;
                    $active_started_ts = ($active_session && !empty($active_session['started_at'])) ? strtotime($active_session['started_at']) : 0;
                    $active_elapsed_seconds = $active_started_ts > 0 ? max(0, time() - $active_started_ts) : 0;
                    $live_total_seconds = (int) $total_seconds + (int) $active_elapsed_seconds;

                    $total_hours = intdiv((int) $live_total_seconds, 3600);
                    $total_remaining_minutes = intdiv(((int) $live_total_seconds % 3600), 60);
                    $today_hours = intdiv((int) $active_elapsed_seconds, 3600);
                    $today_remaining_minutes = intdiv(((int) $active_elapsed_seconds % 3600), 60);

                    $is_active_session = $active_started_ts > 0;
                    $has_pending_activation = !empty($pending_activation_by_user[(int) $usr['id']]);
                    $is_assigned_user = ((int) $user_rank >= 7) || ((int) $usr['encargado_admin_id'] === (int) $user['id']);
                    ?>
                    <tr class="user-row">
                        <td class="user-cell rank-user-col-cell">
                            <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($usr['habbo_username']); ?>" alt="Foto de Perfil" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #8a2be2; object-fit: cover;">
                            <div style="display: flex; flex-direction: column;">
                                <span class="user-name-line"><?php echo htmlspecialchars($usr['username']); ?> (<?php echo $usr['verified'] ? 'Verificado' : 'No Verificado'; ?>)</span>
                                <span class="user-rank-line">Rango: <?php echo isset($rank_names[$usr['rank']]) ? $rank_names[$usr['rank']] : 'Usuario'; ?></span>
                            </div>
                        </td>
                        <td class="time-cell">
                            <span
                                id="time-<?php echo $usr['id']; ?>"
                                data-base-seconds="<?php echo (int) $total_seconds; ?>"
                                data-active-start="<?php echo $active_started_ts > 0 ? (int) $active_started_ts : ''; ?>"
                                style="color: #8a2be2; font-size: 0.6rem;"
                            >Horas: <?php echo $total_hours; ?> Minutos: <?php echo $total_remaining_minutes; ?></span>
                        </td>
                        <td class="time-cell">
                            <span id="current-time-<?php echo $usr['id']; ?>" style="color: #8a2be2; font-size: 0.6rem;">Horas: <?php echo $today_hours; ?> Minutos: <?php echo $today_remaining_minutes; ?></span>
                        </td>
                        <td class="action-cell">
                            <?php if ($is_assigned_user): ?>
                                <?php if ($is_active_session): ?>
                                    <button class="activate-btn" data-user="<?php echo $usr['id']; ?>" data-action="stop_active_time">Cerrar Tiempo</button>
                                    <button class="pause-btn" data-user="<?php echo $usr['id']; ?>" data-action="stop_active_time">Pausa</button>
                                <?php elseif ($has_pending_activation): ?>
                                    <button class="activate-btn" data-user="<?php echo $usr['id']; ?>" data-action="request_activation" disabled>Solicitud enviada</button>
                                    <button class="pause-btn" data-user="<?php echo $usr['id']; ?>" disabled>Pausa</button>
                                <?php else: ?>
                                    <button class="activate-btn" data-user="<?php echo $usr['id']; ?>" data-action="request_activation">Activar Tiempo</button>
                                    <button class="pause-btn" data-user="<?php echo $usr['id']; ?>" disabled>Pausa</button>
                                <?php endif; ?>
                            <?php else: ?>
                            <span class="not-assigned-text">No asignado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        (function() {
            const serverNow = <?php echo time(); ?>;
            const nowOffset = serverNow - Math.floor(Date.now() / 1000);

            function nowSeconds() {
                return Math.floor(Date.now() / 1000) + nowOffset;
            }

            function toInt(value, fallback) {
                const parsed = parseInt(value, 10);
                return Number.isFinite(parsed) ? parsed : fallback;
            }

            function formatTime(seconds) {
                const safeSeconds = Math.max(0, seconds);
                const hours = Math.floor(safeSeconds / 3600);
                const minutes = Math.floor((safeSeconds % 3600) / 60);
                return { hours, minutes };
            }

            function refreshRow(userId) {
                const totalSpan = document.getElementById('time-' + userId);
                const currentSpan = document.getElementById('current-time-' + userId);
                if (!totalSpan || !currentSpan) {
                    return;
                }

                const baseSeconds = toInt(totalSpan.dataset.baseSeconds, 0);
                const activeStart = toInt(totalSpan.dataset.activeStart, 0);
                const elapsedSeconds = activeStart > 0 ? Math.max(0, nowSeconds() - activeStart) : 0;
                const totalLiveSeconds = baseSeconds + elapsedSeconds;

                const totalParts = formatTime(totalLiveSeconds);
                const currentParts = formatTime(elapsedSeconds);

                totalSpan.textContent = 'Horas: ' + totalParts.hours + ' Minutos: ' + totalParts.minutes;
                currentSpan.textContent = 'Horas: ' + currentParts.hours + ' Minutos: ' + currentParts.minutes;
            }

            function refreshAllRows() {
                document.querySelectorAll('span[id^="time-"]').forEach((span) => {
                    const userId = span.id.replace('time-', '');
                    refreshRow(userId);
                });
            }

            async function runAction(userId, action) {
                const payload = new URLSearchParams({
                    action: action,
                    user_id: String(userId)
                });

                const response = await fetch('update_time.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: payload.toString(),
                });

                return response.json();
            }

            function setRowPending(userId) {
                const activateBtn = document.querySelector('.activate-btn[data-user="' + userId + '"]');
                const pauseBtn = document.querySelector('.pause-btn[data-user="' + userId + '"]');
                if (activateBtn) {
                    activateBtn.textContent = 'Solicitud enviada';
                    activateBtn.disabled = true;
                    activateBtn.dataset.action = 'request_activation';
                }
                if (pauseBtn) {
                    pauseBtn.disabled = true;
                }
            }

            function setRowStopped(userId, totalMinutes) {
                const totalSpan = document.getElementById('time-' + userId);
                if (totalSpan) {
                    if (typeof totalMinutes === 'number') {
                        totalSpan.dataset.baseSeconds = String(Math.max(0, totalMinutes * 60));
                    }
                    totalSpan.dataset.activeStart = '';
                }

                const activateBtn = document.querySelector('.activate-btn[data-user="' + userId + '"]');
                const pauseBtn = document.querySelector('.pause-btn[data-user="' + userId + '"]');
                if (activateBtn) {
                    activateBtn.textContent = 'Activar Tiempo';
                    activateBtn.disabled = false;
                    activateBtn.dataset.action = 'request_activation';
                }
                if (pauseBtn) {
                    pauseBtn.disabled = true;
                    pauseBtn.dataset.action = 'stop_active_time';
                }

                refreshRow(userId);
            }

            document.querySelectorAll('.activate-btn').forEach((button) => {
                button.addEventListener('click', async function() {
                    if (this.disabled) {
                        return;
                    }

                    const userId = this.getAttribute('data-user');
                    const action = this.getAttribute('data-action') || 'request_activation';
                    if (!userId) {
                        return;
                    }

                    try {
                        const data = await runAction(userId, action);
                        if (!data || !data.success) {
                            alert((data && data.message) ? data.message : 'No se pudo procesar la accion.');
                            return;
                        }

                        if (action === 'request_activation') {
                            setRowPending(userId);
                        } else if (action === 'stop_active_time') {
                            setRowStopped(userId, typeof data.total_minutes === 'number' ? data.total_minutes : null);
                        }
                    } catch (error) {
                        alert('No se pudo procesar la accion.');
                        console.error(error);
                    }
                });
            });

            document.querySelectorAll('.pause-btn').forEach((button) => {
                button.addEventListener('click', async function() {
                    if (this.disabled) {
                        return;
                    }

                    const userId = this.getAttribute('data-user');
                    if (!userId) {
                        return;
                    }

                    try {
                        const data = await runAction(userId, 'stop_active_time');
                        if (!data || !data.success) {
                            alert((data && data.message) ? data.message : 'No se pudo pausar el time.');
                            return;
                        }

                        setRowStopped(userId, typeof data.total_minutes === 'number' ? data.total_minutes : null);
                    } catch (error) {
                        alert('No se pudo pausar el time.');
                        console.error(error);
                    }
                });
            });

            refreshAllRows();
            setInterval(refreshAllRows, 1000);
        })();
    </script>
</body>
</html>
