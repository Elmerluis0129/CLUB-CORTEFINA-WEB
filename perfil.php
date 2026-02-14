<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function getRankName($rank) {
    $ranks = [
        1 => 'Bartender',
        2 => 'Diva',
        3 => 'Seguridad',
        4 => 'RH',
        5 => 'Gerente',
        6 => 'Heredero',
        7 => 'Founder',
    ];

    return isset($ranks[(int) $rank]) ? $ranks[(int) $rank] : 'Usuario';
}

function getRequiredMissionByRank($rank) {
    $missions = [
        1 => 'BARTERDER [CLUB CORTEFINA]',
        2 => 'DIVA [CLUB CORTEFINA]',
        3 => 'SEGURIDAD [CLUB CORTEFINA]',
    ];

    return isset($missions[(int) $rank]) ? $missions[(int) $rank] : '';
}

function fetchHabboMotto($habbo_username) {
    $api_url = "https://www.habbo.es/api/public/users?name=" . urlencode($habbo_username);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $json_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($json_response === false || $http_code !== 200) {
        return ['ok' => false, 'motto' => '', 'error' => 'No se pudo consultar Habbo API'];
    }

    $data = json_decode($json_response, true);
    if (!is_array($data) || !isset($data['motto'])) {
        return ['ok' => false, 'motto' => '', 'error' => 'No se encontro motto en Habbo API'];
    }

    return ['ok' => true, 'motto' => trim($data['motto']), 'error' => ''];
}

$conn = getDBConnection();

// Bootstrap schema for environments where migrations are pending
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS total_time INT(11) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS experiencia INT(11) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS creditos INT(11) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS horas_acumuladas INT(11) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS horas_actuales INT(11) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS encargado_admin_id INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS mission_verified TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS mission_verified_at DATETIME DEFAULT NULL");

$conn->query("
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
$conn->query("
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

$flash = isset($_GET['msg']) ? $_GET['msg'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_mission'])) {
        $user_stmt = $conn->prepare("SELECT id, habbo_username, rank FROM users WHERE id = ? LIMIT 1");
        $user_stmt->bind_param("i", $_SESSION['user_id']);
        $user_stmt->execute();
        $user_data = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();

        if (!$user_data) {
            header("Location: perfil.php?msg=usuario_no_encontrado");
            exit;
        }

        $required_mission = getRequiredMissionByRank((int) $user_data['rank']);
        if ($required_mission === '') {
            $update_stmt = $conn->prepare("UPDATE users SET mission_verified = 1, mission_verified_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $_SESSION['user_id']);
            $update_stmt->execute();
            $update_stmt->close();
            header("Location: perfil.php?msg=mision_no_requerida");
            exit;
        }

        $motto_result = fetchHabboMotto($user_data['habbo_username']);
        if (!$motto_result['ok']) {
            header("Location: perfil.php?msg=error_api_habbo");
            exit;
        }

        $current_motto = strtoupper(trim($motto_result['motto']));
        $expected_motto = strtoupper(trim($required_mission));
        $is_verified = ($current_motto === $expected_motto);

        if ($is_verified) {
            $update_stmt = $conn->prepare("UPDATE users SET mission_verified = 1, mission_verified_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $_SESSION['user_id']);
            $update_stmt->execute();
            $update_stmt->close();
            header("Location: perfil.php?msg=mision_ok");
            exit;
        }

        $update_stmt = $conn->prepare("UPDATE users SET mission_verified = 0, mission_verified_at = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $_SESSION['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        header("Location: perfil.php?msg=mision_fail");
        exit;
    }

    if (isset($_POST['respond_encargado_request'])) {
        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $decision = isset($_POST['decision']) ? trim((string) $_POST['decision']) : '';
        if ($request_id <= 0 || ($decision !== 'aceptar' && $decision !== 'denegar')) {
            header("Location: perfil.php?msg=solicitud_invalida");
            exit;
        }

        $request_stmt = $conn->prepare("
            SELECT id, user_id, encargado_admin_id
            FROM encargado_requests
            WHERE id = ? AND user_id = ? AND status = 'pendiente'
            LIMIT 1
        ");
        $request_stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
        $request_stmt->execute();
        $request = $request_stmt->get_result()->fetch_assoc();
        $request_stmt->close();

        if (!$request) {
            header("Location: perfil.php?msg=solicitud_no_encontrada");
            exit;
        }

        $new_status = ($decision === 'aceptar') ? 'aceptada' : 'denegada';

        $conn->begin_transaction();
        try {
            if ($new_status === 'aceptada') {
                $set_encargado_stmt = $conn->prepare("UPDATE users SET encargado_admin_id = ? WHERE id = ?");
                $set_encargado_stmt->bind_param("ii", $request['encargado_admin_id'], $_SESSION['user_id']);
                $set_encargado_stmt->execute();
                $set_encargado_stmt->close();

                $cancel_others_stmt = $conn->prepare("UPDATE encargado_requests SET status = 'cancelada', responded_at = NOW() WHERE user_id = ? AND status = 'pendiente' AND id <> ?");
                $cancel_others_stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
                $cancel_others_stmt->execute();
                $cancel_others_stmt->close();
            }

            $update_request_stmt = $conn->prepare("UPDATE encargado_requests SET status = ?, responded_at = NOW() WHERE id = ?");
            $update_request_stmt->bind_param("si", $new_status, $request_id);
            $update_request_stmt->execute();
            $update_request_stmt->close();

            $conn->commit();
            header("Location: perfil.php?msg=" . ($new_status === 'aceptada' ? 'encargado_aceptado' : 'encargado_denegado'));
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            header("Location: perfil.php?msg=error_solicitud");
            exit;
        }
    }

    if (isset($_POST['respond_activation_request'])) {
        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $decision = isset($_POST['decision']) ? trim((string) $_POST['decision']) : '';
        if ($request_id <= 0 || ($decision !== 'aceptar' && $decision !== 'denegar')) {
            header("Location: perfil.php?msg=solicitud_invalida");
            exit;
        }

        $request_stmt = $conn->prepare("
            SELECT id, user_id, admin_id
            FROM time_activation_requests
            WHERE id = ? AND user_id = ? AND status = 'pendiente'
            LIMIT 1
        ");
        $request_stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
        $request_stmt->execute();
        $request = $request_stmt->get_result()->fetch_assoc();
        $request_stmt->close();

        if (!$request) {
            header("Location: perfil.php?msg=solicitud_no_encontrada");
            exit;
        }

        if ($decision === 'denegar') {
            $deny_stmt = $conn->prepare("UPDATE time_activation_requests SET status = 'denegada', responded_at = NOW() WHERE id = ?");
            $deny_stmt->bind_param("i", $request_id);
            $deny_stmt->execute();
            $deny_stmt->close();

            header("Location: perfil.php?msg=time_activacion_denegada");
            exit;
        }

        $user_mission_stmt = $conn->prepare("SELECT rank, mission_verified FROM users WHERE id = ? LIMIT 1");
        $user_mission_stmt->bind_param("i", $_SESSION['user_id']);
        $user_mission_stmt->execute();
        $user_mission_data = $user_mission_stmt->get_result()->fetch_assoc();
        $user_mission_stmt->close();

        if (!$user_mission_data) {
            header("Location: perfil.php?msg=usuario_no_encontrado");
            exit;
        }

        $required_mission = getRequiredMissionByRank((int) $user_mission_data['rank']);
        if ($required_mission !== '' && (int) $user_mission_data['mission_verified'] !== 1) {
            header("Location: perfil.php?msg=debes_verificar_mision");
            exit;
        }

        $active_stmt = $conn->prepare("SELECT id FROM active_time_sessions WHERE user_id = ? AND ended_at IS NULL LIMIT 1");
        $active_stmt->bind_param("i", $_SESSION['user_id']);
        $active_stmt->execute();
        $active_exists = (bool) $active_stmt->get_result()->fetch_assoc();
        $active_stmt->close();

        if ($active_exists) {
            $cancel_request_stmt = $conn->prepare("UPDATE time_activation_requests SET status = 'cancelada', responded_at = NOW() WHERE id = ?");
            $cancel_request_stmt->bind_param("i", $request_id);
            $cancel_request_stmt->execute();
            $cancel_request_stmt->close();

            header("Location: perfil.php?msg=time_ya_activo");
            exit;
        }

        $conn->begin_transaction();
        try {
            $accept_stmt = $conn->prepare("UPDATE time_activation_requests SET status = 'aceptada', responded_at = NOW() WHERE id = ?");
            $accept_stmt->bind_param("i", $request_id);
            $accept_stmt->execute();
            $accept_stmt->close();

            $cancel_others_stmt = $conn->prepare("UPDATE time_activation_requests SET status = 'cancelada', responded_at = NOW() WHERE user_id = ? AND status = 'pendiente' AND id <> ?");
            $cancel_others_stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
            $cancel_others_stmt->execute();
            $cancel_others_stmt->close();

            $start_session_stmt = $conn->prepare("
                INSERT INTO active_time_sessions (user_id, admin_id, request_id, started_at, ended_at)
                VALUES (?, ?, ?, NOW(), NULL)
            ");
            $start_session_stmt->bind_param("iii", $_SESSION['user_id'], $request['admin_id'], $request_id);
            $start_session_stmt->execute();
            $start_session_stmt->close();

            $conn->commit();
            header("Location: perfil.php?msg=time_activacion_aceptada");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            header("Location: perfil.php?msg=error_solicitud");
            exit;
        }
    }

    if (isset($_POST['run_time_request'])) {
        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        if ($request_id <= 0) {
            header("Location: perfil.php?msg=solicitud_invalida");
            exit;
        }

        $request_stmt = $conn->prepare("
            SELECT tr.id, tr.user_id, tr.admin_id, tr.horas, tr.minutos, tr.total_minutos, tr.status,
                   u.rank, u.mission_verified
            FROM time_requests tr
            INNER JOIN users u ON u.id = tr.user_id
            WHERE tr.id = ? AND tr.user_id = ? AND tr.status = 'pendiente'
            LIMIT 1
        ");
        $request_stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
        $request_stmt->execute();
        $request = $request_stmt->get_result()->fetch_assoc();
        $request_stmt->close();

        if (!$request) {
            header("Location: perfil.php?msg=solicitud_no_encontrada");
            exit;
        }

        $required_mission = getRequiredMissionByRank((int) $request['rank']);
        if ($required_mission !== '' && (int) $request['mission_verified'] !== 1) {
            header("Location: perfil.php?msg=debes_verificar_mision");
            exit;
        }

        $total_minutes = max(1, (int) $request['total_minutos']);
        $hours_for_stats = (int) floor($total_minutes / 60);
        $credits_to_add = max(1, (int) round(($total_minutes * 3) / 60));
        $experience_to_add = $total_minutes * 10;

        $conn->begin_transaction();
        try {
            $complete_stmt = $conn->prepare("
                UPDATE time_requests
                SET status = 'completada', accepted_at = NOW(), completed_at = NOW()
                WHERE id = ? AND status = 'pendiente'
            ");
            $complete_stmt->bind_param("i", $request_id);
            $complete_stmt->execute();
            $complete_stmt->close();

            $update_user_stmt = $conn->prepare("
                UPDATE users
                SET total_time = total_time + ?,
                    horas_actuales = horas_actuales + ?,
                    horas_acumuladas = horas_acumuladas + ?,
                    creditos = creditos + ?,
                    experiencia = experiencia + ?
                WHERE id = ?
            ");
            $update_user_stmt->bind_param("iiiiii", $total_minutes, $hours_for_stats, $hours_for_stats, $credits_to_add, $experience_to_add, $_SESSION['user_id']);
            $update_user_stmt->execute();
            $update_user_stmt->close();

            $log_stmt = $conn->prepare("
                INSERT INTO time_logs (request_id, user_id, admin_id, total_minutos, creditos_otorgados, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->bind_param("iiiii", $request_id, $_SESSION['user_id'], $request['admin_id'], $total_minutes, $credits_to_add);
            $log_stmt->execute();
            $log_stmt->close();

            $conn->commit();
            header("Location: perfil.php?msg=time_iniciado");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            header("Location: perfil.php?msg=error_solicitud");
            exit;
        }
    }
}

$user_stmt = $conn->prepare("
    SELECT u.id, u.username, u.habbo_username, u.ccf_code, u.verified, u.total_time, u.created_at,
           u.rank, u.experiencia, u.creditos, u.horas_acumuladas, u.horas_actuales,
           u.mission_verified, u.mission_verified_at, u.encargado_admin_id,
           ea.username AS encargado_username, ea.habbo_username AS encargado_habbo_username,
           ats.started_at AS active_time_started_at, ta.username AS active_time_admin_username
    FROM users u
    LEFT JOIN users ea ON ea.id = u.encargado_admin_id
    LEFT JOIN active_time_sessions ats ON ats.user_id = u.id AND ats.ended_at IS NULL
    LEFT JOIN users ta ON ta.id = ats.admin_id
    WHERE u.id = ?
    LIMIT 1
");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$current_user) {
    $conn->close();
    echo "Usuario no encontrado.";
    exit;
}

$pending_stmt = $conn->prepare("
    SELECT tr.id, tr.horas, tr.minutos, tr.total_minutos, tr.created_at, tr.status,
           a.username AS admin_username
    FROM time_requests tr
    LEFT JOIN users a ON a.id = tr.admin_id
    WHERE tr.user_id = ? AND tr.status = 'pendiente'
    ORDER BY tr.created_at DESC
");
$pending_stmt->bind_param("i", $_SESSION['user_id']);
$pending_stmt->execute();
$pending_requests = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_stmt->close();

$pending_encargado_stmt = $conn->prepare("
    SELECT er.id, er.created_at,
           ea.username AS encargado_username,
           rb.username AS requested_by_username
    FROM encargado_requests er
    LEFT JOIN users ea ON ea.id = er.encargado_admin_id
    LEFT JOIN users rb ON rb.id = er.requested_by_id
    WHERE er.user_id = ? AND er.status = 'pendiente'
    ORDER BY er.created_at DESC
");
$pending_encargado_stmt->bind_param("i", $_SESSION['user_id']);
$pending_encargado_stmt->execute();
$pending_encargado_requests = $pending_encargado_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_encargado_stmt->close();

$pending_activation_stmt = $conn->prepare("
    SELECT tar.id, tar.created_at,
           a.username AS admin_username
    FROM time_activation_requests tar
    LEFT JOIN users a ON a.id = tar.admin_id
    WHERE tar.user_id = ? AND tar.status = 'pendiente'
    ORDER BY tar.created_at DESC
");
$pending_activation_stmt->bind_param("i", $_SESSION['user_id']);
$pending_activation_stmt->execute();
$pending_activation_requests = $pending_activation_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_activation_stmt->close();

$logs_stmt = $conn->prepare("
    SELECT tl.id, tl.total_minutos, tl.creditos_otorgados, tl.created_at, a.username AS admin_username
    FROM time_logs tl
    LEFT JOIN users a ON a.id = tl.admin_id
    WHERE tl.user_id = ?
    ORDER BY tl.created_at DESC
    LIMIT 25
");
$logs_stmt->bind_param("i", $_SESSION['user_id']);
$logs_stmt->execute();
$time_logs = $logs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logs_stmt->close();
$conn->close();

$rank_name = getRankName((int) $current_user['rank']);
$required_mission = getRequiredMissionByRank((int) $current_user['rank']);
$mission_status = ($required_mission === '')
    ? 'No requerida'
    : (((int) $current_user['mission_verified'] === 1) ? 'Mision verificada' : 'Mision no anadida');

$flash_messages = [
    'mision_ok' => 'Mision verificada correctamente.',
    'mision_fail' => 'La mision en Habbo no coincide con la requerida.',
    'mision_no_requerida' => 'Tu rango no requiere una mision de motto.',
    'debes_verificar_mision' => 'Debes verificar tu mision antes de iniciar el time.',
    'time_iniciado' => 'Solicitud aceptada. El time se aplico y quedo registrado.',
    'error_solicitud' => 'No se pudo procesar la solicitud, intenta de nuevo.',
    'error_api_habbo' => 'No se pudo verificar la mision por error con la API de Habbo.',
    'solicitud_no_encontrada' => 'La solicitud ya no esta disponible.',
    'solicitud_invalida' => 'Solicitud invalida.',
    'usuario_no_encontrado' => 'Usuario no encontrado.',
    'encargado_aceptado' => 'Solicitud de encargado aceptada.',
    'encargado_denegado' => 'Solicitud de encargado denegada.',
    'time_activacion_aceptada' => 'Activacion de time aceptada. El time ya esta corriendo.',
    'time_activacion_denegada' => 'Solicitud de activacion de time denegada.',
    'time_ya_activo' => 'Ya tienes un time activo en curso.',
];
$flash_text = isset($flash_messages[$flash]) ? $flash_messages[$flash] : '';

$base_total_minutes = (int) $current_user['total_time'];
$active_time_started_ts = !empty($current_user['active_time_started_at']) ? strtotime($current_user['active_time_started_at']) : 0;
$active_elapsed_seconds = $active_time_started_ts > 0 ? max(0, time() - $active_time_started_ts) : 0;
$total_minutes = $base_total_minutes + intdiv($active_elapsed_seconds, 60);
$total_hours = intdiv($total_minutes, 60);
$remaining_minutes = $total_minutes % 60;
$current_experience = max(0, (int) $current_user['experiencia']);
$current_level = (int) floor($current_experience / 100) + 1;
$next_level_target_exp = $current_level * 100;
$current_level_base_exp = ($current_level - 1) * 100;
$exp_inside_level = max(0, $current_experience - $current_level_base_exp);
$level_progress = (int) round(($exp_inside_level / 100) * 100);
$level_progress = max(0, min(100, $level_progress));
$exp_to_next_level = max(0, $next_level_target_exp - $current_experience);
$progress = $level_progress;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Club Cortefina</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Press Start 2P', monospace;
            background: linear-gradient(135deg, #1a0033 0%, #000000 50%, #0a0a2e 100%);
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            padding: 96px 0 20px 0;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(138, 43, 226, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 0, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .top-nav {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px);
            max-width: 1450px;
            background: rgba(0, 0, 0, 0.65);
            border: 1px solid rgba(138, 43, 226, 0.65);
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            z-index: 4;
            margin: 0 auto;
        }

        .brand { color: #ffd700; font-size: 0.7rem; text-shadow: 0 0 10px #ffd700; }

        .nav-links { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }

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

        .nav-links a:hover { background: #8a2be2; transform: translateY(-1px); }

        .container {
            width: calc(100% - 40px);
            max-width: 1450px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            padding: 0;
        }

        .flash {
            margin: 0 0 14px 0;
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid #8a2be2;
            background: rgba(0, 0, 0, 0.45);
            color: #ffd700;
            font-size: 0.6rem;
        }

        .grid-two {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
            justify-items: center;
            align-items: stretch;
        }

        .grid-two .section {
            width: min(100%, 620px);
            justify-self: center;
            text-align: center;
        }

        .section {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border: 1px solid rgba(138, 43, 226, 0.38);
            border-radius: 15px;
            padding: 22px;
            box-shadow: 0 0 26px rgba(0, 0, 0, 0.42);
            margin-bottom: 16px;
        }

        .section h2 {
            color: #ffd700;
            font-size: 0.95rem;
            margin-bottom: 14px;
            text-shadow: 0 0 10px #ffd700;
        }

        .avatar-main {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 3px solid #ffd700;
            box-shadow: 0 0 16px rgba(255, 215, 0, 0.45);
            display: block;
            margin: 0 auto 16px auto;
            object-fit: cover;
        }

        .line {
            font-size: 0.62rem;
            color: #8a2be2;
            margin-bottom: 8px;
            line-height: 1.4;
            text-align: center;
        }

        .profile-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .profile-core {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .profile-section h2 {
            width: 100%;
            text-align: center;
            margin-bottom: 6px;
        }

        .profile-section .avatar-main {
            margin: 0 auto 6px auto;
        }

        .profile-section .line {
            width: 100%;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(138, 43, 226, 0.35);
            border-radius: 10px;
            padding: 14px 12px;
            min-height: 88px;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cells {
            display: grid;
            gap: 10px;
        }

        .time-section .cells {
            width: min(100%, 520px);
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 0 auto;
        }

        .time-section {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .time-section h2 {
            width: 100%;
        }

        .cell {
            background: rgba(0, 0, 0, 0.38);
            border: 1px solid rgba(138, 43, 226, 0.35);
            border-radius: 10px;
            padding: 14px 12px;
            min-height: 92px;
            margin-bottom: 0;
            text-align: center;
        }

        .time-section .cell {
            width: 100%;
            min-height: 92px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .time-section .cell:nth-child(1),
        .time-section .cell:nth-child(2) {
            min-height: 92px;
        }

        .cell-title {
            color: #8a2be2;
            font-size: 0.55rem;
            margin-bottom: 9px;
        }

        .cell-value {
            color: #ffd700;
            font-size: 0.8rem;
            line-height: 1.35;
            word-break: break-word;
        }

        .btn {
            border: 1px solid #8a2be2;
            border-radius: 8px;
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            color: #000;
            padding: 10px 14px;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.55rem;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .encargado-box { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

        .time-section .encargado-box {
            width: 100%;
            justify-content: center;
            flex-direction: column;
        }

        .encargado-avatar {
            width: 66px;
            height: 66px;
            border-radius: 50%;
            border: 2px solid #8a2be2;
            object-fit: cover;
        }

        .req-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
            gap: 10px;
        }

        .req-card {
            background: rgba(0, 0, 0, 0.38);
            border: 1px solid rgba(138, 43, 226, 0.4);
            border-radius: 10px;
            padding: 20px 16px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .req-line {
            font-size: 0.58rem;
            color: #d4b2ff;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .warn { color: #ff6b6b; font-size: 0.55rem; margin-top: 8px; line-height: 1.4; }
        .ok { color: #00ff99; }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(138, 43, 226, 0.35);
            border-radius: 10px;
        }

        table { width: 100%; border-collapse: collapse; min-width: 980px; }
        th, td {
            padding: 10px;
            border-bottom: 1px solid rgba(138, 43, 226, 0.26);
            font-size: 0.56rem;
            text-align: center;
            vertical-align: middle;
            color: #e9d8ff;
        }

        th { color: #ffd700; background: rgba(138, 43, 226, 0.14); }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
            align-items: stretch;
            width: 100%;
        }

        .info-grid .cell {
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .info-grid .cell .cell-title,
        .info-grid .cell .cell-value {
            width: 100%;
            text-align: center;
        }

        .progress-bar {
            margin-top: 10px;
            width: 100%;
            height: 16px;
            border: 1px solid #8a2be2;
            border-radius: 10px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.4);
        }

        .progress-fill {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #ffd700, #ffb347);
            box-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
            transition: width 0.5s ease;
        }

        /* Center all content in profile cells */
        .section,
        .section .line,
        .section .cells,
        .section .cell,
        .section .cell-title,
        .section .cell-value,
        .section .req-card,
        .section .req-line {
            text-align: center;
        }

        .section .encargado-box,
        .section .req-grid,
        .section .info-grid {
            justify-content: center;
        }

        .section .table-wrap table th,
        .section .table-wrap table td {
            text-align: center;
        }

        @media (max-width: 980px) {
            body { padding-top: 126px; }
            .top-nav { padding: 10px 10px; }
            .grid-two { grid-template-columns: 1fr; }
            .section { padding: 16px; }
            .profile-section .line { width: 100%; }
            .time-section .cells {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="brand">Club Cortefina | Mi Perfil</div>
        <div class="nav-links">
            <a href="index.php">Inicio</a>
            <a href="perfil.php">Perfil</a>
            <?php if ((int) $current_user['rank'] >= 5): ?>
                <a href="time.php">Time</a>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Cerrar</a>
        </div>
    </div>

    <div class="container">
        <?php if ($flash_text !== ''): ?>
            <div class="flash"><?php echo htmlspecialchars($flash_text); ?></div>
        <?php endif; ?>

        <div class="grid-two">
            <div class="section profile-section">
                <h2>Mi Perfil</h2>
                <div class="profile-core">
                    <img class="avatar-main" src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($current_user['habbo_username']); ?>" alt="Avatar">
                    <p class="line">Usuario Habbo: <?php echo htmlspecialchars($current_user['habbo_username']); ?></p>
                    <p class="line">Verificado CCF: <?php echo ((int) $current_user['verified'] === 1) ? 'Si' : 'No'; ?></p>
                    <p class="line">Rango: <?php echo htmlspecialchars($rank_name); ?></p>
                    <?php if ((int) $current_user['verified'] !== 1): ?>
                        <p class="line">Codigo CCF: <strong><?php echo htmlspecialchars($current_user['ccf_code']); ?></strong></p>
                        <div style="text-align:center;">
                            <a href="verify_habbo.php" class="btn" style="display:inline-block;text-decoration:none;">Verificar cuenta CCF</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section time-section">
                <h2>Tiempo y Mision</h2>
                <div class="cells">
                    <div class="cell">
                        <div class="cell-title">Tiempo Total</div>
                        <div
                            id="profile-total-time"
                            class="cell-value"
                            data-base-minutes="<?php echo (int) $base_total_minutes; ?>"
                            data-active-start="<?php echo $active_time_started_ts > 0 ? (int) $active_time_started_ts : ''; ?>"
                        ><?php echo number_format($total_hours); ?>h <?php echo str_pad((string) $remaining_minutes, 2, '0', STR_PAD_LEFT); ?>m (<?php echo number_format($total_minutes); ?> min)</div>
                        <?php if ($active_time_started_ts > 0): ?>
                            <div class="cell-value ok" style="font-size:0.58rem; margin-top:8px;">
                                Time activo<?php if (!empty($current_user['active_time_admin_username'])): ?> por <?php echo htmlspecialchars($current_user['active_time_admin_username']); ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="cell">
                        <div class="cell-title">Encargado</div>
                        <?php if (!empty($current_user['encargado_username'])): ?>
                            <div class="encargado-box">
                                <img class="encargado-avatar" src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($current_user['encargado_habbo_username']); ?>" alt="Encargado">
                                <div class="cell-value"><?php echo htmlspecialchars($current_user['encargado_username']); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="cell-value">Sin encargado asignado</div>
                        <?php endif; ?>
                    </div>
                    <div class="cell">
                        <div class="cell-title">Mision Habbo</div>
                        <div class="cell-value">Rango: <?php echo htmlspecialchars($rank_name); ?></div>
                        <div class="cell-value" style="font-size:0.6rem; margin-top:8px;">
                            Mision requerida: <?php echo $required_mission !== '' ? htmlspecialchars($required_mission) : 'No requerida para tu rango'; ?>
                        </div>
                        <div class="cell-value <?php echo $mission_status === 'Mision no anadida' ? '' : 'ok'; ?>" style="font-size:0.58rem; margin-top:8px;">
                            Estado de mision: <?php echo htmlspecialchars($mission_status); ?>
                        </div>
                        <form method="POST">
                            <button type="submit" name="verify_mission" class="btn">Verificar mision</button>
                        </form>
                    </div>
                    <div class="cell">
                        <div class="cell-title">Nivel (1 a infinito)</div>
                        <div class="cell-value">Nivel actual: <?php echo number_format($current_level); ?></div>
                        <div class="cell-value" style="font-size:0.6rem; margin-top:8px;">1 minuto = 10 EXP</div>
                        <div class="cell-value" style="font-size:0.6rem; margin-top:8px;">Nivel <?php echo number_format($current_level); ?> requiere <?php echo number_format($next_level_target_exp); ?> EXP</div>
                        <div class="cell-value" style="font-size:0.58rem; margin-top:8px;">Faltan <?php echo number_format($exp_to_next_level); ?> EXP para nivel <?php echo number_format($current_level + 1); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Solicitudes</h2>
            <?php $has_any_requests = !empty($pending_requests) || !empty($pending_encargado_requests) || !empty($pending_activation_requests); ?>
            <?php if (!$has_any_requests): ?>
                <p class="line">No tienes solicitudes pendientes.</p>
            <?php else: ?>
                <div class="req-grid">
                    <?php foreach ($pending_encargado_requests as $request): ?>
                        <div class="req-card">
                            <div class="req-line">Solicitud de encargado</div>
                            <div class="req-line">Encargado propuesto: <?php echo htmlspecialchars($request['encargado_username'] ?: 'N/A'); ?></div>
                            <div class="req-line">Solicitado por: <?php echo htmlspecialchars($request['requested_by_username'] ?: 'N/A'); ?></div>
                            <div class="req-line">Fecha: <?php echo htmlspecialchars($request['created_at']); ?></div>
                            <form method="POST" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                <input type="hidden" name="decision" value="aceptar">
                                <button type="submit" name="respond_encargado_request" class="btn">Aceptar</button>
                            </form>
                            <form method="POST" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                <input type="hidden" name="decision" value="denegar">
                                <button type="submit" name="respond_encargado_request" class="btn">Denegar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($pending_activation_requests as $request): ?>
                        <div class="req-card">
                            <div class="req-line">Solicitud de activacion de time</div>
                            <div class="req-line">Admin: <?php echo htmlspecialchars($request['admin_username'] ?: 'N/A'); ?></div>
                            <div class="req-line">Fecha: <?php echo htmlspecialchars($request['created_at']); ?></div>
                            <?php if ($required_mission !== '' && (int) $current_user['mission_verified'] !== 1): ?>
                                <div class="warn">Debes verificar tu mision antes de aceptar esta activacion.</div>
                            <?php endif; ?>
                            <form method="POST" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                <input type="hidden" name="decision" value="aceptar">
                                <button type="submit" name="respond_activation_request" class="btn">Aceptar</button>
                            </form>
                            <form method="POST" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                <input type="hidden" name="decision" value="denegar">
                                <button type="submit" name="respond_activation_request" class="btn">Denegar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($pending_requests as $request): ?>
                        <div class="req-card">
                            <div class="req-line">Solicitud de time</div>
                            <div class="req-line">Admin: <?php echo htmlspecialchars($request['admin_username'] ?: 'N/A'); ?></div>
                            <div class="req-line">Fecha: <?php echo htmlspecialchars($request['created_at']); ?></div>
                            <div class="req-line">Time asignado: <?php echo (int) $request['horas']; ?>h <?php echo (int) $request['minutos']; ?>m (<?php echo (int) $request['total_minutos']; ?> min)</div>
                            <?php if ($required_mission !== '' && (int) $current_user['mission_verified'] !== 1): ?>
                                <div class="warn">Debes verificar tu mision para correr este time.</div>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                    <button type="submit" name="run_time_request" class="btn">Iniciar Time</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Registro de tiempo</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Hora</th>
                            <th>Minuto</th>
                            <th>Dia</th>
                            <th>Mes</th>
                            <th>Anio</th>
                            <th>Tiempo Total</th>
                            <th>Creditos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($time_logs)): ?>
                            <tr><td colspan="8">Sin registros por ahora.</td></tr>
                        <?php else: ?>
                            <?php foreach ($time_logs as $log): ?>
                                <?php
                                $dt = new DateTime($log['created_at']);
                                $log_h = $dt->format('H');
                                $log_m = $dt->format('i');
                                $log_d = $dt->format('d');
                                $log_month = $dt->format('m');
                                $log_y = $dt->format('Y');
                                $mins = (int) $log['total_minutos'];
                                $mins_h = intdiv($mins, 60);
                                $mins_m = $mins % 60;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['admin_username'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log_h); ?></td>
                                    <td><?php echo htmlspecialchars($log_m); ?></td>
                                    <td><?php echo htmlspecialchars($log_d); ?></td>
                                    <td><?php echo htmlspecialchars($log_month); ?></td>
                                    <td><?php echo htmlspecialchars($log_y); ?></td>
                                    <td><?php echo $mins_h; ?>h <?php echo str_pad((string) $mins_m, 2, '0', STR_PAD_LEFT); ?>m</td>
                                    <td><?php echo (int) $log['creditos_otorgados']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <h2>Informacion adicional</h2>
            <div class="info-grid">
                <div class="cell">
                    <div class="cell-title">Fecha de registro</div>
                    <div class="cell-value"><?php echo date('d/m/Y', strtotime($current_user['created_at'])); ?></div>
                </div>
                <div class="cell">
                    <div class="cell-title">Creditos acumulados</div>
                    <div class="cell-value"><?php echo number_format((int) $current_user['creditos']); ?></div>
                </div>
                <div class="cell">
                    <div class="cell-title">Creditos actuales</div>
                    <div class="cell-value"><?php echo number_format((int) $current_user['creditos']); ?></div>
                </div>
                <div class="cell">
                    <div class="cell-title">Horas acumuladas</div>
                    <div class="cell-value"><?php echo number_format((int) $current_user['horas_acumuladas']); ?> h</div>
                </div>
                <div class="cell">
                    <div class="cell-title">Horas actuales</div>
                    <div class="cell-value"><?php echo number_format((int) $current_user['horas_actuales']); ?> h</div>
                </div>
                <div class="cell" style="grid-column: 1 / -1;">
                    <div class="cell-title">Experiencia</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                    <div class="cell-value" style="margin-top:8px;"><?php echo number_format((int) $current_user['experiencia']); ?> EXP</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function() {
            const totalNode = document.getElementById('profile-total-time');
            if (!totalNode) {
                return;
            }

            const baseMinutes = parseInt(totalNode.getAttribute('data-base-minutes') || '0', 10);
            const activeStart = parseInt(totalNode.getAttribute('data-active-start') || '0', 10);
            if (!Number.isFinite(baseMinutes) || !Number.isFinite(activeStart) || activeStart <= 0) {
                return;
            }

            const serverNow = <?php echo time(); ?>;
            const nowOffset = serverNow - Math.floor(Date.now() / 1000);

            function updateTotalTimeDisplay() {
                const currentNow = Math.floor(Date.now() / 1000) + nowOffset;
                const elapsedSeconds = Math.max(0, currentNow - activeStart);
                const totalMinutes = baseMinutes + Math.floor(elapsedSeconds / 60);
                const totalHours = Math.floor(totalMinutes / 60);
                const minutesLeft = totalMinutes % 60;
                totalNode.textContent = totalHours + 'h ' + String(minutesLeft).padStart(2, '0') + 'm (' + totalMinutes.toLocaleString('es-ES') + ' min)';
            }

            updateTotalTimeDisplay();
            setInterval(updateTotalTimeDisplay, 1000);
        })();
    </script>
</body>
</html>
