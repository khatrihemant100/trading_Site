<<<<<<< HEAD
=======
<?php session_start(); ?>
>>>>>>> d01e1cd (update)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Document</title>
</head>
<body>

=======
    <title>NplTrader - Zero-to-Advanced Trading Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #00C896;
            --secondary: #0F172A;
            --text-primary: #eaf4ff;
            --text-secondary: #9fb1c9;
            --glass: rgba(255, 255, 255, 0.06);
            --glass-border: rgba(255, 255, 255, 0.12);
            --radius: 16px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }
        body {
            margin: 0;
            color: var(--text-primary);
            font-family: "Inter", "Poppins", "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #0b1225 0%, #0F172A 45%, #1e1b4b 100%);
            min-height: 100vh;
        }
        .page-wrap {
            max-width: 1320px;
            margin: 0 auto;
            padding: 22px;
        }
        .glass {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .hero {
            position: relative;
            overflow: hidden;
            padding: 44px;
            margin-bottom: 18px;
        }
        .hero h1 {
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 800;
            margin-bottom: 10px;
        }
        .hero p {
            color: var(--text-secondary);
            max-width: 720px;
            font-size: 1.05rem;
        }
        .hero-cta .btn {
            border-radius: 12px;
            padding: 11px 20px;
            font-weight: 700;
            transition: all 0.25s ease;
        }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #012014;
        }
        .btn-primary:hover {
            background: #00ddb1;
            border-color: #00ddb1;
            transform: translateY(-1px) scale(1.02);
            box-shadow: 0 0 24px rgba(0, 200, 150, 0.42);
            color: #012014;
        }
        .btn-outline-light:hover {
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }
        .chart-bg {
            position: absolute;
            right: -40px;
            top: 10px;
            width: 420px;
            height: 220px;
            opacity: 0.35;
            pointer-events: none;
        }
        .chart-line {
            fill: none;
            stroke: #00C896;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-dasharray: 500;
            stroke-dashoffset: 500;
            animation: draw 6s linear infinite;
        }
        @keyframes draw {
            0% { stroke-dashoffset: 500; }
            50% { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: -500; }
        }
        .layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 18px;
        }
        .sidebar {
            position: sticky;
            top: 115px;
            height: fit-content;
        }
        .side-block {
            padding: 14px;
            margin-bottom: 12px;
        }
        .side-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        .search-input {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
        }
        .search-input:focus {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 200, 150, 0.2);
        }
        .level-nav .nav-link {
            width: 100%;
            text-align: left;
            border-radius: 12px;
            border: 1px solid transparent;
            color: var(--text-secondary);
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.02);
            transition: all 0.2s ease;
        }
        .level-nav .nav-link:hover {
            border-color: rgba(0, 200, 150, 0.35);
            color: #dffff5;
            transform: translateX(2px);
        }
        .level-nav .nav-link.active {
            background: rgba(0, 200, 150, 0.18);
            border-color: rgba(0, 200, 150, 0.4);
            color: #dffff5;
        }
        .chip-wrap { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip {
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            padding: 5px 11px;
            font-size: 0.82rem;
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .chip.active, .chip:hover {
            border-color: rgba(0, 200, 150, 0.5);
            color: #e8fff8;
            background: rgba(0, 200, 150, 0.15);
        }
        .progress {
            height: 10px;
            background: rgba(255, 255, 255, 0.08);
        }
        .progress-bar {
            background: linear-gradient(90deg, #00C896, #33f0c0);
        }
        .main-grid {
            display: grid;
            gap: 14px;
        }
        .level-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .level-card {
            padding: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .level-card:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 0 22px rgba(0, 200, 150, 0.16), var(--shadow);
        }
        .level-card p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.92rem;
        }
        .q-panel { padding: 18px; }
        .q-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; }
        .q-sub { color: var(--text-secondary); margin-bottom: 14px; }
        .accordion-item {
            border: 1px solid var(--glass-border);
            background: rgba(15, 23, 42, 0.55);
            border-radius: 12px !important;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .accordion-item.hide-by-filter { display: none; }
        .accordion-button {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-primary);
            font-weight: 600;
            box-shadow: none !important;
        }
        .accordion-button:not(.collapsed) {
            background: rgba(0, 200, 150, 0.14);
            color: #d8fff3;
        }
        .accordion-button::after { filter: invert(1) grayscale(1); }
        .accordion-body { color: var(--text-secondary); line-height: 1.75; }
        .q-icon {
            color: var(--primary);
            margin-right: 9px;
            width: 18px;
            text-align: center;
        }
        .checklist li {
            list-style: none;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        .checklist li i {
            color: var(--primary);
            margin-right: 8px;
        }
        .tip-card { padding: 16px; }
        .tip-card h6 { color: #d8fff3; margin-bottom: 8px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .stat {
            padding: 14px;
            text-align: center;
            border-radius: 14px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.03);
        }
        .stat h4 { margin: 0; font-size: 1.2rem; }
        .stat small { color: var(--text-secondary); }
        .video-wrap iframe {
            width: 100%;
            min-height: 300px;
            border: none;
            border-radius: 12px;
        }
        .empty-note { color: #facc15; font-size: 0.88rem; display: none; margin-top: 8px; }
        @media (max-width: 1024px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .level-cards, .stats-grid { grid-template-columns: 1fr; }
            .hero { padding: 26px; }
            .chart-bg { display: none; }
        }
    </style>
</head>
<body>
<?php include __DIR__.'/includes/navbar.php'; ?>
<div class="page-wrap">
    <section class="hero glass">
        <svg class="chart-bg" viewBox="0 0 500 250" aria-hidden="true">
            <path class="chart-line" d="M10,220 C90,190 120,80 180,120 C230,160 290,90 330,110 C375,130 420,60 490,30"></path>
        </svg>
        <h1>Zero-to-Advanced Trading Guide</h1>
        <p>NplTrader को premium learning hub: beginner-friendly, chart-based, practical, and consistent strategy building for serious traders.</p>
        <div class="hero-cta d-flex gap-2 mt-3">
            <a href="register.php" class="btn btn-primary">Start Learning</a>
            <a href="course/course.php" class="btn btn-outline-light">Explore Course</a>
        </div>
    </section>

    <div class="layout">
        <aside class="sidebar">
            <div class="side-block glass">
                <div class="side-title">Search</div>
                <input id="qaSearch" class="form-control search-input" type="text" placeholder="SL, structure, liquidity...">
                <div id="emptyResult" class="empty-note">No matching result</div>
            </div>
            <div class="side-block glass">
                <div class="side-title">Levels Navigation</div>
                <div class="nav flex-column level-nav" id="levelTabs" role="tablist">
                    <button class="nav-link active" id="level1-tab" data-bs-toggle="pill" data-bs-target="#level1" type="button">Level 1 - Beginner</button>
                    <button class="nav-link" id="level2-tab" data-bs-toggle="pill" data-bs-target="#level2" type="button">Level 2 - Intermediate</button>
                    <button class="nav-link" id="level3-tab" data-bs-toggle="pill" data-bs-target="#level3" type="button">Level 3 - Advanced</button>
                </div>
            </div>
            <div class="side-block glass">
                <div class="side-title">Progress Tracker</div>
                <div class="progress mb-2">
                    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                </div>
                <small id="progressText" class="text-secondary">0% Completed</small>
            </div>
            <div class="side-block glass">
                <div class="side-title">Quick Filters</div>
                <div class="chip-wrap">
                    <button class="chip active" data-filter="all" type="button">All</button>
                    <button class="chip" data-filter="level1" type="button">Level 1</button>
                    <button class="chip" data-filter="level2" type="button">Level 2</button>
                    <button class="chip" data-filter="level3" type="button">Level 3</button>
                    <button class="chip" data-filter="risk" type="button">Risk</button>
                    <button class="chip" data-filter="entry" type="button">Entry</button>
                    <button class="chip" data-filter="structure" type="button">Structure</button>
                </div>
            </div>
        </aside>

        <main class="main-grid">
            <section class="level-cards">
                <article class="level-card glass">
                    <h5>Level 1</h5>
                    <p>Forex basics, candlesticks, market foundations.</p>
                </article>
                <article class="level-card glass">
                    <h5>Level 2</h5>
                    <p>Structure, entries, risk/SL-TP and system habits.</p>
                </article>
                <article class="level-card glass">
                    <h5>Level 3</h5>
                    <p>Liquidity, confluence, advanced execution process.</p>
                </article>
            </section>

            <section class="tab-content">
                <div class="tab-pane fade show active" id="level1">
                    <div class="q-panel glass">
                        <h3 class="q-title">Level 1 Q&amp;A - Foundations</h3>
                        <p class="q-sub">0 knowledge बाट सुरु गर्ने learners का लागि।</p>
                        <div class="accordion" id="accLevel1">
                            <div class="accordion-item qa-item" data-tags="level1 basics structure">
                                <h2 class="accordion-header"><button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#l1q1"><i class="fas fa-chart-line q-icon"></i>Forex market भनेको के हो?</button></h2>
                                <div id="l1q1" class="accordion-collapse collapse show" data-bs-parent="#accLevel1"><div class="accordion-body">Forex भनेको currencies exchange हुने global market हो। पहिलो step मा pair, pip, spread, lot, bid/ask clear हुनु जरूरी छ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level1 candlestick entry">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l1q2"><i class="fas fa-candle-holder q-icon"></i>Candlestick किन महत्त्वपूर्ण?</button></h2>
                                <div id="l1q2" class="accordion-collapse collapse" data-bs-parent="#accLevel1"><div class="accordion-body">OHLC data बाट buyer/seller pressure बुझिन्छ। pattern नाम मात्र होइन, support/resistance context मा पढ्दा मात्र real value आउँछ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level1 risk">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l1q3"><i class="fas fa-shield-alt q-icon"></i>SL किन पहिलो दिनदेखि?</button></h2>
                                <div id="l1q3" class="accordion-collapse collapse" data-bs-parent="#accLevel1"><div class="accordion-body">SL बिना trading गर्नु capital destruction shortcut हो। capital बचाउनु भनेकै trader को पहिलो काम हो।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level1 basics entry">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l1q4"><i class="fas fa-coins q-icon"></i>Pip, lot, spread सजिलै कसरी बुझ्ने?</button></h2>
                                <div id="l1q4" class="accordion-collapse collapse" data-bs-parent="#accLevel1"><div class="accordion-body">Pip price movement को unit हो, lot position size हो, spread broker cost हो। Beginner ले पहिले सानो lot मा trade simulation गरेर per-pip value बुझ्नुपर्छ। यसले risk calculation clear बनाउँछ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level1 structure trend">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l1q5"><i class="fas fa-route q-icon"></i>Trend कसरी सुरुमा पहिचान गर्ने?</button></h2>
                                <div id="l1q5" class="accordion-collapse collapse" data-bs-parent="#accLevel1"><div class="accordion-body">Higher highs र higher lows देखिए uptrend, lower highs र lower lows देखिए downtrend। यदि दुवै clear छैन भने market ranging छ। Trend clear नभएसम्म forced trade नगर्नु beginner को best discipline हो।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level1 psychology discipline">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l1q6"><i class="fas fa-brain q-icon"></i>0 knowledge हुँदा सबैभन्दा common गल्ती के हो?</button></h2>
                                <div id="l1q6" class="accordion-collapse collapse" data-bs-parent="#accLevel1"><div class="accordion-body">YouTube setup copy गरेर context नहेरी entry लिनु सबैभन्दा common गल्ती हो। सही तरीका: एक strategy छान्ने, demo मा 20-30 setups टेस्ट गर्ने, अनि मात्र real market मा सानो risk बाट सुरु गर्ने।</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="level2">
                    <div class="q-panel glass">
                        <h3 class="q-title">Level 2 Q&amp;A - Execution</h3>
                        <p class="q-sub">Market structure बाट systematic trading तिर।</p>
                        <div class="accordion" id="accLevel2">
                            <div class="accordion-item qa-item" data-tags="level2 structure">
                                <h2 class="accordion-header"><button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#l2q1"><i class="fas fa-project-diagram q-icon"></i>Market structure किन जरुरी?</button></h2>
                                <div id="l2q1" class="accordion-collapse collapse show" data-bs-parent="#accLevel2"><div class="accordion-body">HH-HL वा LH-LL ले बजारको direction देखाउँछ। direction clear भएपछि entry quality धेरै improve हुन्छ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level2 entry risk">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l2q2"><i class="fas fa-crosshairs q-icon"></i>Entry confirmation कसरी?</button></h2>
                                <div id="l2q2" class="accordion-collapse collapse" data-bs-parent="#accLevel2"><div class="accordion-body">Trend + zone + confirmation candle + risk control = quality setup। एउटै indicator cross मा trade नलिनुहोस्।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level2 risk">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l2q3"><i class="fas fa-balance-scale q-icon"></i>RRR किन game changer?</button></h2>
                                <div id="l2q3" class="accordion-collapse collapse" data-bs-parent="#accLevel2"><div class="accordion-body">1:2 वा 1:3 RRR अपनाउँदा win-rate low भए पनि long-term profitable रहन सकिन्छ, यदि discipline maintain गरियो भने।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level2 entry pullback">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l2q4"><i class="fas fa-arrow-trend-up q-icon"></i>Pullback entry र breakout entry मध्ये कुन राम्रो?</button></h2>
                                <div id="l2q4" class="accordion-collapse collapse" data-bs-parent="#accLevel2"><div class="accordion-body">Breakout entry momentum capture गर्न राम्रो हुन्छ तर fake breakout risk हुन्छ। Pullback entry मा better price र tighter SL पाइन्छ तर miss हुने chance हुन्छ। Best approach: market condition अनुसार दुवै setup को rule-based checklist बनाउने।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level2 risk psychology">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l2q5"><i class="fas fa-user-shield q-icon"></i>लगातार 2 loss पछि risk कसरी control गर्ने?</button></h2>
                                <div id="l2q5" class="accordion-collapse collapse" data-bs-parent="#accLevel2"><div class="accordion-body">तुरुन्त lot increase नगर्नुहोस्। 2 loss पछि trading pause, journal review, अनि risk half गरेर अर्को trade लिनु best practice हो। लक्ष्य revenge होइन, process reset हो।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level2 structure confirmation">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l2q6"><i class="fas fa-layer-group q-icon"></i>Confluence भनेको practical मा के-के combine गर्ने?</button></h2>
                                <div id="l2q6" class="accordion-collapse collapse" data-bs-parent="#accLevel2"><div class="accordion-body">कम्तिमा 3 confirmations combine गर्नुहोस्: market structure direction, key zone reaction, confirmation candle। चाहियो भने volume वा RSI divergence add गर्न सकिन्छ, तर chart clutter नहोस् भन्ने ध्यान दिनुहोस्।</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="level3">
                    <div class="q-panel glass">
                        <h3 class="q-title">Level 3 Q&amp;A - Advanced Process</h3>
                        <p class="q-sub">Liquidity, confluence र professional routine।</p>
                        <div class="accordion" id="accLevel3">
                            <div class="accordion-item qa-item" data-tags="level3 liquidity">
                                <h2 class="accordion-header"><button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#l3q1"><i class="fas fa-wave-square q-icon"></i>Liquidity sweep भनेको के?</button></h2>
                                <div id="l3q1" class="accordion-collapse collapse show" data-bs-parent="#accLevel3"><div class="accordion-body">Price ले previous highs/lows sweep गरेर stops trigger गर्छ अनि मुख्य direction मा फर्किन्छ। यो false breakout जस्तो देखिन सक्छ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level3 entry structure">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l3q2"><i class="fas fa-chess q-icon"></i>Advanced confluence setup कसरी?</button></h2>
                                <div id="l3q2" class="accordion-collapse collapse" data-bs-parent="#accLevel3"><div class="accordion-body">HTF bias + sweep + zone reaction + LTF confirmation सँग setup बनाउँदा random entry घट्छ र confidence बढ्छ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level3 liquidity risk">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l3q3"><i class="fas fa-water q-icon"></i>Liquidity trap बाट बच्न के हेर्ने?</button></h2>
                                <div id="l3q3" class="accordion-collapse collapse" data-bs-parent="#accLevel3"><div class="accordion-body">High/low sweep पछि immediate strong rejection candle, displacement move, अनि market structure shift confirm गर्नुपर्छ। sweep देखिने बित्तिकै blind entry नलिनुहोस्; confirmation बिना risk high हुन्छ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level3 strategy journal">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l3q4"><i class="fas fa-clipboard-check q-icon"></i>Advanced trader को daily routine कस्तो हुने?</button></h2>
                                <div id="l3q4" class="accordion-collapse collapse" data-bs-parent="#accLevel3"><div class="accordion-body">Pre-market मा bias र key levels plan गर्ने, session मा A+ setup मात्र execute गर्ने, अनि दिनको अन्त्यमा journal update गर्ने। Routine consistent हुँदा emotion कम हुन्छ र decision quality stable हुन्छ।</div></div>
                            </div>
                            <div class="accordion-item qa-item" data-tags="level3 performance review">
                                <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#l3q5"><i class="fas fa-chart-pie q-icon"></i>Performance सुधार्न weekly review कसरी गर्ने?</button></h2>
                                <div id="l3q5" class="accordion-collapse collapse" data-bs-parent="#accLevel3"><div class="accordion-body">हप्ताको trades लाई 3 भागमा divide गर्नुहोस्: best trades, avoidable losses, rule-break trades। त्यसपछि win-rate भन्दा पहिले rule-follow score track गर्नुहोस्। सुधारको fastest path यही review process हो।</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="glass p-3">
                <h5 class="mb-3">Quick Practice Checklist</h5>
                <ul class="checklist p-0 m-0">
                    <li><i class="fas fa-check-circle"></i>Trend identify on 3 charts</li>
                    <li><i class="fas fa-check-circle"></i>Mark support/resistance zones</li>
                    <li><i class="fas fa-check-circle"></i>Find one fake breakout example</li>
                    <li><i class="fas fa-check-circle"></i>Create daily pre-trade checklist</li>
                </ul>
            </section>

            <section class="tip-card glass">
                <h6>Today's Trading Tip</h6>
                <p class="mb-0 text-secondary">यदि setup clear छैन भने no-trade is also a profitable decision. Capital बचाउने habit ले future growth बनाउँछ।</p>
            </section>

            <section class="glass p-3">
                <h5 class="mb-3">Mini Dashboard (Demo)</h5>
                <div class="stats-grid">
                    <div class="stat">
                        <h4 class="text-success">+4.8%</h4>
                        <small>Weekly P/L</small>
                    </div>
                    <div class="stat">
                        <h4>63%</h4>
                        <small>Win Rate</small>
                    </div>
                    <div class="stat">
                        <h4 class="text-danger">-1.2%</h4>
                        <small>Max Drawdown</small>
                    </div>
                </div>
            </section>

            <section class="glass p-3 video-wrap">
                <h5 class="mb-3">Embedded Video Lesson</h5>
                <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Trading Lesson" allowfullscreen></iframe>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const searchInput = document.getElementById("qaSearch");
    const chips = Array.from(document.querySelectorAll(".chip"));
    const qaItems = Array.from(document.querySelectorAll(".qa-item"));
    const progressBar = document.getElementById("progressBar");
    const progressText = document.getElementById("progressText");
    const emptyResult = document.getElementById("emptyResult");
    let activeFilter = "all";

    function updateProgress() {
        const allCollapses = Array.from(document.querySelectorAll(".accordion-collapse"));
        const opened = allCollapses.filter((item) => item.classList.contains("show")).length;
        const pct = allCollapses.length ? Math.round((opened / allCollapses.length) * 100) : 0;
        progressBar.style.width = pct + "%";
        progressText.textContent = pct + "% Completed";
    }

    function applyFilter() {
        const q = (searchInput.value || "").toLowerCase().trim();
        let visibleCount = 0;
        qaItems.forEach((item) => {
            const tags = (item.dataset.tags || "").toLowerCase();
            const txt = item.textContent.toLowerCase();
            const matchSearch = !q || txt.includes(q) || tags.includes(q);
            const matchChip = activeFilter === "all" || tags.includes(activeFilter);
            const show = matchSearch && matchChip;
            item.classList.toggle("hide-by-filter", !show);
            if (show) visibleCount += 1;
        });
        emptyResult.style.display = visibleCount === 0 ? "block" : "none";
    }

    searchInput.addEventListener("input", applyFilter);
    chips.forEach((chip) => {
        chip.addEventListener("click", () => {
            chips.forEach((c) => c.classList.remove("active"));
            chip.classList.add("active");
            activeFilter = chip.dataset.filter;
            if (activeFilter === "level1") document.getElementById("level1-tab").click();
            if (activeFilter === "level2") document.getElementById("level2-tab").click();
            if (activeFilter === "level3") document.getElementById("level3-tab").click();
            applyFilter();
        });
    });

    document.querySelectorAll(".accordion-collapse").forEach((el) => {
        el.addEventListener("shown.bs.collapse", updateProgress);
        el.addEventListener("hidden.bs.collapse", updateProgress);
    });

    applyFilter();
    updateProgress();
</script>
>>>>>>> d01e1cd (update)
</body>
</html>