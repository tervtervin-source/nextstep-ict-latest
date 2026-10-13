<?php
session_start();
include('config/db.php');

// Ensure only lecturers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch lecturer details
$stmt_lecturer = $conn->prepare("SELECT * FROM lecturers WHERE user_id = ?");
$stmt_lecturer->bind_param("i", $user_id);
$stmt_lecturer->execute();
$lecturer = $stmt_lecturer->get_result()->fetch_assoc();

$lecturer_id = $lecturer['lecturer_id'] ?? $user_id;
$lecturer_name = $lecturer['full_name'] ?? $_SESSION['full_name'] ?? 'Lecturer';

// Handle Announcement Deletion (Management)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notice_id = $_GET['delete'];
    $stmt_del = $conn->prepare("DELETE FROM announcements WHERE notice_id = ?");
    if ($stmt_del) {
        $stmt_del->bind_param("i", $notice_id);
        $stmt_del->execute();
        $stmt_del->close();
        header("Location: lecturer_dashboard.php?deleted=1");
        exit();
    }
}

// Handle Announcement Submission matching your exact columns
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['broadcast_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!empty($title) && !empty($message)) {
        // Check if author columns exist in announcements table or insert standard fields
        $stmt = $conn->prepare("INSERT INTO announcements (title, message, author_user_id, author_name) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssis", $title, $message, $user_id, $lecturer_name);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: lecturer_dashboard.php?success=1");
                exit();
            }
        } else {
            // Fallback if author columns aren't present in table yet
            $stmt_fallback = $conn->prepare("INSERT INTO announcements (title, message) VALUES (?, ?)");
            if ($stmt_fallback) {
                $stmt_fallback->bind_param("ss", $title, $message);
                $stmt_fallback->execute();
                $stmt_fallback->close();
                header("Location: lecturer_dashboard.php?success=1");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextStep ICT — Lecturer Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #0f172a;
            --ump-gold: #f59e0b;
            --bg-light: #f8fafc;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: var(--bg-light);
            color: #334155;
        }
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 260px;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 1.5rem;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--ump-navy);
            border-bottom: 1px solid #e2e8f0;
        }
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
            flex-grow: 1;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar-menu li.active a, .sidebar-menu li a:hover {
            background: #f1f5f9;
            color: var(--ump-navy);
            border-left: 4px solid var(--ump-gold);
        }
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: #ffffff;
            padding: 1rem 2rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: var(--ump-navy);
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        .btn-primary {
            background: var(--ump-gold);
            color: #ffffff;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #d97706;
        }
        .btn-danger {
            background: #ef4444;
            color: #ffffff;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            margin-bottom: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--ump-navy);
        }
        .badge {
            background: #e2e8f0;
            color: #334155;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">NextStep ICT</div>
        <ul class="sidebar-menu">
            <li class="active"><a href="lecturer_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
            <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header-bar">
            <div>
                <h2 style="margin: 0; color: var(--ump-navy);">NextStep ICT — Lecturer Portal</h2>
                <span style="font-size: 0.85rem; color: #64748b;">Logged in as: <strong><?php echo htmlspecialchars($lecturer_name); ?></strong> (Lecturer ID: <?php echo htmlspecialchars($lecturer_id); ?>)</span>
            </div>
            <span class="badge" style="background: #fef3c7; color: #d97706; font-size: 0.9rem; padding: 0.5rem 1rem;">
                <i class="fa-solid fa-chalkboard-user"></i> ID: <?php echo htmlspecialchars($lecturer_id); ?>
            </span>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Announcement broadcasted successfully! It is now live on student dashboards.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert-success" style="background: #fee2e2; color: #991b1b; border-color: #fecaca;">
                <i class="fa-solid fa-trash"></i> Announcement deleted successfully.
            </div>
        <?php endif; ?>

        <!-- Post Announcement Form -->
        <div class="card">
            <h3 style="margin-top: 0; color: var(--ump-navy);"><i class="fa-solid fa-bullhorn" style="color: var(--ump-gold);"></i> Post Student Announcement & Guidance</h3>
            <form method="POST" action="lecturer_dashboard.php">
                <div class="form-group">
                    <label>Announcement Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Important Notice on ICT In-Service Training" required>
                </div>
                <div class="form-group">
                    <label>Message / Guidance Details</label>
                    <textarea name="message" class="form-control" placeholder="Enter guidance message or announcements for students..." required></textarea>
                </div>
                <button type="submit" name="broadcast_announcement" value="1" class="btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Broadcast Announcement
                </button>
            </form>
        </div>

        <!-- Enrolled Students List -->
        <div class="card">
            <h3 style="margin-top: 0; color: var(--ump-navy);"><i class="fa-solid fa-graduation-cap" style="color: var(--ump-gold);"></i> Enrolled Students List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Student Number</th>
                        <th>Qualification</th>
                        <th>Academic Status</th>
                        <th>Email Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $students_res = $conn->query("
                        SELECT s.*, u.full_name, u.email 
                        FROM students s 
                        JOIN users u ON s.user_id = u.user_id
                    ");

                    if ($students_res && $students_res->num_rows > 0):
                        while ($stu = $students_res->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stu['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($stu['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($stu['qualification']); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($stu['academic_status']); ?></span></td>
                            <td><?php echo htmlspecialchars($stu['email']); ?></td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr><td colspan="5" style="text-align: center; color: #64748b;">No enrolled students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Posted Announcements History & Management -->
        <div class="card">
            <h3 style="margin-top: 0; color: var(--ump-navy);"><i class="fa-solid fa-clock-rotate-left" style="color: var(--ump-gold);"></i> Manage Posted Announcements</h3>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Date Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ann_history = $conn->query("SELECT * FROM announcements ORDER BY date DESC");
                    if ($ann_history && $ann_history->num_rows > 0):
                        while ($ann = $ann_history->fetch_assoc()):
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--ump-navy);"><?php echo htmlspecialchars($ann['title']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($ann['message'])); ?></td>
                            <td><?php echo htmlspecialchars($ann['date'] ?? $ann['date_posted'] ?? ''); ?></td>
                            <td>
                                <a href="lecturer_dashboard.php?delete=<?php echo $ann['notice_id']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr><td colspan="4" style="text-align: center; color: #64748b;">No announcements posted yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

</body>
</html>