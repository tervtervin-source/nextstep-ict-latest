<?php
session_start();
require_once 'config/db.php';

// Authentication & Admin Role Check
if (!isset($_SESSION['user_id']) OR ($_SESSION['role'] !== 'administrator' AND $_SESSION['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Administrator';
$success = '';
$error = '';

// Handle User Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['delete_user_id'])) {
    $del_id = intval($_POST['delete_user_id']);
    if ($del_id !== $user_id) {
        $stmt_del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt_del->bind_param("i", $del_id);
        if ($stmt_del->execute()) {
            $success = 'User account deleted successfully.';
        } else {
            $error = 'Failed to delete user.';
        }
    } else {
        $error = 'You cannot delete your own active administrator account.';
    }
}

// Handle Department Notice / Announcement Posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!empty($title) && !empty($message)) {
        $stmt_ann = $conn->prepare("INSERT INTO announcements (title, message) VALUES (?, ?)");
        if ($stmt_ann) {
            $stmt_ann->bind_param("ss", $title, $message);
            if ($stmt_ann->execute()) {
                $success = "Department notice published successfully!";
            } else {
                $error = "Failed to publish notice: " . $stmt_ann->error;
            }
        } else {
            $error = "Prepare failed: " . $conn->error;
        }
    } else {
        $error = "Please fill in both the title and content for the notice.";
    }
}

// Handle Company Status Update & Automated Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['update_tax_status'])) {
    $company_id = intval($_POST['company_id']);
    $new_status = trim($_POST['tax_status']);

    $stmt_tax = $conn->prepare("UPDATE companies SET is_verified_by_admin = ? WHERE company_id = ?");
    $approval_val = ($new_status === 'Approved') ? 1 : 0;
    
    if ($stmt_tax) {
        $stmt_tax->bind_param("ii", $approval_val, $company_id);
        if ($stmt_tax->execute()) {
            $success = "Company status successfully updated to '{$new_status}'!";

            $stmt_comp = $conn->prepare("SELECT user_id, company_name FROM companies WHERE company_id = ?");
            if ($stmt_comp) {
                $stmt_comp->bind_param("i", $company_id);
                $stmt_comp->execute();
                $comp_res = $stmt_comp->get_result()->fetch_assoc();
                
                if ($comp_res) {
                    $comp_user_id = $comp_res['user_id'] ?? 0;
                    $c_name = $comp_res['company_name'] ?? 'Company';
                    
                    if ($comp_user_id > 0) {
                        $msg = ($new_status === 'Approved') 
                            ? "Congratulations! Your CIPC and tax clearance documents for {$c_name} have been approved by the Administrator."
                            : "Notice: Your CIPC and tax clearance documents for {$c_name} were marked as Rejected. Please re-upload valid documents.";
                        
                        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message, dateSent, isRead) VALUES (?, ?, NOW(), 0)");
                        if ($stmt_notif) {
                            $stmt_notif->bind_param("is", $comp_user_id, $msg);
                            $stmt_notif->execute();
                        }
                    }
                }
            }
        } else {
            $error = 'Failed to update company status.';
        }
    }
}

// Fetch Metrics for Admin Dashboard
$users_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$opps_count = $conn->query("SELECT COUNT(*) as count FROM opportunities")->fetch_assoc()['count'] ?? 0;
$apps_count = $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'] ?? 0;
$companies_count = $conn->query("SELECT COUNT(*) as count FROM companies")->fetch_assoc()['count'] ?? 0;

$users_result = $conn->query("SELECT * FROM users ORDER BY user_id DESC LIMIT 10");
$companies_result = $conn->query("SELECT * FROM companies ORDER BY company_id DESC");
$students_result = $conn->query("SELECT s.*, u.full_name, u.email FROM students s JOIN users u ON s.user_id = u.user_id ORDER BY s.student_id DESC");
$opps_result = $conn->query("SELECT o.*, c.company_name FROM opportunities o JOIN companies c ON o.company_id = c.company_id ORDER BY o.opportunity_id DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #132238;
            --ump-navy-dark: #0b1522;
            --ump-gold: #f59e0b;
            --ump-gold-hover: #d97706;
            --ump-light: #f8fafc;
        }

        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--ump-light); color: #334155; margin: 0; padding: 0; }
        .portal-nav { background-color: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 0.85rem 2rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .nav-brand { display: flex; align-items: center; gap: 0.6rem; font-weight: 800; font-size: 1.25rem; color: var(--ump-navy); text-decoration: none; }
        .dashboard-layout { display: flex; min-height: calc(100vh - 65px); }
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; padding: 1.5rem 1rem; display: flex; flex-direction: column; gap: 0.4rem; }
        .sidebar a { display: flex; align-items: center; gap: 0.85rem; padding: 0.75rem 1rem; color: #475569; text-decoration: none; font-weight: 600; font-size: 0.95rem; border-radius: 8px; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #f1f5f9; color: var(--ump-navy); }
        .sidebar a.active { background-color: #fef3c7; color: var(--ump-gold-hover); }
        .main-content { flex: 1; padding: 2.5rem; max-width: calc(100% - 260px); box-sizing: border-box; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .metric-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
        .metric-card h3 { margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: 700; }
        .metric-card .number { font-size: 1.75rem; font-weight: 800; color: var(--ump-navy); margin: 0; }
        .section-title { color: var(--ump-navy); font-size: 1.35rem; font-weight: 700; margin: 2rem 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
        .table-container { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; overflow-x: auto; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; white-space: nowrap; }
        th, td { padding: 0.85rem 1.15rem; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f1f5f9; color: var(--ump-navy); font-weight: 700; }
        .btn-action { background-color: #dc2626; color: #ffffff; padding: 0.35rem 0.75rem; border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
        .btn-action:hover { background-color: #b91c1c; }
        .btn-approve { background-color: #16a34a; color: #ffffff; padding: 0.35rem 0.75rem; border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
        .btn-approve:hover { background-color: #15803d; }
        .btn-doc { background-color: var(--ump-navy); color: #ffffff; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .status-badge { padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <nav class="portal-nav">
        <a href="admin_dashboard.php" class="nav-brand"><i class="fa-solid fa-shield-halved" style="color: var(--ump-gold);"></i> NextStep ICT &mdash; Admin Portal</a>
        <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.95rem; font-weight: 600;">
            <span><i class="fa-solid fa-user-shield" style="color: var(--ump-gold);"></i> <?= htmlspecialchars($full_name) ?></span>
            <a href="logout.php" style="color: #dc2626; text-decoration: none;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>
    </nav>

    <div class="dashboard-layout">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="#tax-clearance"><i class="fa-solid fa-file-invoice-dollar"></i> Company Verifications</a>
            <a href="#student-docs"><i class="fa-solid fa-user-graduate"></i> Student & Graduate Docs</a>
            <a href="#users"><i class="fa-solid fa-users-gear"></i> Manage Users</a>
            <a href="#opportunities"><i class="fa-solid fa-briefcase"></i> Manage Opportunities</a>
            <a href="#reports"><i class="fa-solid fa-file-lines"></i> Generate Reports</a>
            <a href="logout.php" style="color: #dc2626; margin-top: auto;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>

        <div class="main-content">
            <div class="metrics-grid">
                <div class="metric-card"><h3>Total Users</h3><p class="number"><?= $users_count ?></p></div>
                <div class="metric-card"><h3>Active Opportunities</h3><p class="number"><?= $opps_count ?></p></div>
                <div class="metric-card"><h3>Applications</h3><p class="number"><?= $apps_count ?></p></div>
                <div class="metric-card"><h3>Companies</h3><p class="number"><?= $companies_count ?></p></div>
            </div>

            <?php if (!empty($success)): ?>
                <div style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; padding:1rem; border-radius:8px; margin-bottom: 1.5rem;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:1rem; border-radius:8px; margin-bottom: 1.5rem;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Company Tax Clearance & CIPC Verification -->
            <div id="tax-clearance" class="section-title"><i class="fa-solid fa-file-invoice-dollar" style="color: var(--ump-gold);"></i> Company Tax Clearance & CIPC Verification</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Industry</th>
                            <th>Contact Person</th>
                            <th>CIPC / Tax Document</th>
                            <th>Status</th>
                            <th>Action / Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($companies_result AND $companies_result->num_rows > 0): ?>
                            <?php while ($comp = $companies_result->fetch_assoc()): ?>
                                <?php 
                                    $is_verified = $comp['is_verified_by_admin'] ?? 0;
                                    $tax_status = ($is_verified == 1) ? 'Approved' : 'Pending';
                                    $st_class = ($is_verified == 1) ? 'status-approved' : 'status-pending';

                                    // Matches cipc_document_path from your register.php
                                    $doc_path = $comp['cipc_document_path'] ?? '';
                                ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--ump-navy);"><?= htmlspecialchars($comp['company_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($comp['industry'] ?? 'ICT') ?></td>
                                    <td><?= htmlspecialchars($comp['contact_person'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($doc_path)): ?>
                                            <a href="<?= htmlspecialchars($doc_path) ?>" target="_blank" class="btn-doc"><i class="fa-solid fa-file-pdf"></i> View Document</a>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.85rem;">No file attached</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-badge <?= $st_class ?>"><?= htmlspecialchars($tax_status) ?></span></td>
                                    <td>
                                        <div style="display: flex; gap: 0.4rem;">
                                            <form method="POST" action="admin_dashboard.php" style="margin:0;">
                                                <input type="hidden" name="update_tax_status" value="1">
                                                <input type="hidden" name="company_id" value="<?= $comp['company_id'] ?>">
                                                <input type="hidden" name="tax_status" value="Approved">
                                                <button type="submit" class="btn-approve"><i class="fa-solid fa-check"></i> Approve</button>
                                            </form>
                                            <form method="POST" action="admin_dashboard.php" style="margin:0;">
                                                <input type="hidden" name="update_tax_status" value="1">
                                                <input type="hidden" name="company_id" value="<?= $comp['company_id'] ?>">
                                                <input type="hidden" name="tax_status" value="Rejected">
                                                <button type="submit" class="btn-action"><i class="fa-solid fa-xmark"></i> Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 1.5rem;">No companies found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Publish Department Notice Form Card -->
<div class="card-panel" style="background: #ffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 2rem;">
    <h3 style="color: var(--ump-navy); margin-top: 0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fa-solid fa-bullhorn" style="color: var(--ump-orange);"></i> Publish Department Notice
    </h3>
    <form action="admin_dashboard.php" method="POST">
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #334155;">Notice Title</label>
            <input type="text" name="title" required class="form-control" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g. Work-Integrated Learning (WIL) Applications Open">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #334155;">Notice Content</label>
            <textarea name="message" rows="3" required class="form-control" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="Type announcement details here..."></textarea>
        </div>
        <button type="submit" name="post_announcement" style="background: #ff8c00; color: #ffffff; padding: 0.6rem 1.25rem; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
            <i class="fa-solid fa-paper-plane"></i> Publish Notice
        </button>
    </form>
</div>

<!-- Manage Published Notices (Toggle Button & Section) -->
<div style="grid-column: 1 / -1; width: 100%; margin-top: 1.5rem; margin-bottom: 2rem;">
    <div class="card-panel" style="background: #ffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; width: 100%; box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <h3 style="color: var(--ump-navy); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-list-check" style="color: var(--ump-orange);"></i> Manage Published Notices
            </h3>
            <button type="button" onclick="toggleNoticesTable()" id="toggleBtn" style="background: var(--ump-navy); color: #fff; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-chevron-down" id="toggleIcon"></i> <span id="toggleText">Show Management Table</span>
            </button>
        </div>

        <!-- Collapsible Content Wrapper -->
        <div id="noticesTableContainer" style="display: none; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
            <?php
            $manage_ann = $conn->query("SELECT * FROM announcements ORDER BY notice_id DESC");
            ?>

            <?php if ($manage_ann && $manage_ann->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569;">
                                <th style="padding: 0.75rem; width: 25%;">Title</th>
                                <th style="padding: 0.75rem; width: 45%;">Message</th>
                                <th style="padding: 0.75rem; width: 15%;">Date Posted</th>
                                <th style="padding: 0.75rem; width: 15%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $manage_ann->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <form action="admin_dashboard.php" method="POST">
                                        <input type="hidden" name="notice_id" value="<?php echo $row['notice_id']; ?>">
                                        <td style="padding: 0.75rem; vertical-align: top;">
                                            <input type="text" name="title" value="<?php echo htmlspecialchars($row['title']); ?>" required style="width: 100%; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px;">
                                        </td>
                                        <td style="padding: 0.75rem; vertical-align: top;">
                                            <textarea name="message" rows="2" required style="width: 100%; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px;"><?php echo htmlspecialchars($row['message']); ?></textarea>
                                        </td>
                                        <td style="padding: 0.75rem; vertical-align: top; color: #64748b; font-size: 0.8rem;">
                                            <?php echo $row['date'] ?? 'N/A'; ?>
                                        </td>
                                        <td style="padding: 0.75rem; vertical-align: top; text-align: center; white-space: nowrap;">
                                            <button type="submit" name="update_announcement" style="background: #0284c7; color: #fff; border: none; padding: 0.4rem 0.75rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem; margin-right: 0.25rem;" title="Save Changes">
                                                <i class="fa-solid fa-floppy-disk"></i> Save
                                            </button>
                                            <button type="submit" name="delete_announcement" onclick="return confirm('Are you sure you want to delete this notice?');" style="background: #dc2626; color: #fff; border: none; padding: 0.4rem 0.75rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" title="Delete Notice">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #64748b; text-align: center; padding: 1.5rem 0; margin: 0;">No announcements found in the database.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleNoticesTable() {
    const container = document.getElementById('noticesTableContainer');
    const text = document.getElementById('toggleText');
    const icon = document.getElementById('toggleIcon');
    
    if (container.style.display === 'none') {
        container.style.display = 'block';
        text.innerText = 'Hide Management Table';
        icon.className = 'fa-solid fa-chevron-up';
    } else {
        container.style.display = 'none';
        text.innerText = 'Show Management Table';
        icon.className = 'fa-solid fa-chevron-down';
    }
}
</script>
            <!-- Student Proof of Registration & Graduate Certificates -->
            <div id="student-docs" class="section-title"><i class="fa-solid fa-user-graduate" style="color: var(--ump-gold);"></i> Student Proof of Registration & Graduate Certificates</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Student Number</th>
                            <th>Academic Status</th>
                            <th>Qualification</th>
                            <th>Proof of Registration / Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students_result AND $students_result->num_rows > 0): ?>
                            <?php while ($std = $students_result->fetch_assoc()): ?>
                                <?php 
                                    $std_name = $std['full_name'] ?? 'Student';
                                    $std_num = $std['student_number'] ?? 'N/A';
                                    $std_status = $std['academic_status'] ?? 'Student';
                                    $std_qual = $std['qualification'] ?? 'DICT';
                                    
                                    // Matches proof_of_reg_path and certificate_path from your register.php
                                    $std_doc = ($std_status === 'Graduate') ? ($std['certificate_path'] ?? '') : ($std['proof_of_reg_path'] ?? '');
                                ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--ump-navy);"><?= htmlspecialchars($std_name) ?></td>
                                    <td><?= htmlspecialchars($std_num) ?></td>
                                    <td><span class="status-badge status-approved"><?= htmlspecialchars($std_status) ?></span></td>
                                    <td><?= htmlspecialchars($std_qual) ?></td>
                                    <td>
                                        <?php if (!empty($std_doc)): ?>
                                            <a href="<?= htmlspecialchars($std_doc) ?>" target="_blank" class="btn-doc"><i class="fa-solid fa-file-pdf"></i> View Document</a>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.85rem;">No document attached</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 1.5rem;">No student registration records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Manage Users Section -->
            <div id="users" class="section-title"><i class="fa-solid fa-users-gear"></i> Manage System Users</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_result AND $users_result->num_rows > 0): ?>
                            <?php while ($usr = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $usr['user_id'] ?></td>
                                    <td><?= htmlspecialchars($usr['full_name']) ?></td>
                                    <td><?= htmlspecialchars($usr['email']) ?></td>
                                    <td><span class="status-badge status-pending"><?= htmlspecialchars(strtoupper($usr['role'])) ?></span></td>
                                    <td>
                                        <?php if ((int)$usr['user_id'] !== (int)$user_id): ?>
                                            <form method="POST" action="admin_dashboard.php" onsubmit="return confirm('Delete this user?');" style="margin:0;">
                                                <input type="hidden" name="delete_user_id" value="<?= $usr['user_id'] ?>">
                                                <button type="submit" class="btn-action"><i class="fa-solid fa-trash"></i> Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 0.8rem; font-weight:700;">Current Admin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Manage Opportunities Section -->
            <div id="opportunities" class="section-title"><i class="fa-solid fa-briefcase"></i> Manage Opportunities</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Position Title</th>
                            <th>Company</th>
                            <th>Category</th>
                            <th>Closing Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($opps_result AND $opps_result->num_rows > 0): ?>
                            <?php while ($op = $opps_result->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--ump-navy);"><?= htmlspecialchars($op['title']) ?></td>
                                    <td><?= htmlspecialchars($op['company_name']) ?></td>
                                    <td><?= htmlspecialchars($op['category'] ?? 'ICT Position') ?></td>
                                    <td><?= htmlspecialchars($op['closingDate']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 1.5rem;">No active opportunities posted yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Generate Reports Section -->
            <div id="reports" class="section-title"><i class="fa-solid fa-file-lines"></i> System Reports & Administration</div>
            <div style="background: #ffffff; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h4 style="margin: 0 0 0.25rem 0; color: var(--ump-navy);">Platform Activity & Placement Summary Report</h4>
                    <p style="margin: 0; font-size: 0.85rem; color: #64748b;">Generate and download aggregate system statistics and application metrics.</p>
                </div>
                <button onclick="alert('Generating system report...');" class="btn-action" style="background-color: var(--ump-navy); padding: 0.65rem 1.25rem; font-size: 0.9rem;"><i class="fa-solid fa-download"></i> Download Report</button>
            </div>
        </div>
    </div>
</body>
</html>