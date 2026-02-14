<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT rank FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $user_rank = $user['rank'];
    } else {
        $user_rank = 1;
    }
    $stmt->close();
    $conn->close();
} else {
    $user_rank = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Cortefina - Habbo.es Nightclub</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Navigation Bar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1a0033, #000000);
            border-bottom: 3px solid #ffd700;
            padding: 15px 20px;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }

        .navbar-container {
            max-width: 1920px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-logo {
            font-size: 1.2rem;
            color: #ffd700;
            text-shadow: 0 0 10px #ffd700;
            font-weight: bold;
        }

        .navbar-menu {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .navbar-link.login, .navbar-link.register {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            color: #ffffff;
            border: 2px solid #8a2be2;
        }

        .navbar-link.login:hover, .navbar-link.register:hover {
            background: linear-gradient(45deg, #4b0082, #8a2be2);
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
        }

        .navbar-link {
            color: #8a2be2;
            text-decoration: none;
            font-size: 0.7rem;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .navbar-link:hover {
            background: #8a2be2;
            color: #fff;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
            transform: scale(1.05);
        }

        .navbar-link.admin {
            background: linear-gradient(45deg, #ffd700, #ffb347);
            color: #000;
            border: 2px solid #ffd700;
        }

        .navbar-link.admin:hover {
            background: linear-gradient(45deg, #ffb347, #ffd700);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        /* Dropdown Menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            color: #8a2be2;
            text-decoration: none;
            font-size: 0.7rem;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: transparent;
            cursor: pointer;
            font-family: 'Press Start 2P', monospace;
        }

        .dropdown-btn:hover {
            background: #8a2be2;
            color: #fff;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
            transform: scale(1.05);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background: linear-gradient(135deg, #1a0033, #000000);
            border: 2px solid #8a2be2;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
            z-index: 1000;
            min-width: 180px;
            margin-top: 5px;
        }

        .dropdown-content a {
            color: #8a2be2;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-size: 0.6rem;
            border-radius: 6px;
            margin: 2px;
            transition: all 0.3s ease;
        }

        .dropdown-content a:hover {
            background: #8a2be2;
            color: #fff;
            box-shadow: 0 0 10px rgba(138, 43, 226, 0.3);
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .navbar-link.admin {
            background: linear-gradient(45deg, #ffd700, #ffb347);
            color: #000;
            border: 2px solid #ffd700;
        }

        .navbar-link.admin:hover {
            background: linear-gradient(45deg, #ffb347, #ffd700);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        /* Adjust hero padding for fixed navbar */
        .hero {
            padding-top: 180px;
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
            max-width: 1920px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            padding: 120px 0 100px 0;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
        }

        .hero-content {
            max-width: 800px;
            width: 100%;
        }

        .banner {
            display: block;
            margin: 0 auto 60px auto;
            width: 700px;
            height: auto;
            filter: drop-shadow(0 0 20px #ffd700) drop-shadow(0 0 40px #8a2be2);
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { filter: drop-shadow(0 0 20px #ffd700) drop-shadow(0 0 40px #8a2be2); }
            to { filter: drop-shadow(0 0 30px #ffd700) drop-shadow(0 0 60px #8a2be2); }
        }

        @keyframes sparkle {
            0% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0); }
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #8a2be2;
            margin-bottom: 50px;
            opacity: 0.9;
        }

        .button-group {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            margin-bottom: 50px;
            flex-wrap: wrap;
        }

        .cta-button {
            background: linear-gradient(45deg, #ffd700, #8a2be2);
            border: none;
            padding: 25px 50px;
            font-size: 1.2rem;
            color: #000;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            margin: 10px;
        }

        .cta-button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.8);
        }

        .secondary-button {
            background: transparent;
            border: 2px solid #8a2be2;
            padding: 23px 48px;
            font-size: 1rem;
            color: #8a2be2;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .secondary-button:hover {
            background: #8a2be2;
            color: #fff;
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
        }

        .auth-section {
            border-top: 2px solid rgba(138, 43, 226, 0.3);
            padding-top: 50px;
            margin-top: 50px;
            width: 100%;
            max-width: 500px;
        }

        .auth-title {
            font-size: 1rem;
            color: #ffd700;
            margin-bottom: 30px;
            text-shadow: 0 0 5px #ffd700;
        }

        .auth-buttons {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
        }

        .auth-button {
            padding: 20px 40px;
            font-size: 0.8rem;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-family: 'Press Start 2P', monospace;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            min-width: 180px;
            text-align: center;
            margin: 10px;
        }

        .login-button {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            color: #ffffff;
            border: 2px solid #8a2be2;
        }

        .login-button:hover {
            background: linear-gradient(45deg, #4b0082, #8a2be2);
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(138, 43, 226, 0.6);
        }

        .register-button {
            background: linear-gradient(45deg, #ffd700, #ffb347);
            color: #000;
            border: 2px solid #ffd700;
        }

        .register-button:hover {
            background: linear-gradient(45deg, #ffb347, #ffd700);
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.6);
        }

        /* Radio Player in Hero */
        .hero-radio {
            margin-top: 60px;
            max-width: 600px;
            width: 100%;
        }

        .radio-player {
            background: linear-gradient(135deg, #1a0033, #0a0a2e);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 0 50px rgba(138, 43, 226, 0.3);
            text-align: center;
        }

        .radio-title {
            font-size: 1.5rem;
            color: #ffd700;
            text-shadow: 0 0 10px #ffd700;
            margin-bottom: 30px;
        }

        .equalizer {
            display: flex;
            justify-content: center;
            align-items: end;
            height: 80px;
            margin-bottom: 30px;
        }

        .bar {
            width: 8px;
            background: #8a2be2;
            margin: 0 2px;
            animation: equalize 1s ease-in-out infinite alternate;
            border-radius: 4px;
        }

        .bar:nth-child(1) { animation-delay: 0s; height: 15px; }
        .bar:nth-child(2) { animation-delay: 0.1s; height: 30px; }
        .bar:nth-child(3) { animation-delay: 0.2s; height: 45px; }
        .bar:nth-child(4) { animation-delay: 0.3s; height: 60px; }
        .bar:nth-child(5) { animation-delay: 0.4s; height: 75px; }
        .bar:nth-child(6) { animation-delay: 0.5s; height: 60px; }
        .bar:nth-child(7) { animation-delay: 0.6s; height: 45px; }
        .bar:nth-child(8) { animation-delay: 0.7s; height: 30px; }
        .bar:nth-child(9) { animation-delay: 0.8s; height: 15px; }

        @keyframes equalize {
            from { height: 10px; }
            to { height: 80px; }
        }

        .radio-button {
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
        }

        .radio-button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.8);
        }

        /* Events Section */
        .events {
            padding: 100px 0;
            text-align: center;
        }

        .section-title {
            font-size: 2.5rem;
            margin-bottom: 50px;
            color: #ffd700;
            text-shadow: 0 0 10px #ffd700;
        }

        .event-cards {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .event-card {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            padding: 30px;
            width: 300px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
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

        .event-card:hover::before {
            transform: translateX(100%);
        }

        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.7);
        }

        .event-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #8a2be2;
        }

        /* VIP Section */
        .vip {
            padding: 100px 0;
            text-align: center;
            background: linear-gradient(135deg, #330066, #000033);
        }

        .vip-badge {
            font-size: 4rem;
            color: #ffd700;
            margin-bottom: 30px;
            animation: shimmer 2s linear infinite;
        }

        @keyframes shimmer {
            0% { filter: brightness(1); }
            50% { filter: brightness(1.5); }
            100% { filter: brightness(1); }
        }

        /* Radio Section */
        .radio {
            padding: 100px 0;
            text-align: center;
        }

        .radio-player {
            background: linear-gradient(135deg, #1a0033, #0a0a2e);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 0 50px rgba(138, 43, 226, 0.3);
        }

        .equalizer {
            display: flex;
            justify-content: center;
            align-items: end;
            height: 100px;
            margin-bottom: 30px;
        }

        .bar {
            width: 10px;
            background: #8a2be2;
            margin: 0 2px;
            animation: equalize 1s ease-in-out infinite alternate;
        }

        .bar:nth-child(1) { animation-delay: 0s; height: 20px; }
        .bar:nth-child(2) { animation-delay: 0.1s; height: 40px; }
        .bar:nth-child(3) { animation-delay: 0.2s; height: 60px; }
        .bar:nth-child(4) { animation-delay: 0.3s; height: 80px; }
        .bar:nth-child(5) { animation-delay: 0.4s; height: 100px; }
        .bar:nth-child(6) { animation-delay: 0.5s; height: 80px; }
        .bar:nth-child(7) { animation-delay: 0.6s; height: 60px; }
        .bar:nth-child(8) { animation-delay: 0.7s; height: 40px; }
        .bar:nth-child(9) { animation-delay: 0.8s; height: 20px; }

        @keyframes equalize {
            from { height: 10px; }
            to { height: 100px; }
        }

        /* Founders Section */
        .founders {
            padding: 100px 0;
            text-align: center;
        }

        .founders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .founder-item {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }

        .founder-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
        }

        .founder-item img {
            margin-bottom: 10px;
        }

        .founder-item p {
            color: #ffd700;
            font-size: 0.8rem;
        }

        /* Gallery Section */
        .gallery {
            padding: 100px 0;
            text-align: center;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .gallery-item {
            background: linear-gradient(135deg, #2a0040, #1a1a2e);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .gallery-item:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
        }

        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        /* Footer */
        .footer {
            padding: 50px 0;
            text-align: center;
            border-top: 2px solid #8a2be2;
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: repeating-linear-gradient(90deg, #ffd700 0px, #ffd700 10px, #8a2be2 10px, #8a2be2 20px);
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
        }

        .social-icon {
            font-size: 2rem;
            color: #8a2be2;
            transition: all 0.3s ease;
        }

        .social-icon:hover {
            color: #ffd700;
            transform: scale(1.2);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .banner {
                font-size: 2rem;
            }

            .event-cards {
                flex-direction: column;
                align-items: center;
            }

            .cta-button, .secondary-button {
                display: block;
                margin: 10px auto;
                width: 200px;
            }
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
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-logo">Club Cortefina</div>
            <div class="navbar-menu">
                <a href="#events" class="navbar-link">Eventos</a>
                <a href="#gallery" class="navbar-link">Galería</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="dropdown-btn">Perfil ▼</button>
                        <div class="dropdown-content">
                            <a href="perfil.php">Mi Perfil</a>
                            <a href="logout.php">Cerrar Sesión</a>
                        </div>
                    </div>
                    
                    <?php if ($user_rank >= 5): ?>
                    <a href="time.php" class="navbar-link admin">Time</a>
                    <?php endif; ?>
                    <?php if ($user_rank >= 5): ?>
                    <a href="admin.php" class="navbar-link admin">Panel Admin</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="navbar-link login">Iniciar Sesión</a>
                    <a href="registro.php" class="navbar-link register">Registro</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="pixel-decoration top-left">■</div>
    <div class="pixel-decoration top-right">■</div>
    <div class="pixel-decoration bottom-left">■</div>
    <div class="pixel-decoration bottom-right">■</div>

    <div class="container">
        <!-- Hero Section -->
        <section class="hero">
            <img src="https://habbofont.net/font/habbo_ribbon/club+cortefina.gif" alt="Club Cortefina" class="banner">
            <a href="https://www.habbo.es/room/125384369" class="cta-button" onclick="confettiEffect()">Enter the Club</a>


            <div class="hero-radio">
                <div class="radio-player">
                    <h3 class="radio-title">Club Radio</h3>
                    <div class="equalizer">
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                    </div>
                    <button class="radio-button">Listen Live</button>
                </div>
            </div>
        </section>

        <!-- Events Section -->
        <section id="events" class="events">
            <h2 class="section-title">Events</h2>
            <div class="event-cards">
                <div class="event-card">
                    <div class="event-icon">🎧</div>
                    <h3>DJ Night</h3>
                    <p>Every Friday</p>
                </div>
                <div class="event-card">
                    <div class="event-icon">🎤</div>
                    <h3>Karaoke</h3>
                    <p>Saturday Nights</p>
                </div>
                <div class="event-card">
                    <div class="event-icon">💃</div>
                    <h3>Dance Party</h3>
                    <p>Weekly Specials</p>
                </div>
            </div>
        </section>

        <!-- VIP Section -->
        <section class="vip">
            <h2 class="section-title">VIP / HC Area</h2>
            <div class="vip-badge">👑</div>
            <p>Exclusive access to premium areas</p>
            <button class="cta-button">Become VIP</button>
        </section>

        <!-- Gallery Section -->
        <section id="gallery" class="gallery">
            <h2 class="section-title">Gallery</h2>
            <div class="gallery-grid">
                <div class="gallery-item">
                    <img src="https://via.placeholder.com/300x200/8a2be2/ffffff?text=Dance+Floor" alt="Dance Floor">
                </div>
                <div class="gallery-item">
                    <img src="https://via.placeholder.com/300x200/ffd700/000000?text=VIP+Area" alt="VIP Area">
                </div>
                <div class="gallery-item">
                    <img src="https://via.placeholder.com/300x200/ff00ff/ffffff?text=Bar" alt="Bar">
                </div>
                <div class="gallery-item">
                    <img src="https://via.placeholder.com/300x200/00ffff/000000?text=Events" alt="Events">
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="social-icons">
                <a href="#" class="social-icon">📘</a>
                <a href="#" class="social-icon">🐦</a>
                <a href="#" class="social-icon">🏨</a>
            </div>
            <p>&copy; 2023 Club Cortefina - Habbo.es</p>
        </footer>
    </div>

    <script>
        function confettiEffect() {
            // Simple confetti effect
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.background = ['#ffd700', '#8a2be2', '#ff00ff'][Math.floor(Math.random() * 3)];
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-10px';
                confetti.style.animation = 'fall 3s linear forwards';
                document.body.appendChild(confetti);

                setTimeout(() => confetti.remove(), 3000);
            }
        }

        // Add fall animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
