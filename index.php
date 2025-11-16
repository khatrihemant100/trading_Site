<?php
session_start();
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
            background: url('img/hero.head.jpg') center/cover no-repeat;
            color: white;
            padding: 150px 0;
            position: relative;
            overflow: hidden;
            min-height: 600px;
            display: flex;
            align-items: center;
            filter: brightness(1);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.4) 0%, rgba(15, 23, 42, 0.3) 100%);
            backdrop-filter: blur(0.5px);
            filter: none;
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            filter: none;
        }
        
        .hero-content {
            position: relative;
            z-index: 3;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.8);
            filter: brightness(1.1);
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            color: #ffffff;
            text-shadow: 3px 3px 10px rgba(0, 0, 0, 0.9), 0 0 20px rgba(0, 0, 0, 0.5);
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 2.5rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.8);
            font-weight: 400;
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
            background-color: var(--primary);
            color: white;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4), 0 0 20px rgba(16, 185, 129, 0.3);
            text-shadow: none;
        }
        
        .hero-cta:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5), 0 0 30px rgba(16, 185, 129, 0.4);
            color: white;
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
                        <a class="nav-link" href="course/course.php">COURSE</a>
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
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Master Your Trading Journey</h1>
                <p class="hero-subtitle">Professional trading journal, portfolio management, and community platform for traders</p>
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
    
    <!-- AI Chat Widget -->
    <?php include 'includes/ai-chat-widget.php'; ?>
</body>
</html>