<?php
session_start();
require_once 'config/db.php';
require_once 'config/mailer.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Full Name determination based on role
    $full_name = trim($_POST['full_name'] ?? '');
    if ($role === 'company') {
        $full_name = trim($_POST['contact_person'] ?? '');
    }

    // Password Complexity Rules
    $has_letter = preg_match('/[a-zA-Z]/', $password);
    $has_number = preg_match('/[0-9]/', $password);
    $has_special = preg_match('/[\W_]/', $password);

    if (!in_array($role, ['student', 'company', 'lecturer'])) {
        $error = 'Invalid user role selected.';
    } elseif (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required account fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match. Please re-type your password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!$has_letter || !$has_number || !$has_special) {
        $error = 'Password must contain a mix of letters, numbers, and special characters.';
    } else {
        // Check email uniqueness
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'An account with this email address already exists.';
        } else {
            $upload_dir = 'uploads/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $cert_file_path = null;
            $por_file_path = null;
            $cipc_file_path = null;
            $academic_status = $_POST['academic_status'] ?? 'Student';

            // Document Upload Logic & Validation
            if ($role === 'student') {
                if ($academic_status === 'Graduate') {
                    if (!isset($_FILES['certificate_file']) || $_FILES['certificate_file']['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Graduates are required to upload their Graduation Certificate or Academic Record.';
                    } else {
                        $file_ext = strtolower(pathinfo($_FILES['certificate_file']['name'], PATHINFO_EXTENSION));
                        if ($file_ext !== 'pdf') {
                            $error = 'Graduation Certificate must be strictly in PDF format.';
                        } else {
                            $cert_filename = 'CERT_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['certificate_file']['name']);
                            $cert_file_path = $upload_dir . $cert_filename;
                            move_uploaded_file($_FILES['certificate_file']['tmp_name'], $cert_file_path);
                        }
                    }
                } elseif ($academic_status === 'Student') {
                    if (!isset($_FILES['por_file']) || $_FILES['por_file']['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Students are required to upload a valid Proof of Registration.';
                    } else {
                        $file_ext = strtolower(pathinfo($_FILES['por_file']['name'], PATHINFO_EXTENSION));
                        if ($file_ext !== 'pdf') {
                            $error = 'Proof of Registration must be strictly in PDF format.';
                        } else {
                            $por_filename = 'POR_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['por_file']['name']);
                            $por_file_path = $upload_dir . $por_filename;
                            move_uploaded_file($_FILES['por_file']['tmp_name'], $por_file_path);
                        }
                    }
                }
            } elseif ($role === 'company') {
                if (!isset($_FILES['cipc_file']) || $_FILES['cipc_file']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Employers are required to upload a CIPC Registration Document or Tax Clearance (PDF only).';
                } else {
                    $file_ext = strtolower(pathinfo($_FILES['cipc_file']['name'], PATHINFO_EXTENSION));
                    if ($file_ext !== 'pdf') {
                        $error = 'CIPC Document must be strictly in PDF format.';
                    } else {
                        $cipc_filename = 'CIPC_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['cipc_file']['name']);
                        $cipc_file_path = $upload_dir . $cipc_filename;
                        move_uploaded_file($_FILES['cipc_file']['tmp_name'], $cipc_file_path);
                    }
                }
            }

            if (empty($error)) {
                $is_company_approved = ($role === 'company') ? 0 : 1;
                
                // Generate 6-digit OTP code and 15-minute expiration
                $otp_code = sprintf("%06d", mt_rand(100000, 999999));
                $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $is_email_verified = 0;

                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, is_company_approved, otp_code, otp_expires_at, is_email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    die("SQL Error: " . $conn->error);
                }

                $stmt->bind_param("ssssissi", $full_name, $email, $password, $role, $is_company_approved, $otp_code, $otp_expires_at, $is_email_verified);

                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;

                    if ($role === 'student') {
                        $student_number = trim($_POST['student_number'] ?? '');
                        $qualification = $_POST['qualification'] ?? 'DICT';
                        $year_of_study = $_POST['year_of_study'] ?? '3';

                        $stmt_std = $conn->prepare("INSERT INTO students (user_id, student_number, qualification, year_of_study, academic_status, certificate_path, proof_of_reg_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt_std->bind_param("issssss", $user_id, $student_number, $qualification, $year_of_study, $academic_status, $cert_file_path, $por_file_path);
                        $stmt_std->execute();

                    } elseif ($role === 'company') {
                        $company_name = trim($_POST['company_name'] ?? '');
                        $registration_number = trim($_POST['registration_number'] ?? '');
                        $industry = trim($_POST['industry'] ?? '');
                        $contact_phone = trim($_POST['company_phone'] ?? '');
                        $website_url = trim($_POST['website_url'] ?? '');
                        $company_address = trim($_POST['company_address'] ?? '');
                        $job_title = trim($_POST['job_title'] ?? '');

                        $stmt_comp = $conn->prepare("INSERT INTO companies (user_id, company_name, contact_person, job_title, registration_number, industry, contact_phone, location, website_url, cipc_document_path, is_verified_by_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                        $stmt_comp->bind_param("isssssssss", $user_id, $company_name, $full_name, $job_title, $registration_number, $industry, $contact_phone, $company_address, $website_url, $cipc_file_path);
                        $stmt_comp->execute();

                    } elseif ($role === 'lecturer') {
                        $staff_number = trim($_POST['staff_number'] ?? '');
                        $department = trim($_POST['department'] ?? '');
                        $faculty = trim($_POST['faculty'] ?? '');
                        $lecturer_phone = trim($_POST['lecturer_phone'] ?? '');
                        $position = trim($_POST['position'] ?? '');

                        $stmt_lec = $conn->prepare("INSERT INTO lecturers (user_id, staff_number, department, faculty, phone_number, position) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_lec->bind_param("isssss", $user_id, $staff_number, $department, $faculty, $lecturer_phone, $position);
                        $stmt_lec->execute();
                    }

                    // Store pending session data and trigger OTP routing
                    $_SESSION['pending_user_id'] = $user_id;
                    $_SESSION['pending_email'] = $email;
                    $_SESSION['pending_name'] = $full_name;

                    // Send PHPMailer OTP Email
                    if (function_exists('sendOtpEmail')) {
                        sendOtpEmail($email, $full_name, $otp_code);
                    }

                    header("Location: verify_otp.php");
                    exit();
                } else {
                    $error = 'Registration failed: ' . $conn->error;
                }
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
    <title>Account Registration | NextStep ICT</title>
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

        .reg-container {
            max-width: 780px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        .reg-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
        }

        .reg-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .reg-header h1 {
            color: var(--ump-navy);
            font-size: 1.875rem;
            margin-bottom: 0.5rem;
        }

        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .role-card {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .role-card:hover {
            border-color: var(--ump-gold);
            background-color: #fffbeb;
        }

        .role-card.selected {
            border-color: var(--ump-navy);
            background-color: #f1f5f9;
        }

        .role-card i {
            font-size: 2rem;
            color: var(--ump-navy);
            margin-bottom: 0.5rem;
            display: block;
        }

        .role-card span {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--ump-navy);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
            color: var(--ump-navy);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--ump-gold);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .role-grid { grid-template-columns: 1fr; }
        }

        .doc-box {
            background-color: #f8fafc;
            border: 2px dashed #cbd5e1;
            padding: 1.25rem;
            border-radius: 8px;
            margin-top: 0.5rem;
        }

        .pwd-hint {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .btn-submit {
            width: 100%;
            background-color: var(--ump-gold);
            color: #ffffff;
            padding: 0.875rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background-color: var(--ump-gold-hover);
        }
    </style>
</head>
<body>

    <nav class="portal-nav">
        <a href="index.php" class="nav-brand">
            <i class="fa-solid fa-graduation-cap" style="color: var(--ump-gold);"></i> NextStep ICT
        </a>
        <div style="font-size: 0.95rem; font-weight: 600;">
            <a href="index.php" style="color: #475569; text-decoration: none; margin-right: 1.5rem;"><i class="fa-solid fa-house"></i> Home</a>
            <a href="login.php" style="color: var(--ump-navy); text-decoration: none;"><i class="fa-solid fa-right-to-bracket"></i> Sign In</a>
        </div>
    </nav>

    <div class="reg-container">
        <div class="reg-card">
            <div class="reg-header">
                <h1>Create Your Account</h1>
                <p style="color: #64748b; margin: 0;">Select your role to register on NextStep ICT</p>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Role Selection Cards -->
            <div style="margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #475569;">Registering as:</div>
            <div class="role-grid">
                <div class="role-card selected" onclick="selectRole('student', this)">
                    <i class="fa-solid fa-user-graduate"></i>
                    <span>Student / Graduate</span>
                </div>
                <div class="role-card" onclick="selectRole('company', this)">
                    <i class="fa-solid fa-building"></i>
                    <span>Employer</span>
                </div>
                <div class="role-card" onclick="selectRole('lecturer', this)">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Lecturer</span>
                </div>
            </div>

            <form action="register.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="role" id="selected_role" value="student">

                <!-- 1. STUDENT / GRADUATE FIELDS -->
                <div id="student_fields">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-user"></i> Full Name</label>
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Themba Sihlabela">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="name@domain.com">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-id-card"></i> Student / Alumnus Number</label>
                            <input type="text" name="student_number" class="form-control" placeholder="e.g. 219012345">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-graduation-cap"></i> Qualification</label>
                            <select name="qualification" class="form-control">
                                <option value="HICT">Higher Certificate in ICT (HICT)</option>
                                <option value="DICT" selected>Diploma in ICT (DICT)</option>
                                <option value="BICT">Bachelor of ICT (BICT)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-user-tag"></i> Academic Status</label>
                            <select name="academic_status" id="academic_status" class="form-control" onchange="toggleDocumentRequirements()">
                                <option value="Student" selected>Currently Enrolled Student</option>
                                <option value="Graduate">Graduated Alumnus</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-calendar-day"></i> Year of Study</label>
                            <select name="year_of_study" class="form-control">
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3" selected>3rd Year / Final Year</option>
                                <option value="Graduated">Graduated</option>
                            </select>
                        </div>
                    </div>

                    <!-- Student Document Box -->
                    <div id="student_doc_box" class="doc-box">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="color: var(--ump-navy);"><i class="fa-solid fa-file-pdf" style="color: #dc2626;"></i> Upload Proof of Registration (PDF only)</label>
                            <p style="font-size: 0.8rem; color: #64748b; margin: 0.2rem 0 0.5rem 0;">Enrolled students must upload a valid Proof of Registration in PDF format.</p>
                            <input type="file" name="por_file" id="por_file" accept=".pdf" class="form-control">
                        </div>
                    </div>

                    <!-- Graduate Document Box -->
                    <div id="graduate_doc_box" class="doc-box" style="display: none;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="color: var(--ump-navy);"><i class="fa-solid fa-certificate" style="color: var(--ump-gold-hover);"></i> Upload Graduation Certificate / Academic Record (PDF only)</label>
                            <p style="font-size: 0.8rem; color: #64748b; margin: 0.2rem 0 0.5rem 0;">Graduates must attach an official Graduation Certificate or Academic Record in PDF format.</p>
                            <input type="file" name="certificate_file" id="certificate_file" accept=".pdf" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- 2. EMPLOYER FIELDS -->
                <div id="company_fields" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-building"></i> Company Name</label>
                            <input type="text" name="company_name" class="form-control" placeholder="e.g. ABC Technologies Pty Ltd">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-file-contract"></i> Company Registration Number</label>
                            <input type="text" name="registration_number" class="form-control" placeholder="e.g. 2020/123456/07">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-industry"></i> Industry</label>
                            <input type="text" name="industry" class="form-control" placeholder="e.g. Information Technology / Software Development">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-envelope"></i> Company Email</label>
                            <input type="email" id="company_email" class="form-control" placeholder="hr@company.co.za" oninput="syncEmail(this.value)">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-phone"></i> Phone Number</label>
                            <input type="text" name="company_phone" class="form-control" placeholder="013 755 1234">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-globe"></i> Website</label>
                            <input type="url" name="website_url" class="form-control" placeholder="https://www.company.co.za">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-location-dot"></i> Company Address</label>
                        <input type="text" name="company_address" class="form-control" placeholder="e.g. Riverside Office Park, Mbombela, 1200">
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-user-tie"></i> Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="e.g. Jane Doe">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-briefcase"></i> Job Title</label>
                            <input type="text" name="job_title" class="form-control" placeholder="e.g. HR Manager / Lead Recruiter">
                        </div>
                    </div>

                    <!-- CIPC Upload -->
                    <div class="doc-box">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="color: var(--ump-navy);"><i class="fa-solid fa-file-pdf" style="color: #dc2626;"></i> Upload CIPC Document / Tax Clearance (PDF only)</label>
                            <p style="font-size: 0.8rem; color: #64748b; margin: 0.2rem 0 0.5rem 0;">Employers must attach an official CIPC registration document or Tax Clearance certificate in PDF format.</p>
                            <input type="file" name="cipc_file" id="cipc_file" accept=".pdf" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- 3. LECTURER FIELDS -->
                <div id="lecturer_fields" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-user"></i> Full Name</label>
                            <input type="text" id="lec_full_name" class="form-control" placeholder="e.g. Dr. S. Zakatha" oninput="syncName(this.value)">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-id-badge"></i> Staff Number</label>
                            <input type="text" name="staff_number" class="form-control" placeholder="e.g. STF-98765">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-sitemap"></i> Department</label>
                            <input type="text" name="department" class="form-control" value="Computer Science & ICT">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-building-columns"></i> Faculty</label>
                            <input type="text" name="faculty" class="form-control" value="Faculty of Agriculture and Natural Sciences">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-solid fa-envelope"></i> Work Email</label>
                            <input type="email" id="lec_email" class="form-control" placeholder="name@ump.ac.za" oninput="syncEmail(this.value)">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-phone"></i> Phone Number</label>
                            <input type="text" name="lecturer_phone" class="form-control" placeholder="013 002 0000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-user-gear"></i> Position</label>
                        <select name="position" class="form-control">
                            <option value="Program Leader">Program Leader</option>
                            <option value="Senior Lecturer">Senior Lecturer</option>
                            <option value="Lecturer" selected>Lecturer</option>
                            <option value="Junior Lecturer">Junior Lecturer</option>
                            <option value="Academic Support">Academic Support / Lab Technician</option>
                        </select>
                    </div>
                </div>

                <!-- PASSWORDS & GLOBAL HIDDEN INPUTS -->
                <div class="form-grid" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label><i class="fa-solid fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required minlength="8">
                        <div class="pwd-hint"><i class="fa-solid fa-circle-info"></i> Min 8 chars with letters, numbers & special characters.</div>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-key"></i> Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="8">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Register & Send OTP
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem;">
                Already have an account? <a href="login.php" style="color: var(--ump-navy); font-weight: 600; text-decoration: none;">Sign In here</a>
            </div>
        </div>
    </div>

    <script>
        function selectRole(role, element) {
            document.getElementById('selected_role').value = role;

            const cards = document.querySelectorAll('.role-card');
            cards.forEach(card => card.classList.remove('selected'));
            element.classList.add('selected');

            document.getElementById('student_fields').style.display = (role === 'student') ? 'block' : 'none';
            document.getElementById('company_fields').style.display = (role === 'company') ? 'block' : 'none';
            document.getElementById('lecturer_fields').style.display = (role === 'lecturer') ? 'block' : 'none';

            // Document Upload Requirements
            document.getElementById('cipc_file').required = (role === 'company');
            
            if (role === 'student') {
                toggleDocumentRequirements();
            } else {
                document.getElementById('por_file').required = false;
                document.getElementById('certificate_file').required = false;
            }
        }

        function syncName(val) {
            const currentRole = document.getElementById('selected_role').value;
            if (currentRole === 'lecturer') {
                document.querySelector('#student_fields input[name="full_name"]').value = val;
            }
        }

        function syncEmail(val) {
            const currentRole = document.getElementById('selected_role').value;
            if (currentRole === 'company' || currentRole === 'lecturer') {
                document.querySelector('#student_fields input[name="email"]').value = val;
            }
        }

        function toggleDocumentRequirements() {
            const role = document.getElementById('selected_role').value;
            if (role !== 'student') return;

            const status = document.getElementById('academic_status').value;
            const studentBox = document.getElementById('student_doc_box');
            const graduateBox = document.getElementById('graduate_doc_box');
            const porInput = document.getElementById('por_file');
            const certInput = document.getElementById('certificate_file');

            if (status === 'Student') {
                studentBox.style.display = 'block';
                graduateBox.style.display = 'none';
                porInput.required = true;
                certInput.required = false;
                certInput.value = '';
            } else {
                studentBox.style.display = 'none';
                graduateBox.style.display = 'block';
                porInput.required = false;
                certInput.required = true;
                porInput.value = '';
            }
        }

        toggleDocumentRequirements();
    </script>

</body>
</html>