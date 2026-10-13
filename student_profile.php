<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch user data from users table securely
$stmt_user = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
if ($stmt_user === false) {
    die("Users select error: " . htmlspecialchars($conn->error));
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();

$full_name = $user_data['full_name'] ?? '';
$email     = $user_data['email'] ?? '';

// Fetch student specific data
$stmt_std = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
if ($stmt_std === false) {
    die("Students select error: " . htmlspecialchars($conn->error));
}
$stmt_std->bind_param("i", $user_id);
$stmt_std->execute();
$student = $stmt_std->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_name   = trim($_POST['full_name'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $qualification  = trim($_POST['qualification'] ?? '');
    $year_of_study  = trim($_POST['year_of_study'] ?? '1st Year');

    // Handle Profile Picture Upload
    $profile_pic_path = $student['profile_pic'] ?? null;

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['profile_pic']['tmp_name'];
        $fileName      = $_FILES['profile_pic']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'profile_' . $user_id . '_' . time() . '.' . $fileExtension;
            $uploadFileDir = 'uploads/profile_pics/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $profile_pic_path = $dest_path;

                // Update profile_pic in students table immediately
                $stmt_pic = $conn->prepare("UPDATE students SET profile_pic = ? WHERE user_id = ?");
                if ($stmt_pic) {
                    $stmt_pic->bind_param("si", $profile_pic_path, $user_id);
                    $stmt_pic->execute();
                }
            } else {
                $error = 'Error moving uploaded file to destination directory.';
            }
        } else {
            $error = 'Invalid image format. Allowed formats: JPG, PNG, WEBP.';
        }
    }

    if (empty($error)) {
        // 1. Update students table (only columns that exist: student_number, qualification, year_of_study)
        $update_query = $conn->prepare("UPDATE students SET student_number = ?, qualification = ?, year_of_study = ? WHERE user_id = ?");
        if ($update_query) {
            $update_query->bind_param("sssi", $student_number, $qualification, $year_of_study, $user_id);
        } else {
            die("Students table error: " . htmlspecialchars($conn->error));
        }

        // 2. Update users table (full_name)
        $update_user = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        if ($update_user) {
            $update_user->bind_param("si", $updated_name, $user_id);
        } else {
            die("Users table error: " . htmlspecialchars($conn->error));
        }

        // 3. Execute both statements
        if ($update_query->execute() && $update_user->execute()) {
            $_SESSION['full_name'] = $updated_name;
            $full_name = $updated_name;
            $success = 'Profile updated successfully!';

            // Refresh student record
            $stmt_std->execute();
            $student = $stmt_std->get_result()->fetch_assoc();
        } else {
            $error = 'Failed to update profile database record.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | NextStep ICT Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #0f172a;
            --ump-gold: #d97706;
            --ump-gold-hover: #b45309;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            color: #334155;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background-color: #ffffff;
            border-right: 1px solid var(--border-color);
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--ump-navy);
            margin-bottom: 2rem;
            padding: 0 0.5rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #f1f5f9;
            color: var(--ump-navy);
        }

        .sidebar-menu a.active {
            background-color: #fef3c7;
            color: var(--ump-gold-hover);
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .top-navbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-profile-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--ump-navy);
        }

        .sign-out-btn {
            color: #ef4444;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Form & Profile Styling */
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--ump-gold);
        }

        .profile-avatar-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #94a3b8;
            border: 3px solid var(--ump-gold);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--ump-navy);
            margin-bottom: 0.4rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--ump-gold);
        }

        .btn-submit {
            background-color: var(--ump-navy);
            color: #ffffff;
            padding: 0.8rem 1.75rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background-color: #1e293b;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-graduation-cap" style="color: var(--ump-gold); font-size: 1.5rem;"></i>
            <span>NextStep ICT Portal</span>
        </div>

        <ul class="sidebar-menu">
            <li><a href="student_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="cv_builder.php"><i class="fa-solid fa-file-pen"></i> CV Builder & Documents</a></li>
            <li><a href="student_profile.php" class="active"><i class="fa-solid fa-user"></i> My Profile</a></li>
            <li><a href="student_dashboard.php#opportunities"><i class="fa-solid fa-briefcase"></i> Opportunities</a></li>
            <li><a href="student_dashboard.php#applications"><i class="fa-solid fa-paper-plane"></i> Applications</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="top-navbar">
            <div class="user-profile-badge">
                <i class="fa-solid fa-user-graduate" style="color: var(--ump-gold);"></i>
                <span><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <a href="logout.php" class="sign-out-btn"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>

        <div class="profile-container">
            <h2 style="color: var(--ump-navy); margin-top: 0; margin-bottom: 1.5rem;">Student Profile Management</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="profile-header">
                <?php if (!empty($student['profile_pic']) && file_exists($student['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($student['profile_pic']); ?>" alt="Profile Picture" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <i class="fa-solid fa-user"></i>
                    </div>
                <?php endif; ?>

                <div>
                    <h3 style="margin: 0 0 0.25rem 0; color: var(--ump-navy);"><?php echo htmlspecialchars($full_name); ?></h3>
                    <p style="margin: 0 0 0.5rem 0; color: #64748b;"><?php echo htmlspecialchars($email); ?></p>
                    <span style="background: #fef3c7; color: #b45309; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.8rem; font-weight: 700;">
                        University of Mpumalanga • ICT Student
                    </span>
                </div>
            </div>

            <form action="student_profile.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required>
                </div>

                <div class="form-group">
                    <label for="student_number">Student Number</label>
                    <input type="text" id="student_number" name="student_number" class="form-control" value="<?php echo htmlspecialchars($student['student_number'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="qualification">Academic Qualification / Program</label>
                    <input type="text" id="qualification" name="qualification" class="form-control" value="<?php echo htmlspecialchars($student['qualification'] ?? ''); ?>" placeholder="e.g. Diploma in ICT" required>
                </div>

                <div class="form-group">
                    <label for="year_of_study">Year of Study</label>
                    <select id="year_of_study" name="year_of_study" class="form-control">
                        <option value="1st Year" <?php echo ($student['year_of_study'] ?? '') === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2nd Year" <?php echo ($student['year_of_study'] ?? '') === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3rd Year" <?php echo ($student['year_of_study'] ?? '') === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="Advanced Diploma" <?php echo ($student['year_of_study'] ?? '') === 'Advanced Diploma' ? 'selected' : ''; ?>>Advanced Diploma</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="profile_pic">Update Profile Picture / Headshot</label>
                    <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*">
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Save Profile Changes</button>
                </div>
            </form>
        </div>

        <footer style="background-color: var(--ump-navy); color: #ffffff; padding: 1.5rem; text-align: center; border-radius: 12px; margin-top: 3rem;">
            <p style="margin: 0 0 0.25rem 0; font-weight: 600;">University of Mpumalanga — NextStep ICT Portal</p>
            <p style="margin: 0; font-size: 0.85rem; color: #cbd5e1;">&copy; 2026 School of Computing and Mathematical Sciences. All rights reserved.</p>
        </footer>
    </div>
</div>

</body>
</html>