<?php
session_start();
require_once 'config/db.php';

// Disable fatal reporting on minor notices to ensure full HTML rendering
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Active Tab Handler
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Authentication Check
if (!isset($_SESSION['user_id']) OR (strtolower($_SESSION['role']) !== 'company' AND strtolower($_SESSION['role']) !== 'employer')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$company_id = 0;

// 1. Fetch the correct company_id associated with this logged-in user
if ($user_id > 0 && isset($conn)) {
    $stmt_c = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
    if ($stmt_c) {
        $stmt_c->bind_param("i", $user_id);
        $stmt_c->execute();
        $comp_res = $stmt_c->get_result();
        if ($comp_res && $comp_res->num_rows > 0) {
            $comp_data = $comp_res->fetch_assoc();
            $company_id = $comp_data['company_id'] ?? 0;
            $_SESSION['company_id'] = $company_id;
        } else {
            $stmt_ins = $conn->prepare("INSERT INTO companies (user_id, company_name, industry) VALUES (?, ?, ?)");
            $default_name = $_SESSION['full_name'] ?? 'My Company';
            $default_ind = 'ICT & Technology';
            if ($stmt_ins) {
                $stmt_ins->bind_param("iss", $user_id, $default_name, $default_ind);
                $stmt_ins->execute();
                $company_id = $conn->insert_id;
                $_SESSION['company_id'] = $company_id;
            }
        }
    }
}


// 2. Initialize stats array
$stats = [
    'total_vacancies' => 0,
    'total_applications' => 0,
    'shortlisted' => 0,
    'placements' => 0
];

// 3. Run dynamic counts using the correct company_id
if ($company_id > 0) {
    $res_vac = $conn->query("SELECT COUNT(*) AS total FROM opportunities WHERE company_id = $company_id");
    if ($res_vac) $stats['total_vacancies'] = $res_vac->fetch_assoc()['total'];

    $res_app = $conn->query("SELECT COUNT(*) AS total FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id WHERE o.company_id = $company_id");
    if ($res_app) $stats['total_applications'] = $res_app->fetch_assoc()['total'];

    $res_short = $conn->query("SELECT COUNT(*) AS total FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id WHERE o.company_id = $company_id AND a.status = 'Shortlisted'");
    if ($res_short) $stats['shortlisted'] = $res_short->fetch_assoc()['total'];

    $res_place = $conn->query("SELECT COUNT(*) AS total FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id WHERE o.company_id = $company_id AND a.status = 'Accepted'");
if ($res_place) $stats['placements'] = $res_place->fetch_assoc()['total'];
}

$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Employer';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Dynamic Statistics Fetch for Company Dashboard
$company_id = $_SESSION['company_id'] ?? 0;

$stats = [
    'total_vacancies' => 0,
    'total_applications' => 0,
    'shortlisted' => 0,
    'placements' => 0
];

if ($company_id > 0 && isset($conn)) {
    // 1. Total Vacancies for this specific company
    $res_vac = $conn->query("SELECT COUNT(*) AS total FROM opportunities WHERE company_id = $company_id");
    if ($res_vac) $stats['total_vacancies'] = $res_vac->fetch_assoc()['total'];

    // 2. Total Applications received for this company's opportunities
    $res_app = $conn->query("SELECT COUNT(*) AS total FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id WHERE o.company_id = $company_id");
    if ($res_app) $stats['total_applications'] = $res_app->fetch_assoc()['total'];

    // 3. Shortlisted candidates
    $res_short = $conn->query("SELECT COUNT(*) AS total FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id WHERE o.company_id = $company_id AND a.status = 'Shortlisted'");
    if ($res_short) $stats['shortlisted'] = $res_short->fetch_assoc()['total'];

    // 4. Placements / Hired candidates
    $res_place = $conn->query("SELECT COUNT(*) AS total FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id WHERE o.company_id = $company_id AND a.status = 'Accepted'");
$stats['placements'] = ($res_place && $row = $res_place->fetch_assoc()) ? $row['total'] : 0;
}

// Handle Profile & Opportunity Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile
    if (isset($_POST['update_profile'])) {
        $company_name = trim($_POST['company_name'] ?? '');
        $industry     = trim($_POST['industry'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $description  = trim($_POST['description'] ?? '');

        // Check if company record already exists for this user
        $check_stmt = $conn->prepare("SELECT company_id FROM companies WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res && $check_res->num_rows > 0) {
            // Record exists -> UPDATE
            $stmt_up = $conn->prepare("UPDATE companies SET company_name = ?, industry = ?, contact_phone = ?, description = ? WHERE user_id = ?");
            if (!$stmt_up) {
                die("Prepare failed (UPDATE): " . $conn->error);
            }
            $stmt_up->bind_param("ssssi", $company_name, $industry, $phone, $description, $user_id);
        } else {
            // Record does not exist -> INSERT
            $stmt_up = $conn->prepare("INSERT INTO companies (user_id, company_name, industry, contact_phone, description) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_up) {
                die("Prepare failed (INSERT): " . $conn->error);
            }
            $stmt_up->bind_param("issss", $user_id, $company_name, $industry, $phone, $description);
        }

        if ($stmt_up->execute()) {
            header("Location: company_dashboard.php?tab=profile&success=1");
            exit();
        } else {
            die("Execute failed: " . $stmt_up->error);
        }
    }
}
if (isset($_POST['publish_opportunity'])) {
        $title       = trim($_POST['title'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $skills      = trim($_POST['skills'] ?? '');
        $closing_date= trim($_POST['closing_date'] ?? '');

        // Fetch company_id first
        $c_stmt = $conn->prepare("SELECT company_id FROM companies WHERE user_id = ?");
        $c_stmt->bind_param("i", $user_id);
        $c_stmt->execute();
        $c_res = $c_stmt->get_result()->fetch_assoc();
        $company_id = $c_res['company_id'] ?? 0;

        if ($company_id > 0) {
            $stmt = $conn->prepare("INSERT INTO opportunities (company_id, title, category, description, required_skills, closing_date) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("isssss", $company_id, $title, $category, $description, $skills, $closing_date);
            if ($stmt->execute()) {
                header("Location: company_dashboard.php?tab=manage_opportunities&success=1");
                exit();
            } else {
                die("Execute failed: " . $stmt->error);
            }
        } else {
            die("Error: Company ID not found for this user.");
        }
    }
        
    

// 1. Fetch or Create Company Info Safely
$company_id = 0;
$company_name = $full_name;
$industry = 'ICT & Technology';
$website = '';
$phone = '';
$description = '';

$stmt_c = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
if ($stmt_c) {
    $stmt_c->bind_param("i", $user_id);
    $stmt_c->execute();
    $comp_res = $stmt_c->get_result();

    if ($comp_res && $comp_res->num_rows > 0) {
        $comp_data = $comp_res->fetch_assoc();
        $company_id = $comp_data['company_id'] ?? ($comp_data['companyId'] ?? 0);
        $company_name = $comp_data['company_name'] ?? ($comp_data['companyName'] ?? $full_name);
        $industry = $comp_data['industry'] ?? 'ICT & Technology';
        $website = $comp_data['website'] ?? '';
        $phone = $comp_data['phone'] ?? '';
        $description = $comp_data['description'] ?? '';
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO companies (user_id, company_name, industry) VALUES (?, ?, 'ICT & Technology')");
        if ($stmt_ins) {
            $stmt_ins->bind_param("is", $user_id, $full_name);
            $stmt_ins->execute();
            $company_id = $conn->insert_id;
        }
    }
}

// 2. Handle POST Actions (Create, Update, Delete)
// Save New Opportunity
    if (isset($_POST['save_opportunity'])) {
        $title           = trim($_POST['title'] ?? '');
        $description_opp = trim($_POST['description'] ?? '');
        $required_skills = trim($_POST['required_skills'] ?? '');
        $closing_date    = trim($_POST['closing_date'] ?? '');

        if (!empty($title) && !empty($description_opp)) {
            $stmt_opp = $conn->prepare("INSERT INTO opportunities (company_id, posted_by_user_id, title, description, requiredSkills, closingDate) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_opp) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_opp->bind_param("iissss", $company_id, $user_id, $title, $description_opp, $required_skills, $closing_date);
            if ($stmt_opp->execute()) {
                header("Location: company_dashboard.php?tab=manage_opportunities&success=1");
                exit();
            } else {
                die("Execute failed: " . $stmt_opp->error);
            }
        } else {
            $error = "Please fill in all required opportunity fields.";
        }
    }
    

    // Update Opportunity
    if (isset($_POST['update_opportunity'])) {
        $opp_id = intval($_POST['opportunity_id']);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Software Development');
        $description_opp = trim($_POST['description'] ?? '');
        $required_skills = trim($_POST['required_skills'] ?? '');
        $closing_date = trim($_POST['closing_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        $stmt_u = $conn->prepare("UPDATE opportunities SET title = ?, category = ?, description = ?, required_skills = ?, closing_date = ?, status = ? WHERE opportunity_id = ?");
        if (!$stmt_u) {
            $stmt_u = $conn->prepare("UPDATE opportunities SET title = ?, category = ?, description = ?, requiredSkills = ?, closingDate = ?, status = ? WHERE opportunity_id = ?");
        }
        if (!$stmt_u) {
            $stmt_u = $conn->prepare("UPDATE opportunities SET title = ?, category = ?, description = ?, required_skills = ?, closing_date = ?, status = ? WHERE id = ?");
        }
        if ($stmt_u) {
            $stmt_u->bind_param("ssssssi", $title, $category, $description_opp, $required_skills, $closing_date, $status, $opp_id);
            $stmt_u->execute();
            header("Location: company_dashboard.php?tab=manage_opportunities&success=Opportunity updated!");
            exit();
        }
    }

    // Delete Opportunity
    if (isset($_POST['delete_opportunity'])) {
        $opp_id = intval($_POST['opportunity_id']);
        $conn->query("DELETE FROM opportunities WHERE opportunity_id = $opp_id");
        $conn->query("DELETE FROM opportunities WHERE id = $opp_id");
        header("Location: company_dashboard.php?tab=manage_opportunities&success=Opportunity deleted!");
        exit();
    }

    // Update Application Status
if (isset($_POST['update_application_status'])) {
    $app_id = intval($_POST['application_id']);
    $new_status = trim($_POST['status']);
    $stmt_st = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
    if ($stmt_st) {
        $stmt_st->bind_param("si", $new_status, $app_id);
        $stmt_st->execute();

       // 1. Retrieve application and opportunity details safely
        $app_query = $conn->query("SELECT a.*, o.title FROM applications a JOIN opportunities o ON a.opportunity_id = o.opportunity_id WHERE a.application_id = $app_id");
        
        if ($app_query && $app_row = $app_query->fetch_assoc()) {
            // Determine student user ID from whichever column exists
            $target_user_id = $app_row['student_id'] ?? $app_row['student_user_id'] ?? $app_row['user_id'] ?? 0;
            $job_title = $app_row['title'] ?? 'Opportunity';

            if ($target_user_id > 0) {
                $notif_title = "Application Status Update";
                $notif_msg = "Your application for '$job_title' status has been updated to: $new_status.";
                
                // 2. Insert notification
                $conn->query("INSERT INTO notifications (user_id, title, message) VALUES ($target_user_id, '" . $conn->real_escape_string($notif_title) . "', '" . $conn->real_escape_string($notif_msg) . "')");
            }
        }

        header("Location: company_dashboard.php?tab=applications&success=Application status updated!");
        exit();
    }
}

    // Handle Direct Notification Sent by Employer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_direct_notification'])) {
    $raw_recipient_id = intval($_POST['recipient_id']);
    $title = "Application Update: " . trim($_POST['notif_title']);
    $message = trim($_POST['notif_message']);

    if ($raw_recipient_id > 0 && !empty($message)) {
        // Find valid user_id by checking applications, students, and users tables
        $target_user_id = 0;

        // 1. Direct match in users table
        $chk_usr = $conn->query("SELECT id FROM users WHERE id = $raw_recipient_id");
        if ($chk_usr && $chk_usr->num_rows > 0) {
            $target_user_id = $raw_recipient_id;
        } else {
            // 2. Lookup user_id via student table or application record
            $chk_app = $conn->query("SELECT s.user_id FROM applications a LEFT JOIN students s ON a.student_id = s.student_id WHERE a.application_id = $raw_recipient_id OR a.student_id = $raw_recipient_id OR s.student_id = $raw_recipient_id LIMIT 1");
            if ($chk_app && $row = $chk_app->fetch_assoc()) {
                $target_user_id = intval($row['user_id']);
            }
        }

        // 3. Fallback to raw ID if query returns nothing
        if ($target_user_id === 0) {
            $target_user_id = $raw_recipient_id;
        }

        $stmt_send = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt_send->bind_param("iss", $target_user_id, $title, $message);
        if ($stmt_send->execute()) {
            header("Location: company_dashboard.php?tab=applications&success=Notification sent successfully!");
            exit();
        } else {
            die("Database Error: " . $conn->error);
        }
    }
}

   // Update Profile
    if (isset($_POST['update_profile'])) {
        $company_name = trim($_POST['company_name'] ?? '');
        $industry     = trim($_POST['industry'] ?? '');
        $website      = trim($_POST['website'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $description  = trim($_POST['description'] ?? '');

        // Check if company record already exists for this user
        $check_stmt = $conn->prepare("SELECT company_id FROM companies WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res && $check_res->num_rows > 0) {
            // Record exists -> UPDATE
            $stmt_up = $conn->prepare("UPDATE companies SET company_name = ?, industry = ?, website = ?, phone = ?, description = ? WHERE user_id = ?");
            if (!$stmt_up) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_up->bind_param("sssssi", $company_name, $industry, $website, $phone, $description, $user_id);
        } else {
            // Record does not exist -> INSERT
            $stmt_up = $conn->prepare("INSERT INTO companies (user_id, company_name, industry, website, phone, description) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_up) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_up->bind_param("isssss", $user_id, $company_name, $industry, $website, $phone, $description);
        }

        if ($stmt_up->execute()) {
            header("Location: company_dashboard.php?tab=profile&success=1");
            exit();
        } else {
            die("Execute failed: " . $stmt_up->error);
        }
    }

// 3. Robust Data Retrieval
$total_opps = 0; $total_apps = 0; $shortlisted_apps = 0; $placements_count = 0;

$r1 = $conn->query("SELECT COUNT(*) as cnt FROM opportunities");
if ($r1) { $total_opps = $r1->fetch_assoc()['cnt']; }

$r2 = $conn->query("SELECT COUNT(*) as cnt FROM applications");
if ($r2) { $total_apps = $r2->fetch_assoc()['cnt']; }

$r3 = $conn->query("SELECT COUNT(*) as cnt FROM applications WHERE status = 'Shortlisted'");
if ($r3) { $shortlisted_apps = $r3->fetch_assoc()['cnt']; }

$r4 = $conn->query("SELECT COUNT(*) as cnt FROM applications WHERE status = 'Accepted'");
if ($r4) { $placements_count = $r4->fetch_assoc()['cnt']; }

// Fetch Opportunities List
$opps_result = $conn->query("SELECT * FROM opportunities ORDER BY 1 DESC");

// Fetch Applications List
$apps_result = $conn->query("
    SELECT a.*, o.title as job_title, u.full_name as student_name 
    FROM applications a 
    LEFT JOIN opportunities o ON a.opportunity_id = o.opportunity_id 
    LEFT JOIN students st ON a.student_id = st.student_id 
    LEFT JOIN users u ON st.user_id = u.user_id 
    ORDER BY a.application_id DESC
");

// Fetch Item for Edit Mode
$edit_opp = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $res_e = $conn->query("SELECT * FROM opportunities WHERE opportunity_id = $edit_id OR id = $edit_id");
    if ($res_e && $res_e->num_rows > 0) {
        $edit_opp = $res_e->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Recruitment Portal | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #132238;
            --ump-gold: #f59e0b;
            --ump-gold-hover: #d97706;
            --ump-light: #f8fafc;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--ump-light); color: #334155; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .portal-nav { background-color: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 0.85rem 2rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .nav-brand { display: flex; align-items: center; gap: 0.6rem; font-weight: 800; font-size: 1.25rem; color: var(--ump-navy); text-decoration: none; }
        .dashboard-layout { display: flex; flex: 1; }
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; padding: 1.5rem 1rem; display: flex; flex-direction: column; gap: 0.3rem; box-sizing: border-box; }
        .sidebar a { display: flex; align-items: center; gap: 0.85rem; padding: 0.65rem 1rem; color: #475569; text-decoration: none; font-weight: 600; font-size: 0.9rem; border-radius: 8px; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #f1f5f9; color: var(--ump-navy); }
        .sidebar a.active { background-color: #fef3c7; color: var(--ump-gold-hover); }
        .main-content { flex: 1; padding: 2.5rem; max-width: calc(100% - 260px); box-sizing: border-box; overflow-y: auto; }
        .section-title { color: var(--ump-navy); font-size: 1.35rem; font-weight: 700; margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .stat-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-icon { width: 45px; height: 45px; border-radius: 8px; background: #fef3c7; color: var(--ump-gold-hover); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .stat-val { font-size: 1.5rem; font-weight: 700; color: var(--ump-navy); margin: 0; }
        .form-card, .table-container { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.3rem; color: var(--ump-navy); }
        .form-control { width: 100%; padding: 0.65rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; }
        .btn-submit { background-color: var(--ump-gold); color: #ffffff; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-submit:hover { background-color: var(--ump-gold-hover); }
        .btn-action { padding: 0.35rem 0.65rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-edit { background-color: #3b82f6; color: #ffffff; }
        .btn-delete { background-color: #ef4444; color: #ffffff; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        th, td { padding: 0.85rem 1rem; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f1f5f9; color: var(--ump-navy); font-weight: 700; }
        .badge { padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: #e2e8f0; color: #334155; }
    </style>
</head>
<body>

    <nav class="portal-nav">
        <a href="company_dashboard.php" class="nav-brand"><i class="fa-solid fa-building" style="color: var(--ump-gold);"></i> NextStep ICT &mdash; Employer Portal</a>
        <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.95rem; font-weight: 600;">
            <span><i class="fa-solid fa-user-tie" style="color: var(--ump-gold);"></i> <?php echo htmlspecialchars($full_name); ?></span>
            <a href="logout.php" style="color: #dc2626; text-decoration: none;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>
    </nav>

    <div class="dashboard-layout">
        <!-- Sidebar Navigation (Without Back to Home) -->
        <div class="sidebar">
            <a href="company_dashboard.php?tab=dashboard" class="<?php echo ($active_tab === 'dashboard') ? 'active' : ''; ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="company_dashboard.php?tab=profile" class="<?php echo ($active_tab === 'profile') ? 'active' : ''; ?>"><i class="fa-solid fa-building-user"></i> Company Profile</a>
            <a href="company_dashboard.php?tab=post_opportunity" class="<?php echo ($active_tab === 'post_opportunity') ? 'active' : ''; ?>"><i class="fa-solid fa-plus-circle"></i> Post Opportunity</a>
            <a href="company_dashboard.php?tab=manage_opportunities" class="<?php echo ($active_tab === 'manage_opportunities') ? 'active' : ''; ?>"><i class="fa-solid fa-briefcase"></i> Manage Opportunities</a>
            <a href="company_dashboard.php?tab=applications" class="<?php echo ($active_tab === 'applications') ? 'active' : ''; ?>"><i class="fa-solid fa-file-lines"></i> Applications</a>
            <a href="company_dashboard.php?tab=reports" class="<?php echo ($active_tab === 'reports') ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie"></i> Reports & Analytics</a>
            <a href="logout.php" style="color: #dc2626; margin-top: auto;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>

        <?php if ($success): ?>
    <div class="alert alert-success">
        Profile updated successfully!
    </div>
<?php endif; ?>

           <?php if ($active_tab === 'dashboard'): ?>
    <div class="section-title" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-chart-line"></i> Recruitment Overview
    </div>

    <!-- Stat Cards Horizontal Grid Container -->
    <div class="stats-grid" style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem;">
        
        <div class="stat-card" style="flex: 1; min-width: 200px; background: #ffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-icon" style="background: #fff8e6; padding: 1rem; border-radius: 8px; color: #ff8c00; font-size: 1.5rem;">
                <i class="fa-solid fa-briefcase"></i>
            </div>
            <div>
                <div class="stat-val" style="font-size: 1.8rem; font-weight: bold; color: #002147;"><?php echo $stats['total_vacancies']; ?></div>
                <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Total Vacancies</p>
            </div>
        </div>

        <div class="stat-card" style="flex: 1; min-width: 200px; background: #ffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-icon" style="background: #fff8e6; padding: 1rem; border-radius: 8px; color: #ff8c00; font-size: 1.5rem;">
                <i class="fa-solid fa-file-lines"></i>
            </div>
            <div>
                <div class="stat-val" style="font-size: 1.8rem; font-weight: bold; color: #002147;"><?php echo $stats['total_applications']; ?></div>
                <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Total Applications</p>
            </div>
        </div>

        <div class="stat-card" style="flex: 1; min-width: 200px; background: #ffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-icon" style="background: #fff8e6; padding: 1rem; border-radius: 8px; color: #ff8c00; font-size: 1.5rem;">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <div class="stat-val" style="font-size: 1.8rem; font-weight: bold; color: #002147;"><?php echo $stats['shortlisted']; ?></div>
                <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Shortlisted</p>
            </div>
        </div>

        <div class="stat-card" style="flex: 1; min-width: 200px; background: #ffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-icon" style="background: #fff8e6; padding: 1rem; border-radius: 8px; color: #ff8c00; font-size: 1.5rem;">
                <i class="fa-solid fa-award"></i>
            </div>
            <div>
                <div class="stat-val" style="font-size: 1.8rem; font-weight: bold; color: #002147;"><?php echo $stats['placements']; ?></div>
                <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Placements</p>
            </div>
        </div>

    </div>
<?php endif; ?>

            <!-- COMPANY PROFILE TAB -->
        <?php if ($active_tab === 'profile'): ?>
    <div class="section-title">
        <i class="fa-solid fa-building"></i> Company Profile Settings
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="padding: 0.75rem 1rem; background: #d1e7dd; color: #0f5132; border-radius: 6px; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check"></i> Profile updated successfully!
        </div>
    <?php endif; ?>

    <!-- Read-Only Summary Card -->
    <div class="profile-card mb-4" style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
        <h3 style="color: #002147; margin-top: 0; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($company_name ?? 'Company Name Not Set'); ?></h3>
        <p style="margin: 0.25rem 0;"><strong>Industry:</strong> <?php echo htmlspecialchars($industry ?? 'N/A'); ?></p>
        <p style="margin: 0.25rem 0;"><strong>Phone:</strong> <?php echo htmlspecialchars($contact_phone ?? $phone ?? 'N/A'); ?></p>
        <p style="margin: 0.25rem 0;"><strong>About:</strong> <?php echo nl2br(htmlspecialchars($description ?? 'No description provided.')); ?></p>
    </div>

    <!-- Edit Profile Form -->
    <div class="form-card" style="background: #ffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0;">
        <form action="company_dashboard.php?tab=profile" method="POST">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Company Name</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name ?? ''); ?>" required class="form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Industry</label>
                <input type="text" name="industry" value="<?php echo htmlspecialchars($industry ?? ''); ?>" class="form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($contact_phone ?? $phone ?? ''); ?>" class="form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Company Description</label>
                <textarea name="description" class="form-control" rows="4" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
            </div>

            <button type="submit" name="update_profile" class="btn-submit" style="background: #ff8c00; color: #fff; padding: 0.6rem 1.2rem; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Save Profile</button>
        </form>
    </div>
<?php endif; ?>

            <!-- POST OPPORTUNITY TAB -->
            <?php if ($active_tab === 'post_opportunity'): ?>
                <div class="section-title"><i class="fa-solid fa-plus-circle" style="color: var(--ump-gold);"></i> Post New Opportunity</div>
                <div class="form-card">
                    <form method="POST" action="company_dashboard.php?tab=post_opportunity">
                        <input type="hidden" name="save_opportunity" value="1">
                        <div class="form-group">
                            <label>Position Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Software Developer Intern" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <input type="text" name="category" class="form-control" value="Software Development">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Required Skills</label>
                            <input type="text" name="required_skills" class="form-control" placeholder="PHP, MySQL, JavaScript">
                        </div>
                        <div class="form-group">
                            <label>Closing Date</label>
                            <input type="date" name="closing_date" class="form-control" required>
                        </div>
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Publish Position</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- MANAGE OPPORTUNITIES TAB -->
            <?php if ($active_tab === 'manage_opportunities'): ?>
                <div class="section-title"><i class="fa-solid fa-briefcase" style="color: var(--ump-gold);"></i> Manage Posted Vacancies</div>

                <?php if ($edit_opp): ?>
                    <div class="form-card" style="border: 2px solid var(--ump-gold);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="margin:0; color:var(--ump-navy);"><i class="fa-solid fa-pen-to-square"></i> Edit Vacancy</h3>
                            <a href="company_dashboard.php?tab=manage_opportunities" style="color:#64748b; text-decoration:none;"><i class="fa-solid fa-xmark"></i> Cancel</a>
                        </div>
                        <form method="POST" action="company_dashboard.php?tab=manage_opportunities">
                            <input type="hidden" name="update_opportunity" value="1">
                            <input type="hidden" name="opportunity_id" value="<?php echo $edit_opp['opportunity_id'] ?? $edit_opp['id']; ?>">
                            <div class="form-group">
                                <label>Position Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_opp['title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($edit_opp['category'] ?? 'Software Development'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($edit_opp['description']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Required Skills</label>
                                <input type="text" name="required_skills" class="form-control" value="<?php echo htmlspecialchars($edit_opp['required_skills'] ?? ($edit_opp['requiredSkills'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Closing Date</label>
                                <input type="date" name="closing_date" class="form-control" value="<?php echo htmlspecialchars($edit_opp['closing_date'] ?? ($edit_opp['closingDate'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="Active" <?php echo (($edit_opp['status'] ?? '') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Closed" <?php echo (($edit_opp['status'] ?? '') === 'Closed') ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Update Vacancy</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Closing Date</th>
                                <th>Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($opps_result AND $opps_result->num_rows > 0): ?>
                                <?php while ($opp = $opps_result->fetch_assoc()): ?>
                                    <?php $opp_id = $opp['opportunity_id'] ?? $opp['id']; ?>
                                    <tr>
                                        <td style="font-weight:700; color:var(--ump-navy);"><?php echo htmlspecialchars($opp['title']); ?></td>
                                        <td><?php echo htmlspecialchars($opp['closing_date'] ?? ($opp['closingDate'] ?? 'N/A')); ?></td>
                                        <td><span class="badge" style="background:#dcfce7; color:#166534;"><?php echo htmlspecialchars($opp['status'] ?? 'Active'); ?></span></td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <a href="company_dashboard.php?tab=manage_opportunities&edit_id=<?php echo $opp_id; ?>" class="btn-action btn-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                                            <form method="POST" action="company_dashboard.php?tab=manage_opportunities" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this vacancy?');">
                                                <input type="hidden" name="delete_opportunity" value="1">
                                                <input type="hidden" name="opportunity_id" value="<?php echo $opp_id; ?>">
                                                <button type="submit" class="btn-action btn-delete"><i class="fa-solid fa-trash"></i> Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; color:#64748b; padding:1.5rem;">No opportunities posted yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- APPLICATIONS TAB -->
            <?php if ($active_tab === 'applications'): ?>
                <div class="section-title"><i class="fa-solid fa-file-lines" style="color: var(--ump-gold);"></i> Candidate Applications</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Applicant Name</th>
                                <th>Position Title</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($apps_result AND $apps_result->num_rows > 0): ?>
                                <?php while ($app = $apps_result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight:700; color:var(--ump-navy);"><?php echo htmlspecialchars($app['student_name'] ?? 'Applicant'); ?></td>
                                        <td><?php echo htmlspecialchars($app['job_title'] ?? 'ICT Position'); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($app['status'] ?? 'Pending'); ?></span></td>
                                        <td>
  <td style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
    <!-- View Transcript Button -->
    <?php if (!empty($app['transcript_path'])): ?>
        <a href="<?php echo htmlspecialchars($app['transcript_path']); ?>" target="_blank" style="background: #132238; color: #fff; padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i class="fa-solid fa-file-pdf"></i> Transcript
        </a>
    <?php else: ?>
        <span style="color: #94a3b8; font-size: 0.85rem;">No Transcript</span>
    <?php endif; ?>

    <!-- View CV Button -->
    <?php if (!empty($app['cv_path'])): ?>
        <a href="<?php echo htmlspecialchars($app['cv_path']); ?>" target="_blank" style="background: #f59e0b; color: #fff; padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i class="fa-solid fa-file-lines"></i> CV
        </a>
    <?php else: ?>
        <span style="color: #94a3b8; font-size: 0.85rem;">No CV</span>
    <?php endif; ?>

    <!-- Send Notification Button -->
    <button type="button" class="btn btn-sm btn-primary" onclick="openNotifyModal(<?php echo $app['student_user_id'] ?? $app['user_id'] ?? $app['student_id'] ?? $app['application_id']; ?>, '<?php echo htmlspecialchars($app['student_name'] ?? 'Student'); ?>')">
    <i class="fa-solid fa-paper-plane"></i> Notify
</button>

    <!-- Status Update Form -->
    <form method="POST" action="company_dashboard.php?tab=applications" style="margin-left: auto; display: inline-flex; gap: 0.4rem; align-items: center;">
        <input type="hidden" name="update_application_status" value="1">
        <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
        <select name="status" class="form-control" style="padding: 0.3rem; font-size: 0.85rem;">
            <option value="Pending" <?php echo ($app['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
            <option value="Under Review" <?php echo ($app['status'] === 'Under Review') ? 'selected' : ''; ?>>Under Review</option>
            <option value="Shortlisted" <?php echo ($app['status'] === 'Shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
            <option value="Accepted" <?php echo ($app['status'] === 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
            <option value="Rejected" <?php echo ($app['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
        </select>
        <button type="submit" style="background: var(--ump-navy); color: #fff; border: none; padding: 0.4rem 0.7rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem;">Update</button>
    </form>
</td>


                                            <form method="POST" action="company_dashboard.php?tab=applications" style="display: flex; gap: 0.4rem;">
                                                <input type="hidden" name="update_application_status" value="1">
                                                <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                                <select name="status" class="form-control" style="padding: 0.3rem; font-size:0.8rem;">
                                                    <option value="Pending">Pending</option>
                                                    <option value="Under Review">Under Review</option>
                                                    <option value="Shortlisted">Shortlisted</option>
                                                    <option value="Accepted">Accepted</option>
                                                    <option value="Rejected">Rejected</option>
                                                </select>
                                                <button type="submit" style="background:var(--ump-navy); color:#fff; border:none; padding:0.3rem 0.6rem; border-radius:4px; cursor:pointer;"><i class="fa-solid fa-check"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; color:#64748b; padding:1.5rem;">No student applications received yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- REPORTS TAB -->
            <?php if ($active_tab === 'reports'): ?>
                <div class="section-title"><i class="fa-solid fa-chart-pie" style="color: var(--ump-gold);"></i> Reports & Analytics</div>
                <div class="table-container">
                    <h3 style="color:var(--ump-navy); margin-top:0;">Recruitment Performance Overview</h3>
                    <p style="color:#64748b;">Total Vacancies: <strong><?php echo $total_opps; ?></strong></p>
                    <p style="color:#64748b;">Total Applications Received: <strong><?php echo $total_apps; ?></strong></p>
                    <p style="color:#64748b;">Shortlisted Applicants: <strong><?php echo $shortlisted_apps; ?></strong></p>
                    <p style="color:#64748b;">Successful Placements: <strong><?php echo $placements_count; ?></strong></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification Modal Popup -->
<div id="notifyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#ffffff; width:450px; max-width:90%; padding:25px; border-radius:10px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); font-family: sans-serif;">
    <h3 style="margin-top:0; color:#002147; font-size:1.2rem;">Send Notification to <span id="studentName"></span></h3>
    
    <form method="POST" action="company_dashboard.php?tab=applications">
      <input type="hidden" name="recipient_id" id="modalStudentId">
      
      <!-- Notification Type / Status Dropdown -->
      <div style="margin-bottom:15px;">
        <label style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Select Notification Template:</label>
        <select id="statusTemplate" name="notif_title" onchange="updateMessageTemplate()" style="width:100%; padding:8px; border-radius:5px; border:1px solid #ccc; font-size:0.95rem;">
          <option value="Shortlisted">Your application is Shortlisted</option>
          <option value="Interview Scheduled">Your application is Scheduled for Interview</option>
          <option value="Accepted">Your application is Accepted / Offered</option>
          <option value="Rejected">Your application is Unsuccessful</option>
          <option value="Custom">Custom Message</option>
        </select>
      </div>

      <!-- Message Textarea -->
      <div style="margin-bottom:20px;">
        <label style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Message Content:</label>
        <textarea id="notifMessage" name="notif_message" rows="4" style="width:100%; padding:8px; border-radius:5px; border:1px solid #ccc; font-size:0.95rem; resize:vertical;" required>Your application status has been updated to: Shortlisted. Please await further interview details.</textarea>
      </div>

      <div style="text-align:right; gap:10px; display:flex; justify-content:flex-end;">
        <button type="button" onclick="closeNotifyModal()" style="background:#64748b; color:#fff; border:none; padding:8px 16px; border-radius:5px; cursor:pointer;">Cancel</button>
        <button type="submit" name="send_direct_notification" style="background:#002147; color:#fff; border:none; padding:8px 16px; border-radius:5px; cursor:pointer; font-weight:600;">Send Notification</button>
      </div>
    </form>
  </div>
</div>

<script>
function openNotifyModal(studentId, name) {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('studentName').innerText = name;
    document.getElementById('notifyModal').style.display = 'flex';
    updateMessageTemplate();
}

function closeNotifyModal() {
    document.getElementById('notifyModal').style.display = 'none';
}

function updateMessageTemplate() {
    const selected = document.getElementById('statusTemplate').value;
    const msgBox = document.getElementById('notifMessage');
    
    if (selected === "Shortlisted") {
        msgBox.value = "Your application status has been updated to: Shortlisted. Please check your schedule for upcoming instructions.";
    } else if (selected === "Interview Scheduled") {
        msgBox.value = "Your application has been selected for an Interview! Please keep an eye on your email for date and time details.";
    } else if (selected === "Accepted") {
        msgBox.value = "Congratulations! Your application has been Accepted for this position.";
    } else if (selected === "Rejected") {
        msgBox.value = "Thank you for applying. Unfortunately, your application was not selected for this role.";
    } else if (selected === "Custom") {
        msgBox.value = "";
    }
}
            </script>
</body>
</html>