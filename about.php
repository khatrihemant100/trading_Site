<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - NpLTrader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-hover: #334155;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --primary: #10b981;
            --primary-dark: #059669;
        }
        body {
            background: var(--dark-bg);
            color: var(--text-primary);
        }
        .about-hero {
            padding: 90px 0 70px;
            background: radial-gradient(circle at top right, rgba(16,185,129,0.18), transparent 45%),
                        radial-gradient(circle at bottom left, rgba(59,130,246,0.14), transparent 40%),
                        var(--dark-bg);
            border-bottom: 1px solid var(--border-color);
        }
        .hero-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid var(--border-color);
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 16px;
        }
        .hero-title {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
        }
        .hero-subtitle {
            color: var(--text-secondary);
            max-width: 760px;
            font-size: 1.05rem;
        }
        .section {
            padding: 70px 0;
        }
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .section-subtitle {
            color: var(--text-secondary);
            margin-bottom: 28px;
        }
        .about-card {
            background: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            height: 100%;
            transition: all 0.25s ease;
        }
        .about-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
        }
        .about-card .icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(16,185,129,0.15);
            color: var(--primary);
            margin-bottom: 14px;
            font-size: 1.2rem;
        }
        .about-card p {
            color: var(--text-secondary);
            margin: 0;
        }
        .stats-box {
            background: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stats-box h3 {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 4px;
        }
        .stats-box p {
            color: var(--text-secondary);
            margin: 0;
        }
        .cta-block {
            background: linear-gradient(135deg, rgba(16,185,129,0.22), rgba(5,150,105,0.16));
            border: 1px solid rgba(16,185,129,0.45);
            border-radius: 14px;
            padding: 32px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include __DIR__.'/includes/navbar.php'; ?>

    <section class="about-hero">
        <div class="container">
            <span class="hero-badge">About NpLTrader</span>
            <h1 class="hero-title">A Practical Trading Platform Built for Nepali Traders</h1>
            <p class="hero-subtitle">
                NpLTrader blends structured education, real-world trade journaling, portfolio tools, and trader
                psychology guidance so beginners and experienced traders can build consistency with discipline.
            </p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <h2 class="section-title">What Makes Us Different</h2>
            <p class="section-subtitle">Everything is designed to turn knowledge into execution.</p>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="about-card p-4">
                        <div class="icon"><i class="fas fa-book-open"></i></div>
                        <h5>Structured Learning</h5>
                        <p>Step-by-step roadmap from beginner basics to advanced technical analysis.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="about-card p-4">
                        <div class="icon"><i class="fas fa-chart-line"></i></div>
                        <h5>Execution Focus</h5>
                        <p>Trade planning, risk control, and journaling to improve real performance.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="about-card p-4">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <h5>Community Support</h5>
                        <p>Learn with like-minded traders, share setups, and grow with accountability.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section pt-0">
        <div class="container">
            <h2 class="section-title">Our Mission</h2>
            <p class="section-subtitle">
                To make high-quality, practical trading education accessible in a clear and locally relevant format.
            </p>
            <div class="row g-3">
                <div class="col-md-3 col-6"><div class="stats-box"><h3>10K+</h3><p>Community Reach</p></div></div>
                <div class="col-md-3 col-6"><div class="stats-box"><h3>12+</h3><p>Core Modules</p></div></div>
                <div class="col-md-3 col-6"><div class="stats-box"><h3>100%</h3><p>Practical Focus</p></div></div>
                <div class="col-md-3 col-6"><div class="stats-box"><h3>24/7</h3><p>Learning Access</p></div></div>
            </div>
        </div>
    </section>

    <section class="section pt-0 pb-5">
        <div class="container">
            <div class="cta-block">
                <h3 class="mb-2">Ready to Learn and Trade Better?</h3>
                <p class="text-light-emphasis mb-4">Start with our guided course path and build your trading system.</p>
                <a href="course/course.php" class="btn btn-success px-4 me-2">Explore Courses</a>
                <a href="register.php" class="btn btn-outline-light px-4">Create Free Account</a>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
