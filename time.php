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
$users_query = $conn->prepare("SELECT id, username, habbo_username, rank, verified, created_at, avatar FROM users WHERE rank IN (5, 6, 7) ORDER BY rank DESC, created_at DESC");
$users_query->execute();
$all_users = $users_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Load all users for the time system
$all_users_query = $conn->prepare("SELECT id, username, habbo_username, verified, rank, total_time FROM users ORDER BY id DESC");
$all_users_query->execute();
$all_registered_users = $all_users_query->get_result()->fetch_all(MYSQLI_ASSOC);
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 120px 20px 50px 20px;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .time-section {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
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
            padding: 25px;
            margin-bottom: 15px;
            border: 1px solid rgba(138, 43, 226, 0.4);
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            align-items: start;
            gap: 15px;
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
            justify-content: center;
        }

        .user-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            flex: 1;
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

        .user-row:hover {
            background: rgba(138, 43, 226, 0.1);
        }

        .user-cell {
            display: flex;
            align-items: center;
            position: relative;
        }

        .user-cell img {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .user-cell div {
            margin-left: 80px;
        }

        .time-cell {
            text-align: center;
        }

        .action-cell {
            text-align: center;
        }

        .action-cell button {
            margin: 0 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .time-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 100px 15px 30px 15px;
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

            .user-cell {
                flex-direction: column;
                text-align: center;
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

    <div class="container">
        <div class="header">
            <h1 class="title">Panel Time</h1>
            <p class="subtitle">Bienvenido, <?php echo htmlspecialchars($user['username']); ?> (Rango: <?php echo $user_rank_name; ?>)</p>
            <a href="index.php" class="back-link">← Volver al Inicio</a>
            <div class="time-buttons">
                <button id="activate-time-btn">Activar Tiempo</button>
                <button id="pause-time-btn">Pausa</button>
            </div>
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
                    <th>Usuario con Rango</th>
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
                    $today_minutes = 0; // Placeholder for today_time, assuming it's 0 since column doesn't exist
                    $today_seconds = $today_minutes * 60;
                    ?>
                    <tr class="user-row">
                        <td class="user-cell">
                            <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=<?php echo urlencode($usr['habbo_username']); ?>" alt="Foto de Perfil" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #8a2be2; object-fit: cover;">
                            <div style="display: flex; flex-direction: column; margin-left: 15px;">
                                <span style="color: #ffd700; font-size: 0.8rem;"><?php echo htmlspecialchars($usr['username']); ?> (<?php echo $usr['verified'] ? 'Verificado' : 'No Verificado'; ?>) - Rango: <?php echo isset($rank_names[$usr['rank']]) ? $rank_names[$usr['rank']] : 'Usuario'; ?></span>
                            </div>
                        </td>
                        <td class="time-cell">
                            <span id="time-<?php echo $usr['id']; ?>" style="color: #8a2be2; font-size: 0.6rem;"></span>
                        </td>
                        <td class="time-cell">
                            <span style="color: #8a2be2; font-size: 0.6rem;">Horas: 0 Minutos: 0 Segundos: 0</span>
                        </td>
                        <td class="action-cell">
                            <button class="activate-btn" data-user="<?php echo $usr['id']; ?>">Activar Tiempo</button>
                            <button class="pause-btn" data-user="<?php echo $usr['id']; ?>">Pausa</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Time display update
        let userTimes = {};
        let pausedUsers = new Set();
        let isTimeActivated = false;

        // Initialize user times
        <?php foreach ($all_registered_users as $usr): ?>
            userTimes[<?php echo $usr['id']; ?>] = <?php echo isset($usr['total_time']) ? $usr['total_time'] * 60 : 0; ?>;
        <?php endforeach; ?>

        function updateTimeDisplays() {
            if (!isTimeActivated) return;
            for (const userId in userTimes) {
                if (!pausedUsers.has(userId)) {
                    userTimes[userId]++;
                    const totalSeconds = userTimes[userId];
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    const timeSpan = document.getElementById('time-' + userId);
                    if (timeSpan) {
                        timeSpan.textContent = `Horas: ${hours} Minutos: ${minutes} Segundos: ${seconds}`;
                    }
                }
            }
        }

        setInterval(updateTimeDisplays, 1000);

        // Header buttons functionality
        document.getElementById('activate-time-btn').addEventListener('click', function() {
            if (this.textContent === 'Activar Tiempo') {
                this.textContent = 'Cerrar Tiempo';
                isTimeActivated = true;
                alert('Tiempo activado');
            } else {
                this.textContent = 'Activar Tiempo';
                isTimeActivated = false;
                alert('Tiempo cerrado');
            }
        });

        document.getElementById('pause-time-btn').addEventListener('click', function() {
            alert('Pausa activada');
        });

        let timers = {};

        document.querySelectorAll('.activate-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user');
                const timerDisplay = document.getElementById('timer-' + userId);

                if (this.textContent === 'Activar Tiempo') {
                    this.textContent = 'Cerrar Tiempo';
                    startTimer(userId, timerDisplay);
                    // Update total time after 1 hour
                    setTimeout(() => {
                        updateTotalTime(userId);
                    }, 3600000); // 1 hour in milliseconds
                } else {
                    this.textContent = 'Activar Tiempo';
                    stopTimer(userId);
                }
            });
        });

        document.querySelectorAll('.pause-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user');
                pausedUsers.add(userId);
                alert(`Pausa activada para usuario ${userId}`);
                stopTimer(userId);
            });
        });

        function startTimer(userId, display) {
            timers[userId] = { start: Date.now(), display: display };
            updateDisplay(userId);
        }

        function stopTimer(userId) {
            if (timers[userId]) {
                delete timers[userId];
                const display = document.getElementById('timer-' + userId);
                display.textContent = '';
            }
        }

        function updateDisplay(userId) {
            if (timers[userId]) {
                const elapsed = Date.now() - timers[userId].start;
                const remaining = 3600000 - elapsed; // 1 hour
                if (remaining > 0) {
                    const minutes = Math.floor(remaining / 60000);
                    const seconds = Math.floor((remaining % 60000) / 1000);
                    timers[userId].display.textContent = `Tiempo restante: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                    setTimeout(() => updateDisplay(userId), 1000);
                } else {
                    timers[userId].display.textContent = 'Tiempo completado';
                    stopTimer(userId);
                }
            }
        }

        function updateTotalTime(userId) {
            fetch('update_time.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Total time updated for user ' + userId);
                } else {
                    console.error('Failed to update total time');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
