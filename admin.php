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

// Load events, photos, and games data
$events_file = 'events.json';
$photos_file = 'photos.json';
$games_file = 'games.json';

$events = file_exists($events_file) ? json_decode(file_get_contents($events_file), true) : [];
$photos = file_exists($photos_file) ? json_decode(file_get_contents($photos_file), true) : [];
$games = file_exists($games_file) ? json_decode(file_get_contents($games_file), true) : [];

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

    if (isset($_POST['update_user'])) {
        $user_id = $_POST['original_id'];
        $new_rank = $_POST['new_rank'];

        // Only allow rank changes from 6 to 7, or lower ranks
        $conn = getDBConnection();
        $current_user_stmt = $conn->prepare("SELECT rank FROM users WHERE id = ?");
        $current_user_stmt->bind_param("i", $user_id);
        $current_user_stmt->execute();
        $current_rank = $current_user_stmt->get_result()->fetch_assoc()['rank'];
        $current_user_stmt->close();

        // Allow rank changes only if current user has higher rank than target, or if changing from 6 to 7
        if ($user_rank > $current_rank || ($user_rank >= 7 && $current_rank == 6 && $new_rank == 7)) {
            $stmt = $conn->prepare("UPDATE users SET rank = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_rank, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();

        header("Location: admin.php");
        exit;
    }

    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['original_id'];

        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        header("Location: admin.php");
        exit;
    }

    // Redirect to avoid form resubmission
    header("Location: admin.php");
    exit;
}
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

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .admin-section {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            padding: 30px;
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
            margin-bottom: 25px;
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
            margin-top: 30px;
        }

        .item {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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
            margin-top: 20px;
        }

        .user-item {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(138, 43, 226, 0.4);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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
        }

        .user-edit-form {
            width: 100%;
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

        /* Responsive */
        @media (max-width: 768px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 100px 15px 30px 15px;
            }

            .title {
                font-size: 2rem;
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
            <h1 class="title">Panel Administrativo</h1>
            <p class="subtitle">Bienvenido, <?php echo htmlspecialchars($user['username']); ?> (Rango: <?php echo $user_rank_name; ?>)</p>
            <a href="index.php" class="back-link">← Volver al Inicio</a>
        </div>

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

            <!-- User Management (Founder only) -->
            <?php if ($user_rank >= 7): ?>
            <div class="admin-section user-management" style="grid-column: span 2;">
                <h3 class="section-title">Gestión de Usuarios</h3>
                <p style="text-align: center; color: #8a2be2; font-size: 0.7rem; margin-bottom: 30px;">Funciones avanzadas para el fundador</p>

                <!-- User List -->
                <div class="user-list">
                    <h4 style="color: #ffd700; margin-bottom: 20px;">Lista de Usuarios</h4>
                    <?php
                    $conn = getDBConnection();
                    $users_query = $conn->query("SELECT id, username, habbo_username, rank, verified, created_at FROM users ORDER BY rank DESC, created_at DESC");
                    $all_users = $users_query->fetch_all(MYSQLI_ASSOC);
                    $conn->close();
                    ?>

                    <?php foreach ($all_users as $usr): ?>
                    <div class="user-item">
                        <form method="POST" class="user-edit-form">
                            <div class="user-info">
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
                                            <?php if ($usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="delete_user" class="delete-btn" onclick="return confirm('¿Estás seguro de que quieres eliminar este usuario?')">Eliminar</button>
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
                                            <?php if ($usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="delete_user" class="delete-btn" onclick="return confirm('¿Estás seguro de que quieres eliminar este usuario?')">Eliminar</button>
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
                                            <option value="7" <?php echo $usr['rank'] == 7 ? 'selected' : ''; ?>>Dueño</option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="user-actions">
                                            <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                            <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                            <?php if ($usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="delete_user" class="delete-btn" onclick="return confirm('¿Estás seguro de que quieres eliminar este usuario?')">Eliminar</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Horas en Sala:</label>
                                    <div class="field-content">
                                        <input type="number" name="room_hours" value="<?php echo rand(0, 500); ?>" class="form-input-small" min="0">
                                        <div class="user-actions">
                                            <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                            <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                            <?php if ($usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                            <button type="submit" name="delete_user" class="delete-btn" onclick="return confirm('¿Estás seguro de que quieres eliminar este usuario?')">Eliminar</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-field">
                                    <label>Verificado:</label>
                                    <span style="color: <?php echo $usr['verified'] ? '#00ff00' : '#ff6b6b'; ?>;">
                                        <?php echo $usr['verified'] ? 'Sí' : 'No'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="user-actions">
                                <input type="hidden" name="original_id" value="<?php echo $usr['id']; ?>">
                                <button type="submit" name="update_user" class="update-btn">Actualizar</button>
                                <?php if ($usr['id'] != $_SESSION['user_id'] && $usr['rank'] < $user_rank): ?>
                                <button type="submit" name="delete_user" class="delete-btn" onclick="return confirm('¿Estás seguro de que quieres eliminar este usuario?')">Eliminar</button>
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
</body>
</html>
