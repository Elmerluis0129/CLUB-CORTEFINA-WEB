<?php
session_start();
require_once 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: perfil.php");
    exit;
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $habbo_username = isset($_POST['habbo_username']) ? trim($_POST['habbo_username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    $errors = [];

    // Validation
    if (empty($username)) {
        $errors[] = "El nombre de usuario es obligatorio.";
    }

    if (empty($email)) {
        $errors[] = "El correo electrónico es obligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El correo electrónico no es válido.";
    }

    if (empty($habbo_username)) {
        $errors[] = "El nombre de usuario de Habbo es obligatorio.";
    }

    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Las contraseñas no coinciden.";
    }

    if (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres.";
    }

    if (empty($errors)) {
        $conn = getDBConnection();

        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "El nombre de usuario ya está en uso.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $errors[] = "El correo electrónico ya está en uso.";
            } else {
                // Generate CCF code
                $ccf_code = 'CCF-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . '-' . rand(1, 999);

                // Create new user
                $stmt = $conn->prepare("INSERT INTO users (username, email, habbo_username, password, ccf_code, verified) VALUES (?, ?, ?, ?, ?, 0)");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->bind_param("sssss", $username, $email, $habbo_username, $hashed_password, $ccf_code);

                if ($stmt->execute()) {
                    // Redirect to login page with success message
                    header("Location: login.php?success=1");
                    exit;
                } else {
                    $errors[] = "Error al registrar el usuario.";
                }
            }
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
    <title>Registro - Club Cortefina</title>
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
            width: 450px;
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
            font-size: 0.6rem;
        }

        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #8a2be2;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.5rem;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .btn {
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            border: none;
            padding: 15px 30px;
            color: #000;
            cursor: pointer;
            border-radius: 8px;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
            margin: 10px;
        }

        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.8);
        }

        .error {
            color: #ff6b6b;
            text-shadow: 0 0 5px #ff6b6b;
            margin-bottom: 20px;
            font-size: 0.5rem;
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
        <h2>Registro</h2>

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
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="habbo_username">Nombre de Usuario de Habbo:</label>
                <input type="text" id="habbo_username" name="habbo_username" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn">Registrarse</button>
        </form>

        <br>
        <a href="login.php">¿Ya tienes cuenta? Inicia Sesión</a>
        <br><br>
        <a href="index.php">Volver al Inicio</a>
    </div>
</body>
</html>
