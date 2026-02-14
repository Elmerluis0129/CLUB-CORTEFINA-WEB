<?php
session_start();
require_once 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: perfil.php");
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $errors = [];

    // Validation
    if (empty($username)) {
        $errors[] = "El nombre de usuario es obligatorio.";
    }

    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria.";
    }

    if (empty($errors)) {
        $conn = getDBConnection();
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_suspended TINYINT(1) DEFAULT 0");

        // Find user
        $stmt = $conn->prepare("SELECT id, password, is_suspended FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if ((int) $user['is_suspended'] === 1) {
                $errors[] = "Tu cuenta esta suspendida.";
            } elseif (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];

                // Redirect to profile
                header("Location: perfil.php");
                exit;
            } else {
                $errors[] = "Nombre de usuario o contraseña incorrectos.";
            }
        } else {
            $errors[] = "Nombre de usuario o contraseña incorrectos.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Club Cortefina</title>
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
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            padding: 50px;
            border-radius: 15px;
            box-shadow: 0 0 50px rgba(138, 43, 226, 0.3);
            width: 400px;
            text-align: center;
            position: relative;
            overflow: hidden;
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
            margin-bottom: 30px;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #8a2be2;
            font-size: 0.8rem;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #8a2be2;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.6rem;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .btn {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            border: none;
            padding: 15px 30px;
            color: #ffffff;
            cursor: pointer;
            border-radius: 8px;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
            margin: 10px;
        }

        .btn:hover {
            background: linear-gradient(45deg, #4b0082, #8a2be2);
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(138, 43, 226, 0.6);
        }

        .error {
            color: #ff6b6b;
            text-shadow: 0 0 5px #ff6b6b;
            margin-bottom: 20px;
            font-size: 0.6rem;
        }

        a {
            color: #8a2be2;
            text-decoration: none;
            font-size: 0.6rem;
            transition: all 0.3s ease;
        }

        a:hover {
            color: #ffd700;
            text-shadow: 0 0 5px #ffd700;
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
    </style>
</head>
<body>
    <div class="pixel-decoration top-left">■</div>
    <div class="pixel-decoration top-right">■</div>
    <div class="pixel-decoration bottom-left">■</div>
    <div class="pixel-decoration bottom-right">■</div>

    <div class="container">
        <h2>Iniciar Sesión</h2>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nombre de Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>

        <br>
        <a href="registro.php">¿No tienes cuenta? Regístrate</a>
        <br><br>
        <a href="index.php">Volver al Inicio</a>
    </div>
</body>
</html>
