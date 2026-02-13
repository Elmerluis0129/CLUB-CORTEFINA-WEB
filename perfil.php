<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Load current user from database
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, username, habbo_username, ccf_code, verified FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $current_user = $result->fetch_assoc();
} else {
    echo "Usuario no encontrado.";
    exit;
}

$stmt->close();

// Load all users from database
$users_query = $conn->prepare("SELECT id, username, habbo_username, verified FROM users ORDER BY id DESC");
$users_query->execute();
$all_users = $users_query->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

if (!$current_user) {
    echo "Usuario no encontrado.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Club Cortefina</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px;
            text-align: center;
        }

        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .user-section {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .user-section::before {
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

        .user-section:hover::before {
            transform: translateX(100%);
        }

        .user-item {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 15px;
            border: 1px solid rgba(138, 43, 226, 0.4);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .user-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            flex: 1;
        }

        .user-buttons {
            display: flex;
            gap: 10px;
        }

        .user-buttons button {
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

        .user-buttons button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .container::before {
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

        .container:hover::before {
            transform: translateX(100%);
        }

        h2 {
            color: #ffd700;
            text-shadow: 0 0 10px #ffd700;
            margin-bottom: 40px;
            font-size: 1.5rem;
            text-align: center;
        }

        img {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 3px solid #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            margin: 0 auto 30px auto;
            display: block;
            object-fit: cover;
        }

        p {
            margin-bottom: 20px;
            font-size: 0.8rem;
            color: #8a2be2;
            text-align: center;
            line-height: 1.4;
        }

        .verified {
            color: #00ff00 !important;
            text-shadow: 0 0 5px #00ff00;
        }

        .not-verified {
            color: #ff6b6b !important;
            text-shadow: 0 0 5px #ff6b6b;
        }

        .ccf-code {
            background: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #8a2be2;
            font-family: monospace;
            color: #ffd700;
            margin: 10px 0;
            display: inline-block;
        }

        a {
            color: #8a2be2;
            text-decoration: none;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid #8a2be2;
            display: inline-block;
            margin: 10px 5px;
        }

        a:hover {
            background: #8a2be2;
            color: #fff;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
            transform: scale(1.05);
        }

        .verify-link {
            background: linear-gradient(45deg, #ffd700, #ffb347);
            color: #000;
            border: 2px solid #ffd700;
        }

        .verify-link:hover {
            background: linear-gradient(45deg, #ffb347, #ffd700);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
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

        /* Logout Button */
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

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                width: 90%;
                padding: 30px;
            }

            h2 {
                font-size: 1.2rem;
            }

            img {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="pixel-decoration top-left">■</div>
    <div class="pixel-decoration top-right">■</div>
    <div class="pixel-decoration bottom-left">■</div>
    <div class="pixel-decoration bottom-right">■</div>

    <div class="container">
        <div class="user-section">
            <h2>Perfil de <?php echo htmlspecialchars($current_user['username']); ?></h2>
            <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($current_user['habbo_username']); ?>" alt="Avatar de Habbo">
            <p>Nombre de Avatar: <?php echo htmlspecialchars($current_user['habbo_username']); ?></p>
            <p>Verificado: <?php echo $current_user['verified'] ? 'Sí' : 'No'; ?></p>
            <?php if (!$current_user['verified']): ?>
                <p>Tu código CCF: <strong><?php echo $current_user['ccf_code']; ?></strong></p>
                <p>Pon este código en tu motto de Habbo para verificar.</p>
                <a href="verify_habbo.php">Verificar Cuenta</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
            <br><br>
            <a href="index.php">Volver al Inicio</a>
        </div>


    </div>


</body>
</html>
