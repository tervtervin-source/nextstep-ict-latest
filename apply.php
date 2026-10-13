<?php
session_start();
require_once 'config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$opportunity_id = $_GET['opportunity_id'] ?? null;
$error = '';
$success = '';

if (!$opportunity_id) {
    header("Location: student_dashboard.php");
    exit();
}

// 1. Ensure a valid student record exists
$student_id = null;
$stmt_check = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
if ($stmt_check) {
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    if ($res_check && $res_check->num_rows > 0) {
        $row = $res_check->fetch_assoc();
        $student_id = $row['student_id'];
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO students (user_id) VALUES (?)");
        if ($stmt_ins) {
            $stmt_ins->bind_param("i", $user_id);
            $stmt_ins->execute();
            $student_id = $conn->insert_id;
        }
    }
}

if (!$student_id) {
    $student_id = $user_id;
}

// Check if student already applied for this position
$already_applied = false;
$existing_app = null;
$stmt_already = $conn->prepare("SELECT * FROM applications WHERE opportunity_id = ? AND student_id = ?");
if ($stmt_already) {
    $stmt_already->bind_param("ii", $opportunity_id, $student_id);
    $stmt_already->execute();
    $res_already = $stmt_already->get_result();
    if ($res_already && $res_already->num_rows > 0) {
        $already_applied = true;
        $existing_app = $res_already->fetch_assoc();
    }
}

// Fetch opportunity details
$stmt = $conn->prepare("SELECT o.*, c.company_name FROM opportunities o JOIN companies c ON o.company_id = c.company_id WHERE o.opportunity_id = ?");
$stmt->bind_param("i", $opportunity_id);
$stmt->execute();
$opp_res = $stmt->get_result();

if ($opp_res->num_rows === 0) {
    header("Location: student_dashboard.php");
    exit();
}
$opportunity = $opp_res->fetch_assoc();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $upload_dir = 'uploads/transcripts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $transcript_destination = $existing_app['transcript_path'] ?? null;
    $cv_destination = $existing_app['cv_path'] ?? null;

    // Handle Transcript Upload
    if (isset($_FILES['transcript']) && $_FILES['transcript']['error'] === UPLOAD_ERR_OK) {
        $t_ext = strtolower(pathinfo($_FILES['transcript']['name'], PATHINFO_EXTENSION));
        if (in_array($t_ext, ['pdf', 'doc', 'docx'])) {
            $transcript_destination = $upload_dir . 'transcript_' . $student_id . '_' . time() . '.' . $t_ext;
            move_uploaded_file($_FILES['transcript']['tmp_name'], $transcript_destination);
        } else {
            $error = 'Invalid transcript file format. Use PDF or Word.';
        }
    }

    // Handle CV Upload
    if (empty($error) && isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $cv_ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        if (in_array($cv_ext, ['pdf', 'doc', 'docx'])) {
            $cv_destination = $upload_dir . 'cv_' . $student_id . '_' . time() . '.' . $cv_ext;
            move_uploaded_file($_FILES['cv']['tmp_name'], $cv_destination);
        } else {
            $error = 'Invalid CV file format. Use PDF or Word.';
        }
    }

    if (empty($error)) {
        if ($already_applied) {
            // Update existing application record
            $stmt_app = $conn->prepare("UPDATE applications SET transcript_path = ?, cv_path = ?, date_applied = NOW() WHERE opportunity_id = ? AND student_id = ?");
            if ($stmt_app) {
                $stmt_app->bind_param("ssii", $transcript_destination, $cv_destination, $opportunity_id, $student_id);
                if ($stmt_app->execute()) {
                    $success = 'Your application and uploaded documents have been updated!';
                } else {
                    $error = 'Database error: ' . $stmt_app->error;
                }
            }
        } else {
            // Insert new application record
            if ($transcript_destination) {
                $stmt_app = $conn->prepare("INSERT INTO applications (opportunity_id, student_id, transcript_path, cv_path, status, date_applied) VALUES (?, ?, ?, ?, 'Pending', NOW())");
                if ($stmt_app) {
                    $stmt_app->bind_param("iiss", $opportunity_id, $student_id, $transcript_destination, $cv_destination);
                    if ($stmt_app->execute()) {
                        $success = 'Application, Transcript, and CV submitted successfully!';
                    } else {
                        $error = 'Database error: ' . $stmt_app->error;
                    }
                }
            } else {
                $error = 'Please upload your academic transcript.';
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
    <title>Apply for <?php echo htmlspecialchars($opportunity['title']); ?> | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ump-navy: #132238; --ump-gold: #f59e0b; --ump-gold-hover: #d97706; --ump-light: #f8fafc; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--ump-light); color: #334155; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 500px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); box-sizing: border-box; }
        .back-link { display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 600; margin-bottom: 1.5rem; }
        .back-link:hover { color: var(--ump-navy); }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--ump-navy); }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; box-sizing: border-box; }
        .btn-submit { background-color: var(--ump-gold); color: #ffffff; width: 100%; padding: 0.85rem; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem; margin-top: 1rem; }
        .btn-submit:hover { background-color: var(--ump-gold-hover); }
        .info-box { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 0.85rem 1rem; border-radius: 10px; font-size: 0.85rem; margin-bottom: 1.25rem; }
    </style>
</head>
<body>
    <div class="card">
        <a href="student_dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        
        <h2 style="color: var(--ump-navy); margin-top:0; font-size:1.5rem;"><?php echo htmlspecialchars($opportunity['title']); ?></h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">Company: <strong><?php echo htmlspecialchars($opportunity['company_name']); ?></strong></p>

        <?php if ($already_applied && empty($success)): ?>
            <div class="info-box">
                <i class="fa-solid fa-circle-info"></i> You have already applied for this position. Submitting again will update your uploaded transcript and CV documents.
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background:#fef2f2; color:#991b1b; padding:0.75rem; border-radius:8px; margin-bottom: 1rem; font-size:0.85rem;"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background:#f0fdf4; color:#166534; padding:0.75rem; border-radius:8px; margin-bottom: 1rem; font-size:0.85rem;"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
            <a href="student_dashboard.php" style="display:block; text-align:center; background:var(--ump-navy); color:#fff; padding:0.75rem; border-radius:8px; text-decoration:none; font-weight:600; margin-top:1rem;">Return to Dashboard</a>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Upload Academic Transcript (.pdf, .doc, .docx)</label>
                    <input type="file" name="transcript" class="form-control" accept=".pdf,.doc,.docx" <?php echo $already_applied ? '' : 'required'; ?>>
                </div>
                <div class="form-group">
                    <label>Upload CV / Resume (.pdf, .doc, .docx)</label>
                    <input type="file" name="cv" class="form-control" accept=".pdf,.doc,.docx">
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-upload"></i> <?php echo $already_applied ? 'Update Application Documents' : 'Submit Application & Documents'; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>