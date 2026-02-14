<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$admin_id = (int) $_SESSION['user_id'];
$target_user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

if ($target_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

$conn = getDBConnection();

$bootstrap_statements = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS total_time INT(11) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS experiencia INT(11) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS creditos INT(11) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS horas_acumuladas INT(11) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS horas_actuales INT(11) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS encargado_admin_id INT(11) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS mission_verified TINYINT(1) DEFAULT 0",
];

foreach ($bootstrap_statements as $sql) {
    $conn->query($sql);
}

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

$conn->query("
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

$admin_stmt = $conn->prepare("SELECT id, rank FROM users WHERE id = ? LIMIT 1");
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_user = $admin_stmt->get_result()->fetch_assoc();
$admin_stmt->close();

if (!$admin_user || (int) $admin_user['rank'] < 5) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$target_stmt = $conn->prepare("SELECT id, rank, mission_verified, encargado_admin_id FROM users WHERE id = ? LIMIT 1");
$target_stmt->bind_param("i", $target_user_id);
$target_stmt->execute();
$target_user = $target_stmt->get_result()->fetch_assoc();
$target_stmt->close();

if (!$target_user) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$can_manage = ((int) $admin_user['rank'] >= 7) || ((int) $target_user['encargado_admin_id'] === $admin_id);
if (!$can_manage) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'User not assigned to this admin']);
    exit;
}

if ($action === 'request_activation') {
    $active_stmt = $conn->prepare("SELECT id FROM active_time_sessions WHERE user_id = ? AND ended_at IS NULL LIMIT 1");
    $active_stmt->bind_param("i", $target_user_id);
    $active_stmt->execute();
    $active_exists = (bool) $active_stmt->get_result()->fetch_assoc();
    $active_stmt->close();

    if ($active_exists) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'El time ya esta activo para este usuario.']);
        exit;
    }

    $pending_stmt = $conn->prepare("SELECT id FROM time_activation_requests WHERE user_id = ? AND status = 'pendiente' LIMIT 1");
    $pending_stmt->bind_param("i", $target_user_id);
    $pending_stmt->execute();
    $pending_exists = (bool) $pending_stmt->get_result()->fetch_assoc();
    $pending_stmt->close();

    if ($pending_exists) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Ya existe una solicitud pendiente para este usuario.']);
        exit;
    }

    $insert_stmt = $conn->prepare("
        INSERT INTO time_activation_requests (user_id, admin_id, status)
        VALUES (?, ?, 'pendiente')
    ");
    $insert_stmt->bind_param("ii", $target_user_id, $admin_id);
    $insert_stmt->execute();
    $insert_stmt->close();

    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Solicitud enviada. Esperando aceptacion del usuario.']);
    exit;
}

if ($action === 'stop_active_time') {
    $session_stmt = $conn->prepare("
        SELECT id, user_id, admin_id, started_at
        FROM active_time_sessions
        WHERE user_id = ? AND ended_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $session_stmt->bind_param("i", $target_user_id);
    $session_stmt->execute();
    $active_session = $session_stmt->get_result()->fetch_assoc();
    $session_stmt->close();

    if (!$active_session) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'No hay time activo para este usuario.']);
        exit;
    }

    $started_at_ts = strtotime((string) $active_session['started_at']);
    $elapsed_seconds = max(0, time() - (int) $started_at_ts);
    $minutes_to_add = (int) ceil($elapsed_seconds / 60);
    $minutes_to_add = max(0, min(600, $minutes_to_add));
    $hours_for_stats = (int) floor($minutes_to_add / 60);
    $credits_to_add = $minutes_to_add > 0 ? max(1, (int) round(($minutes_to_add * 3) / 60)) : 0;
    $experience_to_add = $minutes_to_add * 10;

    $conn->begin_transaction();
    try {
        if ($minutes_to_add > 0) {
            $update_stmt = $conn->prepare("
                UPDATE users
                SET total_time = total_time + ?,
                    horas_actuales = horas_actuales + ?,
                    horas_acumuladas = horas_acumuladas + ?,
                    creditos = creditos + ?,
                    experiencia = experiencia + ?
                WHERE id = ?
            ");
            $update_stmt->bind_param("iiiiii", $minutes_to_add, $hours_for_stats, $hours_for_stats, $credits_to_add, $experience_to_add, $target_user_id);
            $update_stmt->execute();
            $update_stmt->close();

            $session_admin_id = (int) $active_session['admin_id'];
            $log_stmt = $conn->prepare("
                INSERT INTO time_logs (request_id, user_id, admin_id, total_minutos, creditos_otorgados, created_at)
                VALUES (NULL, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->bind_param("iiii", $target_user_id, $session_admin_id, $minutes_to_add, $credits_to_add);
            $log_stmt->execute();
            $log_stmt->close();
        }

        $close_stmt = $conn->prepare("UPDATE active_time_sessions SET ended_at = NOW() WHERE id = ?");
        $close_stmt->bind_param("i", $active_session['id']);
        $close_stmt->execute();
        $close_stmt->close();

        $totals_stmt = $conn->prepare("
            SELECT total_time, horas_actuales, horas_acumuladas, creditos, experiencia
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $totals_stmt->bind_param("i", $target_user_id);
        $totals_stmt->execute();
        $totals = $totals_stmt->get_result()->fetch_assoc();
        $totals_stmt->close();

        $conn->commit();
        $conn->close();

        echo json_encode([
            'success' => true,
            'message' => 'Time guardado y sesion cerrada.',
            'added_minutes' => $minutes_to_add,
            'total_minutes' => (int) ($totals['total_time'] ?? 0),
            'horas_actuales' => (int) ($totals['horas_actuales'] ?? 0),
            'horas_acumuladas' => (int) ($totals['horas_acumuladas'] ?? 0),
            'creditos' => (int) ($totals['creditos'] ?? 0),
            'experiencia' => (int) ($totals['experiencia'] ?? 0),
        ]);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'No se pudo cerrar el time activo.']);
        exit;
    }
}

$conn->close();
echo json_encode(['success' => false, 'message' => 'Accion invalida']);
?>
