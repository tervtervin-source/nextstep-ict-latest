<?php
session_start();
require_once 'config/db.php';

// Authentication & Role Check
if (!isset($_SESSION['user_id']) OR $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success = '';
$error = '';

// Fetch Student details safely
$stmt_std = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
if (!$stmt_std) {
    die("SQL Prepare Error: " . $conn->error);
}
$stmt_std->bind_param("i", $user_id);
$stmt_std->execute();
$student = $stmt_std->get_result()->fetch_assoc();
$student_id = $student['student_id'] ?? 0;
$profile_pic = $student['profilePicture'] ?? $student['profile_picture'] ?? '';

// Fetch Dynamic Metrics for Dashboard Cards
$stmt_opps = $conn->prepare("SELECT COUNT(*) as count FROM opportunities");
$stmt_opps->execute();
$total_opps = $stmt_opps->get_result()->fetch_assoc()['count'] ?? 0;

$stmt_apps = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE student_id = ?");
$stmt_apps->bind_param("i", $student_id);
$stmt_apps->execute();
$total_apps = $stmt_apps->get_result()->fetch_assoc()['count'] ?? 0;

$stmt_int = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE student_id = ? AND (status = 'Shortlisted' OR status = 'Interview Scheduled')");
$stmt_int->bind_param("i", $student_id);
$stmt_int->execute();
$total_interviews = $stmt_int->get_result()->fetch_assoc()['count'] ?? 0;

$stmt_notifs = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE (user_id = ? OR user_id = ?) AND is_read = 0");
$stmt_notifs->bind_param("ii", $user_id, $student_id);
$stmt_notifs->execute();
$unread_notifications = $stmt_notifs->get_result()->fetch_assoc()['count'] ?? 0;

// Calculate Profile Completion %
$completion_score = 40; // Base score
if (!empty($student['student_number'])) $completion_score += 20;
if (!empty($student['qualification'])) $completion_score += 20;
if (!empty($student['year_of_study'])) $completion_score += 10;
if (!empty($profile_pic)) $completion_score += 10;

// Handle Opportunity Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['apply_opportunity_id'])) {
    $opportunity_id = intval($_POST['apply_opportunity_id']);

    $check_app = $conn->prepare("SELECT application_id FROM applications WHERE student_id = ? AND opportunity_id = ?");
    $check_app->bind_param("ii", $student_id, $opportunity_id);
    $check_app->execute();
    
    if ($check_app->get_result()->num_rows > 0) {
        $error = 'You have already applied for this opportunity.';
    } else {
        $stmt_apply = $conn->prepare("INSERT INTO applications (student_id, opportunity_id, status, date_applied) VALUES (?, ?, 'Pending', NOW())");
        if ($stmt_apply AND $stmt_apply->bind_param("ii", $student_id, $opportunity_id) AND $stmt_apply->execute()) {
            $success = 'Application submitted successfully! Track status below.';
        } else {
            $error = 'Failed to submit application: ' . $conn->error;
        }
    }
}

// Fetch filter parameters
$search_keyword = trim($_GET['search'] ?? '');

// Fetch available opportunities securely
$query = "SELECT o.*, c.company_name, c.location FROM opportunities o JOIN companies c ON o.company_id = c.company_id WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_keyword)) {
    $query .= " AND (o.title LIKE ? OR o.description LIKE ? OR o.requiredSkills LIKE ?)";
    $like_keyword = "%{$search_keyword}%";
    array_push($params, $like_keyword, $like_keyword, $like_keyword);
    $types .= "sss";
}

$query .= " ORDER BY o.opportunity_id DESC LIMIT 6";
$stmt_opp = $conn->prepare($query);
if (!empty($params)) {
    $stmt_opp->bind_param($types, ...$params);
}
$stmt_opp->execute();
$opportunities_result = $stmt_opp->get_result();

// Fetch Student's Tracked Applications
$stmt_apps = $conn->prepare("SELECT a.*, o.title, c.company_name FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id JOIN companies c ON o.company_id = c.company_id WHERE a.student_id = ? ORDER BY a.date_applied DESC");
$stmt_apps->bind_param("i", $student_id);
$stmt_apps->execute();
$applications_result = $stmt_apps->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Career Dashboard | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #132238;
            --ump-navy-dark: #0b1522;
            --ump-gold: #f59e0b;
            --ump-gold-hover: #d97706;
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
            position: sticky;
            top: 0;
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

        .dashboard-layout {
            display: flex;
            min-height: calc(100vh - 65px);
        }

        .sidebar {
            width: 260px;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.75rem 1rem;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .sidebar a:hover, .sidebar a.active {
            background-color: #f1f5f9;
            color: var(--ump-navy);
        }

        .sidebar a.active {
            background-color: #fef3c7;
            color: var(--ump-gold-hover);
        }

        .main-content {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
            background-color: #f8fafc;
        }

        .metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .metric-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
        }

        .metric-card .number {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--ump-navy);
            margin: 0;
        }

        .progress-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .progress-bar-container {
            background: #e2e8f0;
            border-radius: 10px;
            height: 12px;
            width: 100%;
            margin-top: 0.75rem;
            overflow: hidden;
        }

        .progress-bar-fill {
            background: var(--ump-gold);
            height: 100%;
            border-radius: 10px;
        }

        .section-title {
            color: var(--ump-navy);
            font-size: 1.35rem;
            font-weight: 700;
            margin: 2rem 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-box {
            background: #ffffff;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
        }

        .form-control {
            padding: 0.65rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.9rem;
            flex: 1;
        }

        .btn-filter {
            background-color: var(--ump-navy);
            color: #ffffff;
            padding: 0.65rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .opp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .opp-card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .badge {
            background-color: #fef3c7;
            color: var(--ump-gold-hover);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .table-container {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        th, td {
            padding: 0.85rem 1.15rem;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background-color: #f1f5f9;
            color: var(--ump-navy);
            font-weight: 700;
        }

        .status-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-accepted { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <nav class="portal-nav">
        <a href="student_dashboard.php" class="nav-brand">
            <i class="fa-solid fa-graduation-cap" style="color: var(--ump-gold);"></i> NextStep ICT Portal
        </a>
        <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.95rem; font-weight: 600;">
            <span><i class="fa-solid fa-user-graduate" style="color: var(--ump-gold);"></i> <?= htmlspecialchars($full_name) ?></span>
            <a href="logout.php" style="color: #dc2626; text-decoration: none;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>
    </nav>

    <div class="dashboard-layout">
        
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <a href="student_dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="cv_builder.php"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--ump-gold);"></i> CV Builder & Documents</a>
            <a href="student_profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
            <a href="#opportunities"><i class="fa-solid fa-briefcase"></i> Opportunities</a>
            <a href="#saved"><i class="fa-solid fa-bookmark"></i> Saved Opportunities</a>
            <a href="#applications"><i class="fa-solid fa-clipboard-list"></i> Applications</a>
            <a href="#notifications"><i class="fa-solid fa-bell"></i> Notifications</a>
            <a href="#feedback"><i class="fa-solid fa-comments"></i> Feedback</a>
        </div>

        <div class="main-content">

            <!-- Metrics Top Row (5 Cards) -->
            <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div class="metric-card">
                    <h3>Total Opportunities</h3>
                    <p class="number" style="color: var(--ump-gold);"><?php echo $total_opps; ?></p>
                </div>
                <div class="metric-card">
                    <h3>Applications Submitted</h3>
                    <p class="number" style="color: var(--ump-gold);"><?php echo $total_apps; ?></p>
                </div>
                <div class="metric-card">
                    <h3>Interviews Scheduled</h3>
                    <p class="number" style="color: var(--ump-gold);"><?php echo $total_interviews; ?></p>
                </div>
                <div class="metric-card">
                    <h3>Notifications</h3>
                    <p class="number" style="color: var(--ump-gold);"><?php echo $unread_notifications; ?></p>
                </div>
                <div class="metric-card">
                    <h3>Profile Completion</h3>
                    <p class="number" style="color: var(--ump-gold);"><?php echo $completion_score; ?>%</p>
                </div>
            </div>

            <!-- 2-Column Middle Grid Container -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                
                <!-- Left Column: Profile Completion Progress -->
                <div>
                    <div class="progress-box">
                        <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.95rem; color: var(--ump-navy); margin-bottom: 0.75rem;">
                            <span><i class="fa-solid fa-user-check" style="color: var(--ump-gold);"></i> Profile Completion Progress</span>
                            <span><?php echo $completion_score; ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $completion_score; ?>%;"></div>
                        </div>
                        <p style="font-size: 0.85rem; color: #64748b; margin: 0.75rem 0 0 0;">Complete your profile details and CV to reach 100%.</p>
                    </div>
                </div>

               <!-- Right Column: Lecturer Announcements & Guidance -->
<div>
    <div class="announcements-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem;">
        <h3 style="color: var(--ump-navy); margin-top: 0; font-size: 1.2rem; margin-bottom: 1rem;">
            <i class="fa-solid fa-bullhorn" style="color: var(--ump-gold);"></i> Lecturer Announcements &amp; Guidance
        </h3>
        
        <?php
        $ann_res = $conn->query("SELECT * FROM announcements ORDER BY 1 DESC LIMIT 5");
        
        if ($ann_res && $ann_res->num_rows > 0) {
            while ($ann = $ann_res->fetch_assoc()) {
                $title = $ann['title'] ?? 'Announcement';
                $content = $ann['content'] ?? $ann['message'] ?? $ann['details'] ?? '';
                $author = $ann['author_name'] ?? $ann['lecturer_name'] ?? 'Bella Ngomane';
                $date = $ann['created_at'] ?? $ann['date_posted'] ?? $ann['date'] ?? '';

                echo '<div style="background: #f8fafc; border-left: 4px solid var(--ump-gold); padding: 1rem; margin-bottom: 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">';
                echo '<h4 style="margin: 0 0 0.3rem 0; color: var(--ump-navy); font-size: 1rem;">' . htmlspecialchars($title) . '</h4>';
                echo '<p style="margin: 0; font-size: 0.88rem; color: #475569;">' . htmlspecialchars($content) . '</p>';
                echo '<small style="color: #94a3b8; display: block; margin-top: 0.5rem;"><i class="fa-solid fa-user"></i> Posted by: ' . htmlspecialchars($author) . ($date ? ' &bull; ' . htmlspecialchars($date) : '') . '</small>';
                echo '</div>';
            }
        } else {
            echo '<p style="color: #64748b; font-size: 0.9rem; margin: 0;">No announcements at this time.</p>';
        }
        ?>
    </div>
</div>

            </div>

            <!-- Opportunities Section -->
            <div id="opportunities" class="section-title"><i class="fa-solid fa-briefcase"></i> Recent Opportunities &amp; Roles</div>
            
            <form method="GET" action="student_dashboard.php" class="filter-box">
                <input type="text" name="search" class="form-control" placeholder="Search by title, skills, or keyword..." value="<?= htmlspecialchars($search_keyword) ?>">
                <button type="submit" class="btn-filter"><i class="fa-solid fa-search"></i> Search</button>
            </form>

            <div class="opp-grid">
                <?php if ($opportunities_result AND $opportunities_result->num_rows > 0): ?>
                    <?php while ($opp = $opportunities_result->fetch_assoc()): ?>
                        <div class="opp-card">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <span class="badge">ICT Position</span>
                                    <span style="font-size: 0.75rem; color: #64748b;"><i class="fa-solid fa-calendar"></i> Closes: <?= htmlspecialchars($opp['closingDate']) ?></span>
                                </div>
                                <h3 style="color: var(--ump-navy); margin: 0 0 0.2rem 0; font-size: 1.1rem;"><?= htmlspecialchars($opp['title']) ?></h3>
                                <p style="color: #64748b; font-size: 0.8rem; margin: 0 0 0.75rem 0;"><i class="fa-solid fa-building"></i> <?= htmlspecialchars($opp['company_name']) ?> &bull; <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($opp['location']) ?></p>
                                <p style="font-size: 0.85rem; color: #334155; margin-bottom: 0.75rem;"><?= nl2br(htmlspecialchars($opp['description'])) ?></p>
                                <div style="font-size: 0.8rem; color: #475569; margin-bottom: 0.75rem;">
                                    <strong>Skills:</strong> <?= htmlspecialchars($opp['requiredSkills']) ?>
                                </div>
                            </div>
                            <a href="apply.php?opportunity_id=<?php echo $opp['opportunity_id']; ?>" style="background-color: var(--ump-gold); color: #fff; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; text-align: center;">
                                <i class="fa-solid fa-paper-plane"></i> Apply Now
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; background: #ffffff; padding: 1.5rem; text-align: center; border-radius: 10px; border: 1px solid #e2e8f0; color: #64748b;">
                        <p style="margin: 0; font-weight: 600;">No active opportunities available right now.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Application Status Tracker -->
            <div id="applications" class="section-title"><i class="fa-solid fa-clipboard-list"></i> Application Status Tracker</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Position Title</th>
                            <th>Company</th>
                            <th>Date Applied</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($applications_result AND $applications_result->num_rows > 0): ?>
                            <?php while ($app = $applications_result->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--ump-navy);"><?= htmlspecialchars($app['title']) ?></td>
                                    <td><?= htmlspecialchars($app['company_name']) ?></td>
                                    <td><?= htmlspecialchars($app['date_applied']) ?></td>
                                    <td>
                                        <?php 
                                            $status_class = 'status-pending';
                                            if ($app['status'] === 'Accepted') $status_class = 'status-accepted';
                                            if ($app['status'] === 'Rejected') $status_class = 'status-rejected';
                                        ?>
                                        <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($app['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #64748b; padding: 1.5rem;">You haven't submitted any applications yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Saved Opportunities Section -->
            <div id="saved" class="section-title"><i class="fa-solid fa-bookmark"></i> Saved Opportunities</div>
            <div style="background: #ffffff; padding: 1.25rem 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 2rem;">
                <p style="margin: 0; font-size: 0.9rem; color: #64748b;">You have no bookmarked or saved opportunities at the moment.</p>
            </div>

            <!-- Notifications Section -->
<div id="notifications" class="section-title"><i class="fa-solid fa-bell"></i> Notifications</div>
<div style="background: #ffffff; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 2rem;">
    <?php 
    $current_session_uid = intval($_SESSION['user_id'] ?? 0);
    $current_student_id = intval($student['student_id'] ?? 0);

    // Fetch all notifications for this student/user
    $notif_res = $conn->query("SELECT * FROM notifications WHERE user_id = '$current_session_uid' OR user_id = '$current_student_id' ORDER BY notification_id DESC");
    ?>

    <?php if ($notif_res && $notif_res->num_rows > 0): ?>
        <?php while ($notif = $notif_res->fetch_assoc()): ?>
            <?php 
                $title = $notif['title'] ?? $notif['subject'] ?? 'Notification';
                $message = $notif['message'] ?? $notif['content'] ?? $notif['notification_text'] ?? '';
                $date_str = $notif['created_at'] ?? $notif['date_sent'] ?? $notif['date'] ?? '';
                $formatted_date = !empty($date_str) ? date('d M Y, H:i', strtotime($date_str)) : '';
            ?>
            <div style="background: #f8fafc; border-left: 5px solid var(--ump-navy); padding: 1.25rem 1.5rem; margin-bottom: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <h4 style="margin: 0; color: var(--ump-navy); font-size: 1rem; font-weight: 700;"><?php echo htmlspecialchars($title); ?></h4>
                    <?php if ($formatted_date): ?>
                        <span style="font-size: 0.8rem; color: #94a3b8;"><?php echo htmlspecialchars($formatted_date); ?></span>
                    <?php endif; ?>
                </div>
                <p style="margin: 0; color: #475569; font-size: 0.88rem; line-height: 1.5;"><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align: center; color: #64748b; padding: 1.5rem;">
            <i class="fa-solid fa-envelope-open" style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #94a3b8;"></i>
            <p style="margin: 0; font-size: 0.9rem;">You have no notifications at this time.</p>
        </div>
    <?php endif; ?>
</div>
            <!-- Student Feedback & Support Section -->
            <div id="feedback" class="section-title"><i class="fa-solid fa-comments"></i> Student Feedback &amp; Support</div>
            <div style="background: #ffffff; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 2rem;">
                <p style="margin: 0; font-size: 0.9rem; color: #64748b;">Need assistance with your career placement or portal access? Contact the UMP ICT Department support desk at <strong style="color: var(--ump-navy);">ict-support@ump.ac.za</strong>.</p>
            </div>

            <!-- SINGLE Full-Width Footer at the Bottom -->
            <footer style="background-color: var(--ump-navy); color: #ffffff; padding: 1.5rem; text-align: center; border-radius: 12px; margin-top: 3rem;">
                <p style="margin: 0 0 0.25rem 0; font-weight: 600;">University of Mpumalanga — NextStep ICT Portal</p>
                <p style="margin: 0; font-size: 0.85rem; color: #cbd5e1;">&copy; 2026 School of Computing and Mathematical Sciences. All rights reserved.</p>
            </footer>

        </div> <!-- Closes main-content -->

    </div> <!-- Closes dashboard-layout -->
</body>
</html>