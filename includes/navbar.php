<?php
// Ensure session started
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Load course status helper if available
if (!function_exists('isCoursesEnabled')) {
	$courseStatusPath = __DIR__ . '/check_course_status.php';
	if (file_exists($courseStatusPath)) {
		require_once $courseStatusPath;
	}
}

// Compute correct path prefix from the executing script directory to project root (where index.php lives)
$executingDir = isset($_SERVER['SCRIPT_FILENAME']) ? dirname($_SERVER['SCRIPT_FILENAME']) : getcwd();
$prefixCandidates = ['', '..', '../..', '../../..'];
$p = '';
foreach ($prefixCandidates as $cand) {
	$test = rtrim($executingDir . DIRECTORY_SEPARATOR . ($cand === '' ? '' : ($cand . DIRECTORY_SEPARATOR)) . 'index.php', DIRECTORY_SEPARATOR);
	if (file_exists($test)) {
		$p = ($cand === '' ? '' : ($cand . '/'));
		break;
	}
}

// Active route detection (basic)
$scriptName = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
function nav_active($names, $scriptName) {
	foreach ((array)$names as $n) {
		if (strcasecmp($scriptName, $n) === 0) return 'active';
	}
	return '';
}

// Fetch user profile image if logged in
$profileImage = null;
$username = null;
if (isset($_SESSION['user_id'])) {
	$username = $_SESSION['username'] ?? null;
	$dbPath = __DIR__ . '/../config/database.php';
	if (file_exists($dbPath)) {
		require_once $dbPath;
		if (isset($pdo)) {
			$stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
			$stmt->execute([$_SESSION['user_id']]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$profileImage = $row['profile_image'] ?? null;
		}
	}
}
?>

<style>
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

/* Marquee / ticker strip just below navbar */
.ticker-strip {
	background-color: var(--dark-card);
	border-bottom: 1px solid var(--border-color);
	padding: 6px 0;
}
.ticker-strip .ticker-container {
	padding-left: 0.5rem;
	padding-right: 0.5rem;
}
.ticker-strip .tradingview-widget-container {
	width: 100% !important;
	height: 36px !important;
	margin: 0 auto;
}
.ticker-strip .tradingview-widget-container__widget {
	height: 100% !important;
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
</style>

<nav class="navbar navbar-expand-lg navbar-light sticky-top">
	<div class="container">
		<a class="navbar-brand fw-bold" href="<?php echo htmlspecialchars($p . 'index.php'); ?>">
			<i class="fas fa-chart-line text-primary me-2"></i>NpLTrader
		</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarNav">
			<ul class="navbar-nav mx-auto">
				<li class="nav-item">
					<a class="nav-link <?php echo nav_active(['', 'index.php'], $scriptName); ?>" href="<?php echo htmlspecialchars($p . 'index.php'); ?>">HOME</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo nav_active(['blog.php'], $scriptName); ?>" href="<?php echo htmlspecialchars($p . 'blog.php'); ?>">BLOG</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo nav_active(['course.php'], $scriptName); ?>" href="<?php echo htmlspecialchars($p . 'course/course.php'); ?>" <?php if (function_exists('isCoursesEnabled') && !isCoursesEnabled()): ?>onclick="return handleCourseClick(event)"<?php endif; ?>>COURSE</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo nav_active(['about.php'], $scriptName); ?>" href="<?php echo htmlspecialchars($p . 'about.php'); ?>">ABOUT US</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo nav_active(['contact.php'], $scriptName); ?>" href="<?php echo htmlspecialchars($p . 'contact.php'); ?>">CONTACT</a>
				</li>
				<?php if (isset($_SESSION['user_id'])): ?>
				<li class="nav-item">
					<a class="nav-link <?php echo nav_active(['dashboard.php'], $scriptName); ?>" href="<?php echo htmlspecialchars($p . 'dashboard/dashboard.php'); ?>">DASHBOARD</a>
				</li>
				<?php endif; ?>
			</ul>
			<div class="d-flex align-items-center">
				<?php if (isset($_SESSION['user_id']) && $username): ?>
					<div class="dropdown me-3">
						<button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" style="color: var(--primary) !important; padding: 0;">
							<?php if (!empty($profileImage) && file_exists($profileImage)): ?>
								<img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; border: 2px solid var(--primary);">
							<?php else: ?>
								<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 8px; font-weight: bold;">
									<?php echo strtoupper(substr($username, 0, 1)); ?>
								</div>
							<?php endif; ?>
							<span><?php echo htmlspecialchars($username); ?></span>
						</button>
						<ul class="dropdown-menu dropdown-menu-end">
							<li><a class="dropdown-item" href="<?php echo htmlspecialchars($p . 'dashboard/dashboard.php'); ?>"><i class="fas fa-th-large me-2"></i>Dashboard</a></li>
							<li><a class="dropdown-item" href="<?php echo htmlspecialchars($p . 'user/profile.php'); ?>"><i class="fas fa-user me-2"></i>Profile</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars($p . 'logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
						</ul>
					</div>
				<?php else: ?>
					<a href="<?php echo htmlspecialchars($p . 'login.php'); ?>" class="btn btn-outline-primary me-2">Sign In</a>
					<a href="<?php echo htmlspecialchars($p . 'register.php'); ?>" class="btn btn-primary">Sign Up</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</nav>

<!-- TradingView Marquee (same across all pages) -->
<div class="ticker-strip">
	<div class="container-fluid ticker-container">
		<div class="tradingview-widget-container">
			<div class="tradingview-widget-container__widget"></div>
			<script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js" async>
			{
				"symbols": [
					{ "proName": "FOREXCOM:SPXUSD", "title": "S&P 500 Index" },
					{ "proName": "FOREXCOM:NSXUSD", "title": "US 100 Cash CFD" },
					{ "proName": "FX_IDC:EURUSD", "title": "EUR to USD" },
					{ "proName": "BITSTAMP:BTCUSD", "title": "Bitcoin" },
					{ "proName": "BITSTAMP:ETHUSD", "title": "Ethereum" },
					{ "description": "xauusd", "proName": "OANDA:XAUUSD" },
					{ "description": "USD to JPY", "proName": "FX:USDJPY" },
					{ "description": "GBP to USD", "proName": "FX:GBPUSD" },
					{ "description": "NVIDIA", "proName": "NASDAQ:NVDA" },
					{ "description": "TESLA", "proName": "NASDAQ:TSLA" },
					{ "description": "AUD to USD", "proName": "FX:AUDUSD" }
				],
				"showSymbolLogo": true,
				"isTransparent": true,
				"displayMode": "regular",
				"colorTheme": "dark",
				"locale": "en"
			}
			</script>
		</div>
	</div>
</div>

<?php if (function_exists('isCoursesEnabled') && !isCoursesEnabled()): ?>
<script>
function handleCourseClick(event) {
	event.preventDefault();
	if (typeof showComingSoon === 'function') {
		showComingSoon();
	}
	return false;
}
</script>
<?php endif; ?>
