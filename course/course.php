<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Courses - NpLTrader</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
        }
        
        .navbar {
            background-color: var(--dark-card) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary) !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: var(--text-secondary) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s;
            padding: 8px 16px;
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            margin-bottom: 50px;
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .course-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.2);
        }
        
        .card-body {
            background-color: var(--dark-card);
            color: var(--text-primary);
        }
        
        .course-img {
            height: 200px;
            object-fit: cover;
        }
        
        .course-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .course-title {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        
        .card-text {
            color: var(--text-secondary);
        }
        
        .course-duration {
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .course-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .btn-enroll {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
            width: 100%;
            border: none;
        }
        
        .btn-enroll:hover {
            background-color: var(--primary-dark);
            color: white;
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
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
            color: var(--text-primary);
            font-weight: 700;
        }
        
        .courses-section {
            background-color: var(--dark-bg);
            padding: 50px 0;
        }
        
        footer {
            background-color: var(--dark-card) !important;
            border-top: 1px solid var(--border-color);
        }
        
        footer h5 {
            color: var(--text-primary);
        }
        
        footer p, footer li {
            color: var(--text-secondary);
        }
        
        footer a {
            color: var(--text-secondary);
            text-decoration: none;
        }
        
        footer a:hover {
            color: var(--primary);
        }
        
        footer hr {
            border-color: var(--border-color);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-chart-line me-2"></i>NpLTrader
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../blog.php">BLOG</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="course.php">COURSE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../about.php">ABOUT US</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact.php">CONTACT</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">DASHBOARD</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['username'])): 
                        // Profile image fetch गर्ने
                        require_once __DIR__.'/../config/database.php';
                        $profile_stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $profile_stmt->execute([$_SESSION['user_id']]);
                        $profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
                        $profile_image = $profile_data['profile_image'] ?? null;
                    ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" style="color: var(--primary) !important; padding: 0;">
                                <?php if (!empty($profile_image) && file_exists(__DIR__.'/../' . $profile_image)): ?>
                                    <img src="../<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; border: 2px solid var(--primary);">
                                <?php else: ?>
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 8px; font-weight: bold;">
                                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="../dashboard.php"><i class="fas fa-th-large me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="../user/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="../register.php" class="btn btn-outline-primary me-2">Sign Up</a>
                        <a href="../login.php" class="btn btn-primary">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">हाम्रो पाठ्यक्रमहरू</h1>
            <p class="hero-subtitle">शेयर बजारको ज्ञान बढाउनका लागि विशेषज्ञहरूद्वारा डिजाइन गरिएका पाठ्यक्रमहरू</p>
        </div>
    </section>

    <!-- Courses Section -->
    <section class="courses-section">
        <div class="container">
            <h2 class="section-title">हाम्रा लोकप्रिय पाठ्यक्रमहरू</h2>
            
            <div class="row">
                <!-- Course 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card course-card">
                        <img src="https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60" class="card-img-top course-img" alt="Stock Market Basics">
                        <span class="course-badge">बेसिक</span>
                        <div class="card-body">
                            <h5 class="card-title course-title">शेयर बजारको बेसिक ज्ञान</h5>
                            <p class="card-text">शुरुआतीहरूका लागि शेयर बजारको मूलभूत ज्ञान, कसरी सुरु गर्ने, र बजार विश्लेषणको आधारभूत तरिकाहरू।</p>
                            <p class="course-duration"><i class="far fa-clock me-2"></i>4 हप्ता</p>
                            <p class="course-price">रु. 2,500</p>
                            <a href="#" class="btn btn-enroll">BUY NOW</a>
                        </div>
                    </div>
                </div>
                
                <!-- Course 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card course-card">
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60" class="card-img-top course-img" alt="Technical Analysis">
                        <span class="course-badge">इन्टरमिडिएट</span>
                        <div class="card-body">
                            <h5 class="card-title course-title">टेक्निकल विश्लेषण</h5>
                            <p class="card-text">चार्ट, प्याटर्न, र टेक्निकल इन्डिकेटरहरूको प्रयोग गरेर बजारको प्रवृत्ति विश्लेषण गर्ने तरिका सिक्नुहोस्।</p>
                            <p class="course-duration"><i class="far fa-clock me-2"></i>6 हप्ता</p>
                            <p class="course-price">रु. 4,500</p>
                            <a href="#" class="btn btn-enroll">BUY NOW</a>
                        </div>
                    </div>
                </div>
                
                <!-- Course 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card course-card">
                        <img src="https://images.unsplash.com/photo-1535320903710-d993d3d77d29?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60" class="card-img-top course-img" alt="Fundamental Analysis">
                        <span class="course-badge">एड्भान्स्ड</span>
                        <div class="card-body">
                            <h5 class="card-title course-title">फन्डामेन्टल विश्लेषण</h5>
                            <p class="card-text">कम्पनीहरूको वित्तीय विवरण, उद्योग विश्लेषण, र मूल्यांकन तरिकाहरूबारे गहन अध्ययन।</p>
                            <p class="course-duration"><i class="far fa-clock me-2"></i>8 हप्ता</p>
                            <p class="course-price">रु. 6,500</p>
                            <a href="#" class="btn btn-enroll">BUY NOW</a>
                        </div>
                    </div>
                </div>
                
                <!-- Course 4 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card course-card">
                        <img src="https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60" class="card-img-top course-img" alt="Trading Strategies">
                        <span class="course-badge">इन्टरमिडिएट</span>
                        <div class="card-body">
                            <h5 class="card-title course-title">ट्रेडिंग रणनीतिहरू</h5>
                            <p class="card-text">विभिन्न ट्रेडिंग शैलीहरू, जस्तै स्विंग ट्रेडिंग, डे ट्रेडिंग, र पोजिसन ट्रेडिंगबारे व्यावहारिक ज्ञान।</p>
                            <p class="course-duration"><i class="far fa-clock me-2"></i>5 हप्ता</p>
                            <p class="course-price">रु. 3,800</p>
                            <a href="#" class="btn btn-enroll">BUY NOW</a>
                        </div>
                    </div>
                </div>
                
                <!-- Course 5 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card course-card">
                        <img src="https://images.unsplash.com/photo-1553729459-efe14ef6055d?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60" class="card-img-top course-img" alt="Portfolio Management">
                        <span class="course-badge">एड्भान्स्ड</span>
                        <div class="card-body">
                            <h5 class="card-title course-title">पोर्टफोलियो व्यवस्थापन</h5>
                            <p class="card-text">जोखिम व्यवस्थापन, विविधीकरण, र दीर्घकालीन निवेश रणनीतिहरूबारे सिक्नुहोस्।</p>
                            <p class="course-duration"><i class="far fa-clock me-2"></i>7 हप्ता</p>
                            <p class="course-price">रु. 5,500</p>
                            <a href="#" class="btn btn-enroll">BUY NOW</a>
                        </div>
                    </div>
                </div>
                
                <!-- Course 6 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card course-card">
                        <img src="https://images.unsplash.com/photo-1621761191319-c6fb62004040?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60" class="card-img-top course-img" alt="Options Trading">
                        <span class="course-badge">एड्भान्स्ड</span>
                        <div class="card-body">
                            <h5 class="card-title course-title">अप्सन ट्रेडिंग</h5>
                            <p class="card-text">कल र पुट अप्सनहरू, स्ट्रेटेजीहरू, र जोखिम व्यवस्थापनबारे विस्तृत ज्ञान प्राप्त गर्नुहोस्।</p>
                            <p class="course-duration"><i class="far fa-clock me-2"></i>6 हप्ता</p>
                            <p class="course-price">रु. 7,000</p>
                            <a href="#" class="btn btn-enroll">BUY NOW</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-chart-line me-2"></i>NpLTrader</h5>
                    <p>शेयर बजारको ज्ञान बढाउने उत्कृष्ट माध्यम</p>
                </div>
                <div class="col-md-4">
                    <h5>तिब्र लिंकहरू</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white">हाम्रो बारेमा</a></li>
                        <li><a href="#" class="text-white">पाठ्यक्रमहरू</a></li>
                        <li><a href="#" class="text-white">सम्पर्क गर्नुहोस्</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>सम्पर्क</h5>
                    <p><i class="fas fa-envelope me-2"></i> info@npltrader.com</p>
                    <p><i class="fas fa-phone me-2"></i> +977 9841XXXXXX</p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2023 NpLTrader. सर्वाधिकार सुरक्षित।</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>