<?php
session_start();

$stats = ['total_jobs' => 0, 'total_companies' => 0, 'total_students' => 0];
$announcements = false;
$featured_opportunities = false;

if (file_exists('config/db.php')) {
    include_once 'config/db.php';
    if (isset($conn) && $conn) {
        // Dynamic counts
        $jobs_res = $conn->query("SELECT COUNT(*) AS total FROM opportunities");
        if ($jobs_res) $stats['total_jobs'] = $jobs_res->fetch_assoc()['total'];

        $comp_res = $conn->query("SELECT COUNT(*) AS total FROM companies");
        if ($comp_res) $stats['total_companies'] = $comp_res->fetch_assoc()['total'];

        $std_res = $conn->query("SELECT COUNT(*) AS total FROM students");
        if ($std_res) $stats['total_students'] = $std_res->fetch_assoc()['total'];

        // Announcements query
        $announcements = $conn->query("SELECT * FROM announcements ORDER BY notice_id DESC LIMIT 5");

        // Featured opportunities query
        $featured_opportunities = $conn->query("SELECT o.*, c.company_name FROM opportunities o LEFT JOIN companies c ON o.company_id = c.company_id ORDER BY o.opportunity_id DESC LIMIT 4");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextStep ICT Portal | University of Mpumalanga</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #132238;
            --ump-navy-dark: #0b1522;
            --ump-gold: #f59e0b;
            --ump-gold-hover: #d97706;
            --ump-green: #15803d;
            --ump-light: #f8fafc;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--ump-light);
            color: #334155;
            margin: 0;
            padding: 0;
        }

        .portal-nav {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: relative;
            z-index: 100;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--ump-navy);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .nav-links a {
            text-decoration: none;
            color: #475569;
        }

        .nav-links a:hover {
            color: var(--ump-navy);
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            background: none;
            border: none;
            color: #475569;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0;
            font-family: inherit;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: #ffffff;
            min-width: 200px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 0;
            z-index: 1000;
        }

        .dropdown-content a {
            color: #334155;
            padding: 0.65rem 1.25rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.875rem;
        }

        .dropdown-content a:hover {
            background-color: #f1f5f9;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .btn-nav-gold {
            background-color: var(--ump-gold);
            color: #ffffff !important;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 600;
        }

        .hero-banner {
            background: linear-gradient(135deg, var(--ump-navy) 0%, var(--ump-navy-dark) 100%);
            color: #ffffff;
            padding: 4.5rem 1.5rem;
            text-align: center;
            border-bottom: 4px solid var(--ump-gold);
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .hero-tagline {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ump-gold);
            margin-bottom: 1rem;
            text-transform: uppercase;
        }

        .hero-subtitle {
            font-size: 1rem;
            color: #cbd5e1;
            max-width: 780px;
            margin: 0 auto 2.5rem;
            line-height: 1.6;
        }

        .cta-group {
            display: flex;
            gap: 0.85rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-gold { background-color: var(--ump-gold); color: #ffffff; }
        .btn-white { background-color: #ffffff; color: var(--ump-navy); }
        .btn-green { background-color: var(--ump-green); color: #ffffff; }

        .stats-section {
            max-width: 1100px;
            margin: -2rem auto 3rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 0 1.5rem;
            position: relative;
            z-index: 10;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: #fef3c7;
            color: var(--ump-gold-hover);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--ump-navy);
            margin: 0;
        }

        .portal-container {
            max-width: 1100px;
            margin: 0 auto 4rem;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .portal-container { grid-template-columns: 1fr; }
        }

        .card-panel {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 0.85rem;
            margin-bottom: 1.25rem;
        }

        .job-item {
            padding: 0.85rem 1rem;
            border: 1px solid #f1f5f9;
            border-radius: 6px;
            margin-bottom: 0.85rem;
        }

        .notice-item {
            padding: 0.85rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .notice-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

    <nav class="portal-nav">
        <a href="index.php" class="nav-brand">
            <i class="fa-solid fa-graduation-cap" style="color: var(--ump-gold); font-size: 1.5rem;"></i>
            <span>NextStep ICT</span>
        </a>
        <div class="nav-links">
            <a href="index.php">Home</a>

            <div class="dropdown">
                <button class="dropdown-btn">
                    Opportunities <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                </button>
                <div class="dropdown-content">
                    <a href="login.php"><i class="fa-solid fa-laptop-code" style="color: var(--ump-gold);"></i> Internships</a>
                    <a href="login.php"><i class="fa-solid fa-user-tie" style="color: var(--ump-gold);"></i> Graduate Programs</a>
                    <a href="login.php"><i class="fa-solid fa-briefcase" style="color: var(--ump-gold);"></i> Entry-Level Jobs</a>
                </div>
            </div>

            <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Sign In</a>
            <a href="register.php" class="btn-nav-gold">Register</a>
        </div>
    </nav>

    <div class="hero-banner">
        <div class="hero-tagline">University of Mpumalanga &mdash; ICT Portal</div>
        <h1 class="hero-title">Connecting ICT Talent with Industry Leaders</h1>
        <p class="hero-subtitle">Empowering Higher Certificate in ICT (HICT), Diploma in ICT (DICT), and Bachelor of ICT (BICT) students and graduates to bridge academia with professional careers.</p>
        
        <div class="cta-group">
            <a href="login.php" class="btn-action btn-gold"><i class="fa-solid fa-briefcase"></i> Explore Opportunities</a>
            <a href="register.php" class="btn-action btn-white"><i class="fa-solid fa-user-graduate"></i> Student Signup</a>
            <a href="register.php" class="btn-action btn-green"><i class="fa-solid fa-building"></i> Employer Signup</a>
        </div>
    </div>

    <!-- Live Statistics Section -->
    <div class="stats-section">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-briefcase"></i></div>
            <div>
                <p class="stat-value"><?= $stats['total_jobs'] ?></p>
                <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 600;">Active Opportunities</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
            <div>
                <p class="stat-value"><?= $stats['total_companies'] ?></p>
                <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 600;">Verified Employers</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
            <div>
                <p class="stat-value"><?= $stats['total_students'] ?></p>
                <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 600;">UMP ICT Talent Pool</p>
            </div>
        </div>
    </div>

    <div class="portal-container">
        <!-- Main Content Column: Featured ICT Opportunities -->
        <div>
            <div class="card-panel">
                <div class="panel-header">
                    <h3 style="margin: 0; color: var(--ump-navy); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-laptop-code" style="color: var(--ump-gold);"></i> Featured ICT Opportunities
                    </h3>
                    <a href="login.php" style="color: var(--ump-navy); font-weight: 600; font-size: 0.875rem; text-decoration: none;">View All &rarr;</a>
                </div>

                <?php if ($featured_opportunities && $featured_opportunities->num_rows > 0): ?>
                    <?php while ($job = $featured_opportunities->fetch_assoc()): ?>
                        <div class="job-item">
                            <h4 style="margin: 0 0 0.25rem 0; color: var(--ump-navy); font-size: 1rem;"><?= htmlspecialchars($job['title']) ?></h4>
                            <p style="margin: 0 0 0.5rem 0; color: #64748b; font-size: 0.85rem;"><i class="fa-solid fa-building"></i> <?= htmlspecialchars($job['company_name']) ?> &bull; <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($job['category'] ?? 'ICT Position') ?></p>
                            <p style="margin: 0; font-size: 0.85rem; color: #334155;"><?= htmlspecialchars(substr($job['description'], 0, 120)) ?>...</p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center; padding: 2rem 0; margin: 0;">No active opportunities posted in the database yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar Column: Department Notices -->
     <div class="card-panel">
    <div class="panel-header" style="margin-bottom: 1rem;">
        <h3 style="margin: 0; color: var(--ump-navy); display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-bullhorn" style="color: var(--ump-orange);"></i> Department Notices
        </h3>
    </div>

    <?php if ($announcements && $announcements->num_rows > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php while ($notice = $announcements->fetch_assoc()): ?>
                <div style="padding: 0.75rem 1rem; border-left: 4px solid var(--ump-orange); background: #fff8e6; border-radius: 0 6px 6px 0;">
                    <h4 style="margin: 0 0 0.25rem; color: var(--ump-navy); font-size: 0.95rem; font-weight: 700;">
                        <?php echo htmlspecialchars($notice['title']); ?>
                    </h4>
                    <p style="margin: 0 0 0.25rem; font-size: 0.85rem; color: #334155; line-height: 1.4;">
                        <?php echo htmlspecialchars($notice['message']); ?>
                    </p>
                    <?php if (!empty($notice['author_name'])): ?>
                        <small style="color: #64748b; font-size: 0.75rem;">Posted by: <?php echo htmlspecialchars($notice['author_name']); ?></small>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p style="color: #64748b; text-align: center; padding: 2rem 0; margin: 0;">No live announcements at this time.</p>
    <?php endif; ?>
</div>

                <?php if ($announcements && $announcements->num_rows > 0): ?>
                    <?php while ($notice = $announcements->fetch_assoc()): ?>
                        <div class="notice-item">
                            <h4 style="margin: 0 0 0.25rem 0; color: var(--ump-navy); font-size: 0.95rem;"><?= htmlspecialchars($notice['title']) ?></h4>
                            <p style="margin: 0 0 0.35rem 0; font-size: 0.85rem; color: #475569;"><?= htmlspecialchars($notice['message']) ?></p>
                            <span style="font-size: 0.75rem; color: #64748b;"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($notice['createdAT'] ?? $notice['date'] ?? '') ?> &bull; <?= htmlspecialchars($notice['department'] ?? 'ICT') ?></span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center; padding: 2rem 0; margin: 0; font-size: 0.9rem;">No live announcements at this time.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>