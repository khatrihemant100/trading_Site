<?php
session_start();
require_once __DIR__.'/includes/check_course_status.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Trading Nepal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-hover: #334155;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
        }
        
        .navbar {
            background-color: var(--dark-card) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding: 0.5rem 0;
            min-height: 60px;
        }
        
        .navbar .container {
            display: flex;
            align-items: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        .navbar-brand {
            color: var(--primary) !important;
            font-size: 1.4rem;
            margin-right: 2rem;
            margin-left: -0.5rem;
            padding: 0.5rem 0;
        }
        
        .navbar-collapse {
            flex-grow: 1;
            display: flex !important;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .navbar-nav.mx-auto {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .navbar-nav .nav-item {
            margin: 0 0.4rem;
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem !important;
            font-size: 0.95rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .navbar .d-flex.align-items-center {
            margin-left: auto;
            margin-right: -0.5rem;
            padding-left: 1rem;
        }
        
        @media (max-width: 991px) {
            .navbar .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .navbar-brand {
                margin-right: 1rem;
                margin-left: 0;
            }
            
            .navbar-nav.mx-auto {
                position: static;
                transform: none;
                margin: 0.75rem 0 !important;
                width: 100%;
            }
            
            .navbar-nav .nav-item {
                margin: 0.2rem 0;
            }
            
            .navbar-collapse {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .navbar .d-flex.align-items-center {
                margin-left: 0;
                margin-right: 0;
                padding-left: 0;
                width: 100%;
                justify-content: flex-end;
                margin-top: 0.75rem;
            }
        }
        
        .nav-link {
            color: var(--text-secondary) !important;
            transition: all 0.3s;
            border-radius: 6px;
        }
        
        .nav-link:hover {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .navbar-toggler {
            border-color: var(--border-color);
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28148, 163, 184, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .hero-section {
            background: url('img/hero.head.png') center/cover no-repeat;
            color: white;
            padding: 150px 0;
            position: relative;
            overflow: hidden;
            min-height: 600px;
            display: flex;
            align-items: center;
        }
        
        /* Dark blur overlay for better text readability - lighter */
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.45) 0%, rgba(15, 23, 42, 0.35) 50%, rgba(15, 23, 42, 0.45) 100%);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 1;
        }
        
        /* Additional dark overlay - lighter */
        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.25);
            z-index: 1;
        }
        
        /* Glowing gradient lighting effects */
        .hero-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.3) 0%, transparent 70%);
            filter: blur(80px);
            z-index: 2;
            animation: glowMove 8s ease-in-out infinite;
        }
        
        .hero-glow-1 {
            top: -200px;
            right: -200px;
            animation-delay: 0s;
        }
        
        .hero-glow-2 {
            bottom: -200px;
            left: -200px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 70%);
            animation-delay: 2s;
        }
        
        @keyframes glowMove {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.6; }
            50% { transform: translate(50px, 50px) scale(1.2); opacity: 0.8; }
        }
        
        /* Moving stock chart lines */
        .chart-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.4), transparent);
            z-index: 2;
            opacity: 0.3;
        }
        
        .chart-line-1 {
            top: 20%;
            animation: chartMove1 12s linear infinite;
        }
        
        .chart-line-2 {
            top: 50%;
            animation: chartMove2 15s linear infinite;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.3), transparent);
        }
        
        .chart-line-3 {
            top: 80%;
            animation: chartMove3 18s linear infinite;
            background: linear-gradient(90deg, transparent, rgba(245, 158, 11, 0.3), transparent);
        }
        
        @keyframes chartMove1 {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        @keyframes chartMove2 {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        
        @keyframes chartMove3 {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Floating financial numbers */
        .floating-number {
            position: absolute;
            color: rgba(16, 185, 129, 0.6);
            font-size: 1.2rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            z-index: 2;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
            pointer-events: none;
        }
        
        .floating-number.positive {
            color: rgba(16, 185, 129, 0.6);
        }
        
        .floating-number.negative {
            color: rgba(239, 68, 68, 0.6);
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }
        
        /* Futuristic data effects - grid pattern */
        .data-grid {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(rgba(16, 185, 129, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 2;
            opacity: 0.4;
            animation: gridPulse 4s ease-in-out infinite;
        }
        
        @keyframes gridPulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.5; }
        }
        
        /* Data points animation */
        .data-point {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(16, 185, 129, 0.6);
            border-radius: 50%;
            z-index: 2;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.8);
            animation: dataPointPulse 3s ease-in-out infinite;
        }
        
        @keyframes dataPointPulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.5); opacity: 1; }
        }
        
        .hero-content {
            position: relative;
            z-index: 10;
            text-shadow: 2px 2px 12px rgba(0, 0, 0, 0.9), 0 0 30px rgba(0, 0, 0, 0.6);
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 30%, #10b981 60%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: none;
            letter-spacing: -0.5px;
            min-height: 4.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            filter: drop-shadow(0 0 30px rgba(251, 191, 36, 0.4));
            animation: titleGradient 5s ease infinite;
            background-size: 200% 200%;
        }
        
        @keyframes titleGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .typing-cursor {
            display: inline-block;
            width: 3px;
            height: 3.5rem;
            background: linear-gradient(180deg, #fbbf24 0%, #10b981 100%);
            animation: blink 1s infinite, cursorGlow 2s ease-in-out infinite;
            margin-left: 5px;
            box-shadow: 0 0 15px rgba(251, 191, 36, 0.8), 0 0 25px rgba(16, 185, 129, 0.6);
        }
        
        @keyframes cursorGlow {
            0%, 100% { 
                box-shadow: 0 0 15px rgba(251, 191, 36, 0.8), 0 0 25px rgba(16, 185, 129, 0.6);
            }
            50% { 
                box-shadow: 0 0 25px rgba(251, 191, 36, 1), 0 0 40px rgba(16, 185, 129, 0.9);
            }
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
        
        #typing-text {
            display: inline-block;
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.98);
            margin-bottom: 2.5rem;
            max-width: 850px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 2px 2px 12px rgba(0, 0, 0, 0.95), 0 0 20px rgba(0, 0, 0, 0.5);
            font-weight: 400;
            animation: subtitleFadeIn 1.2s ease-out;
            line-height: 1.8;
        }
        
        .subtitle-highlight {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            position: relative;
            display: inline-block;
            animation: highlightPulse 2s ease-in-out infinite;
            filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.6));
        }
        
        .subtitle-highlight::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 50%, #10b981 100%);
            opacity: 0.8;
            animation: underlineGlow 2s ease-in-out infinite;
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(251, 191, 36, 0.6);
        }
        
        @keyframes subtitleFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes highlightPulse {
            0%, 100% { 
                filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.6));
            }
            50% { 
                filter: drop-shadow(0 0 15px rgba(251, 191, 36, 0.9)) drop-shadow(0 0 25px rgba(16, 185, 129, 0.6));
            }
        }
        
        @keyframes underlineGlow {
            0%, 100% { 
                opacity: 0.6;
                box-shadow: 0 0 10px rgba(251, 191, 36, 0.6);
            }
            50% { 
                opacity: 1;
                box-shadow: 0 0 20px rgba(251, 191, 36, 0.9), 0 0 30px rgba(16, 185, 129, 0.6);
            }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .hero-section {
                padding: 100px 0;
                min-height: 500px;
            }
            
            .features-title {
                font-size: 2rem;
            }
        }
        
        .hero-cta {
            background-color: #10b981;
            color: #fbbf24;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            margin-top: 1.5rem;
            letter-spacing: 0.5px;
        }
        
        .hero-cta:hover {
            background-color: #059669;
            color: #f59e0b;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4), 0 0 30px rgba(16, 185, 129, 0.5);
        }
        
        .hero-cta:active {
            transform: translateY(-1px) scale(1.02);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
        }
        
        .features-section {
            background-color: var(--dark-bg);
            padding: 100px 0;
        }
        
        .features-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 60px;
        }
        
        .feature-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.2);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: var(--primary);
        }
        
        .feature-card h3 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .feature-card p {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin: 0;
        }
        
        .motivation-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s;
            color: var(--text-primary);
        }
        
        .motivation-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .btn-light {
            background-color: white;
            color: var(--dark-bg);
        }
        .btn-light:hover {
            background-color: var(--text-secondary);
            color: white;
        }
        .btn-outline-light {
            border-color: white;
            color: white;
        }
        .btn-outline-light:hover {
            background-color: white;
            color: var(--dark-bg);
        }
        .text-primary {
            color: var(--primary) !important;
        }
        .text-muted {
            color: var(--text-secondary) !important;
        }
        
        .community-section {
            --dark-bg: #0f172a;
            padding: 80px 0;
            color: white;
        }
        
        .community-pill {
            background-color: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .trader-card {
            background: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            height: 100%;
        }
        
        .trader-card h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.5rem;
        }
        
        .platform-btn {
            width: 100%;
            height: 80px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--dark-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .platform-btn:hover {
            border-color: var(--primary);
            background: var(--dark-hover);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            color: var(--primary);
        }
        
        .platform-logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: white;
        }
        
        .nepali-platforms .platform-logo {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }
        
        .foreign-platforms .platform-logo {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        }
        
        .social-media-section {
            --dark-bg: #0f172a;
            padding: 80px 0;
            color: white;
        }
        
        .social-media-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .social-media-section .subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 50px;
        }
        
        .social-link {
            text-decoration: none;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.3s;
            padding: 20px;
        }
        
        .social-link:hover {
            transform: translateY(-5px);
            color: white;
        }
        
        .social-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }
        
        .social-link:hover .social-icon {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.1);
        }
        
        .social-link span {
            font-weight: 600;
            font-size: 0.95rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-chart-line text-primary me-2"></i>NpLTrader
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="blog.php">BLOG</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="course/course.php" onclick="return handleCourseClick(event)">COURSE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">ABOUT US</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">CONTACT</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard/dashboard.php">DASHBOARD</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['username'])): 
                        // Profile image fetch गर्ने
                        require_once __DIR__.'/config/database.php';
                        $profile_stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $profile_stmt->execute([$_SESSION['user_id']]);
                        $profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
                        $profile_image = $profile_data['profile_image'] ?? null;
                    ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" style="color: var(--primary) !important; padding: 0;">
                                <?php if (!empty($profile_image) && file_exists($profile_image)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; border: 2px solid var(--primary);">
                                <?php else: ?>
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 8px; font-weight: bold;">
                                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="dashboard/dashboard.php"><i class="fas fa-th-large me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="user/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2">Sign In</a>
                        <a href="register.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <!-- Glowing gradient lighting effects -->
        <div class="hero-glow hero-glow-1"></div>
        <div class="hero-glow hero-glow-2"></div>
        
        <!-- Futuristic data grid -->
        <div class="data-grid"></div>
        
        <!-- Moving stock chart lines -->
        <div class="chart-line chart-line-1"></div>
        <div class="chart-line chart-line-2"></div>
        <div class="chart-line chart-line-3"></div>
        
        <!-- Floating financial numbers (will be generated by JS) -->
        <div id="floatingNumbers"></div>
        
        <!-- Data points (will be generated by JS) -->
        <div id="dataPoints"></div>
        
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">
                    <span id="typing-text"></span><span class="typing-cursor">|</span>
                </h1>
                <p class="hero-subtitle">
                    <span class="subtitle-highlight">Transform</span> your trading with AI-powered insights, 
                    <span class="subtitle-highlight">analyze</span> every trade like a pro, and 
                    <span class="subtitle-highlight">connect</span> with Nepal's elite trading community
                </p>
                <a href="register.php" class="hero-cta">Start Trading Smarter</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="features-title">Everything You Need to Succeed</h2>
            <div class="row g-4">
                <!-- Feature 1: Trading Journal -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Trading Journal</h3>
                        <p>Document every trade with detailed analysis</p>
                    </div>
                </div>
                
                <!-- Feature 2: Portfolio Tracking -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Portfolio Tracking</h3>
                        <p>Monitor performance and P/L in real-time</p>
                    </div>
                </div>
                
                <!-- Feature 3: Community -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Community</h3>
                        <p>Share insights and learn from other traders</p>
                    </div>
                </div>
                
                <!-- Feature 4: Psychology Tools -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3>Psychology Tools</h3>
                        <p>Build discipline with habits and motivation</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Success Stories -->
    <section class="py-5" style="background-color: var(--dark-card);">
        <div class="container">
            <h2 class="text-center mb-5" style="color: var(--text-primary); font-size: 2.5rem; font-weight: 700;">Success Stories</h2>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0" style="background-color: var(--dark-bg); border: 1px solid var(--border-color) !important;">
                        <div class="card-body p-4" style="color: var(--text-primary);">
                            <blockquote class="blockquote mb-0">
                                <p class="lead font-italic" style="font-size: 1.2rem;">"After taking the beginner's course, I turned my initial investment of रु 50,000 into रु 2,50,000 in just 8 months!"</p>
                                <footer class="blockquote-footer mt-3" style="color: var(--text-secondary);">
                                    <strong style="color: var(--text-primary);">Ramesh Shrestha</strong>, Kathmandu
                                </footer>
                            </blockquote>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Traders Community Section -->
    <section class="community-section">
        <div class="container">
            <div class="text-center mb-5">
                <span class="community-pill">TRADERS COMMUNITY</span>
                <h2 class="display-5 fw-bold mb-4">NpLTrader Community</h2>
                <p class="lead mb-0" style="max-width: 800px; margin: 0 auto; opacity: 0.95;">
                    Become a part of our <strong>Exclusive Traders' Community</strong> and don't miss out on all the good stuff - from exciting giveaways to special access to classes and member-only discounts. It's the perfect space to learn, grow, and get rewarded while trading with like-minded people.
                </p>
            </div>
            
            <div class="row g-4 mt-4">
                <!-- For Nepali Traders -->
                <div class="col-lg-6">
                    <div class="trader-card nepali-platforms">
                        <h4><i class="fas fa-flag me-2"></i>For Nepali Traders</h4>
                        <div class="d-grid gap-2">
                            <a href="#" class="platform-btn">
                                <div class="platform-logo" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                    N
                                </div>
                                <span>Nepal Stock Exchange (NEPSE)</span>
                            </a>
                            <a href="#" class="platform-btn">
                                <div class="platform-logo" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <span>Meroshare</span>
                            </a>
                            <a href="#" class="platform-btn">
                                <div class="platform-logo" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                    <i class="fas fa-building"></i>
                                </div>
                                <span>Brokerage Firms</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- For Foreign Traders -->
                <div class="col-lg-6">
                    <div class="trader-card foreign-platforms">
                        <h4><i class="fas fa-globe me-2"></i>For Foreign Traders & NRI's</h4>
                        <div class="d-grid gap-2">
                            <a href="#" class="platform-btn">
                                <div class="platform-logo" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                                    EX
                                </div>
                                <span>Exness</span>
                            </a>
                            <a href="#" class="platform-btn">
                                <div class="platform-logo" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                    W
                                </div>
                                <span>Winpro FX</span>
                            </a>
                            <a href="#" class="platform-btn">
                                <div class="platform-logo" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                                    P
                                </div>
                                <span>Propfirmo</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5" style="background-color: var(--dark-bg);">
        <div class="container text-center">
            <h2 class="mb-4" style="color: var(--text-primary);">Ready to Start Your Trading Journey?</h2>
            <p class="lead mb-5" style="color: var(--text-secondary);">Join our community of 10,000+ successful traders in Nepal</p>
            <a href="register.php" class="btn btn-primary btn-lg px-5">Get Started Today</a>
        </div>
    </section>

    <!-- Social Media Section (Full Dark Mode, No Green BG) -->
    <section class="social-media-section" style="background-color: var(--dark-card); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 48px 0;">
        <div class="container">
            <div class="text-center">
                <h2 class="mb-3" style="color: var(--primary); text-shadow: 1px 1px 8px rgba(0,0,0,0.7);">Find NpLTrader On</h2>
                <p class="subtitle" style="color: var(--text-secondary); max-width: 640px; margin: 0 auto;">
                    NpLTrader has a strong community of traders across all popular social media platforms.<br>
                    Join us to stay updated with latest trading tips, market insights, and exclusive content.
                </p>
                <div class="row g-4 justify-content-center mt-4">
                    <div class="col-lg-2 col-md-4 col-6">
                        <a href="#" class="social-link d-flex flex-column align-items-center text-decoration-none" target="_blank">
                            <div class="social-icon mb-2" style="background: #181a21; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:#FF0000; box-shadow: 0 3px 18px 0 rgba(0,0,0,0.7);">
                                <i class="fab fa-youtube"></i>
                            </div>
                            <span style="color: var(--text-primary);">YouTube</span>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <a href="#" class="social-link d-flex flex-column align-items-center text-decoration-none" target="_blank">
                            <div class="social-icon mb-2" style="background: #182136; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:#229ED9; box-shadow: 0 3px 18px 0 rgba(0,0,0,0.7);">
                                <i class="fab fa-telegram"></i>
                            </div>
                            <span style="color: var(--text-primary);">Telegram</span>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <a href="#" class="social-link d-flex flex-column align-items-center text-decoration-none" target="_blank">
                            <div class="social-icon mb-2" style="background: #21151c; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:#E4405F; box-shadow: 0 3px 18px 0 rgba(0,0,0,0.7);">
                                <i class="fab fa-instagram"></i>
                            </div>
                            <span style="color: var(--text-primary);">Instagram</span>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <a href="#" class="social-link d-flex flex-column align-items-center text-decoration-none" target="_blank">
                            <div class="social-icon mb-2" style="background: #17202b; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:#1877F3; box-shadow: 0 3px 18px 0 rgba(0,0,0,0.7);">
                                <i class="fab fa-facebook"></i>
                            </div>
                            <span style="color: var(--text-primary);">Facebook</span>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <a href="#" class="social-link d-flex flex-column align-items-center text-decoration-none" target="_blank">
                            <div class="social-icon mb-2" style="background: #15242e; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:#1DA1F2; box-shadow: 0 3px 18px 0 rgba(0,0,0,0.7);">
                                <i class="fab fa-twitter"></i>
                            </div>
                            <span style="color: var(--text-primary);">Twitter</span>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <a href="#" class="social-link d-flex flex-column align-items-center text-decoration-none" target="_blank">
                            <div class="social-icon mb-2" style="background: #0e2134; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:#0A66C2; box-shadow: 0 3px 18px 0 rgba(0,0,0,0.7);">
                                <i class="fab fa-linkedin"></i>
                            </div>
                            <span style="color: var(--text-primary);">LinkedIn</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4" style="background-color: var(--dark-card); border-top: 1px solid var(--border-color);">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 style="color: var(--text-primary);"><i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>NpLTrader</h5>
                    <p style="color: var(--text-secondary);">The premier stock trading education platform in Nepal</p>
                </div>
                <div class="col-md-3">
                    <h5 style="color: var(--text-primary);">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-decoration-none" style="color: var(--text-secondary);">Home</a></li>
                        <li><a href="course/course.php" class="text-decoration-none" style="color: var(--text-secondary);">Courses</a></li>
                        <li><a href="blog.php" class="text-decoration-none" style="color: var(--text-secondary);">Blog</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5 style="color: var(--text-primary);">Contact</h5>
                    <ul class="list-unstyled" style="color: var(--text-secondary);">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Kathmandu, Nepal</li>
                        <li><i class="fas fa-phone me-2"></i> +977 9841XXXXXX</li>
                        <li><i class="fas fa-envelope me-2"></i> info@npltrader.com</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4" style="border-color: var(--border-color);">
            <div class="text-center" style="color: var(--text-secondary);">
                <small>© 2023 NpLTrader. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Typewriter animation for hero title
        const typingTexts = [
            'Trade Smarter.',
            'Track Better.',
            'Grow Faster.'
        ];
        
        let currentTextIndex = 0;
        let currentCharIndex = 0;
        let isDeleting = false;
        let typingSpeed = 100;
        
        function typeWriter() {
            const element = document.getElementById('typing-text');
            if (!element) return;
            
            const currentText = typingTexts[currentTextIndex];
            
            if (isDeleting) {
                // Delete text
                element.textContent = currentText.substring(0, currentCharIndex - 1);
                currentCharIndex--;
                typingSpeed = 50; // Faster when deleting
                
                if (currentCharIndex === 0) {
                    isDeleting = false;
                    currentTextIndex = (currentTextIndex + 1) % typingTexts.length;
                    typingSpeed = 500; // Pause before typing next
                }
            } else {
                // Type text
                element.textContent = currentText.substring(0, currentCharIndex + 1);
                currentCharIndex++;
                typingSpeed = 100; // Normal typing speed
                
                if (currentCharIndex === currentText.length) {
                    isDeleting = true;
                    typingSpeed = 2000; // Pause before deleting
                }
            }
            
            setTimeout(typeWriter, typingSpeed);
        }
        
        // Start typewriter effect when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay before starting
            setTimeout(() => {
                typeWriter();
            }, 500);
        });
        
        // Generate floating financial numbers
        function createFloatingNumbers() {
            const container = document.getElementById('floatingNumbers');
            const numbers = ['+2.45%', '+$1,234', '+5.67%', '-1.23%', '+$890', '+3.21%', '+$567', '-0.45%', '+$1,890', '+4.56%'];
            const isPositive = [true, true, true, false, true, true, true, false, true, true];
            
            for (let i = 0; i < 15; i++) {
                const number = document.createElement('div');
                number.className = `floating-number ${isPositive[i % isPositive.length] ? 'positive' : 'negative'}`;
                number.textContent = numbers[i % numbers.length];
                
                // Random position
                const left = Math.random() * 100;
                const top = Math.random() * 100;
                const delay = Math.random() * 5;
                const duration = 8 + Math.random() * 4;
                
                number.style.left = left + '%';
                number.style.top = top + '%';
                number.style.animation = `floatNumber ${duration}s ease-in-out infinite`;
                number.style.animationDelay = delay + 's';
                
                container.appendChild(number);
            }
        }
        
        // Generate data points
        function createDataPoints() {
            const container = document.getElementById('dataPoints');
            
            for (let i = 0; i < 20; i++) {
                const point = document.createElement('div');
                point.className = 'data-point';
                
                const left = Math.random() * 100;
                const top = Math.random() * 100;
                const delay = Math.random() * 3;
                
                point.style.left = left + '%';
                point.style.top = top + '%';
                point.style.animationDelay = delay + 's';
                
                container.appendChild(point);
            }
        }
        
        // Floating number animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes floatNumber {
                0% {
                    transform: translateY(0) translateX(0) scale(1);
                    opacity: 0;
                }
                10% {
                    opacity: 0.8;
                }
                90% {
                    opacity: 0.8;
                }
                100% {
                    transform: translateY(-100vh) translateX(${Math.random() * 200 - 100}px) scale(0.5);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            createFloatingNumbers();
            createDataPoints();
            
            // Regenerate numbers every 15 seconds for continuous effect
            setInterval(() => {
                const container = document.getElementById('floatingNumbers');
                container.innerHTML = '';
                createFloatingNumbers();
            }, 15000);
        });
    </script>
    
    <?php include __DIR__.'/includes/coming_soon_notification.php'; ?>
    
    <script>
        <?php if (!isCoursesEnabled()): ?>
        function handleCourseClick(event) {
            event.preventDefault();
            showComingSoon();
            return false;
        }
        <?php endif; ?>
    </script>
</body>
</html>