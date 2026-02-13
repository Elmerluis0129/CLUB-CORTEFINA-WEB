<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrar Sesión - Club Cortefina</title>
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
            width: 500px;
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

        p {
            margin-bottom: 40px;
            font-size: 0.8rem;
            color: #8a2be2;
        }

        .btn {
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            border: none;
            padding: 15px 30px;
            font-size: 0.9rem;
            color: #000;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            font-family: 'Press Start 2P', monospace;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.8);
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
        <h2>Cerrar Sesión</h2>
        <p>Cerraste sesión correctamente.</p>
        <a href="index.php" class="btn">Volver a Inicio</a>
    </div>
</body>
</html>
