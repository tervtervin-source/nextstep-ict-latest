<?php
session_start();
require_once 'config/db.php';

// Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = '';
$active_portal = isset($_POST['portal']) ? $_POST['portal'] : 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $portal = trim($_POST['portal'] ?? 'user');

    if (!empty($email) && !empty($password)) {
        
        // Flexible query that won't crash if optional columns are missing
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Support multiple possible password column names
                $hashed_pw = $user['password_hash'] ?? ($user['password'] ?? '');

                // Verify password
                if (password_verify($password, $hashed_pw) || $password === $hashed_pw) {
                    $role = strtolower($user['role'] ?? 'student');

                    // Portal Mode Check
                    if ($portal === 'admin' && $role !== 'admin') {
                        $error = 'Access denied. Administrator privileges required.';
                    } elseif ($portal === 'user' && $role === 'admin') {
                        $error = 'Administrator accounts must sign in using the Administrator tab.';
                    } else {

                        // 1. Check Email OTP Verification (if column exists and is 0)
                        if (array_key_exists('is_email_verified', $user) && intval($user['is_email_verified']) === 0) {
                            $_SESSION['pending_email'] = $email;
                            header("Location: verify_otp.php");
                            exit();
                        }

                        // 2. Admin Verification Check for Employers/Companies
                        if ($role === 'company' || $role === 'employer') {
                            $uid = $user['user_id'] ?? $user['id'];
                            
                            $stmt_comp = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
                            if ($stmt_comp) {
                                $stmt_comp->bind_param("i", $uid);
                                $stmt_comp->execute();
                                $comp_res = $stmt_comp->get_result();
                                
                                if ($comp_res && $comp_res->num_rows > 0) {
                                    $comp = $comp_res->fetch_assoc();
                                    $is_approved = $comp['is_verified_by_admin'] ?? ($comp['is_company_approved'] ?? 0);
                                    
                                    if (intval($is_approved) !== 1) {
                                        $error = 'Your company account is pending approval by the UMP Administrator. You cannot log in yet.';
                                    }
                                } else {
                                    $error = 'Company profile and documents are pending administrator verification.';
                                }
                            }
                        }

                        // Log in if no errors
                        if (empty($error)) {
                            $_SESSION['user_id'] = $user['user_id'] ?? $user['id'];
                            $_SESSION['full_name'] = $user['full_name'] ?? $user['name'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['email'] = $email;

                            if ($role === 'admin') {
                                header("Location: admin_dashboard.php");
                            } elseif ($role === 'company' || $role === 'employer') {
                                header("Location: company_dashboard.php");
                            } elseif ($role === 'lecturer') {
                                header("Location: lecturer_dashboard.php");
                            } else {
                                header("Location: student_dashboard.php");
                            }
                            exit();
                        }
                    }
                } else {
                    $error = 'Invalid email address or password.';
                }
            } else {
                $error = 'No account found with that email address.';
            }
        } else {
            // Print exact MySQL error if prepare fails
            $error = 'Database query failed: ' . $conn->error;
        }
    } else {
        $error = 'Please fill in both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #132238;
            --ump-gold: #f59e0b;
            --ump-gold-hover: #d97706;
            --ump-light: #f8fafc;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--ump-light); color: #334155; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 440px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); box-sizing: border-box; }
        .home-link { display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 600; margin-bottom: 1.5rem; }
        .home-link:hover { color: var(--ump-navy); }
        
        .portal-toggle { display: flex; background: #f1f5f9; border-radius: 10px; padding: 4px; margin-bottom: 1.75rem; }
        .portal-btn { flex: 1; padding: 0.65rem 0; border: none; background: transparent; font-size: 0.9rem; font-weight: 700; color: #64748b; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .portal-btn.active { background: #ffffff; color: var(--ump-navy); box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--ump-navy); }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 1rem; color: #94a3b8; font-size: 0.95rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; box-sizing: border-box; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--ump-gold); }
        
        .forgot-link { display: block; text-align: right; color: #64748b; font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-top: 0.4rem; }
        .forgot-link:hover { color: var(--ump-gold-hover); }
        
        .btn-submit { background-color: var(--ump-gold); color: #ffffff; width: 100%; padding: 0.85rem; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem; margin-top: 1rem; transition: background 0.2s; }
        .btn-submit:hover { background-color: var(--ump-gold-hover); }
    </style>
</head>
<body>

    <div class="login-card">
        <a href="index.php" class="home-link"><i class="fa-solid fa-arrow-left"></i> Home</a>

        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="background: #fef3c7; width: 50px; height: 50px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-shield-halved" style="font-size: 1.5rem; color: var(--ump-gold-hover);"></i>
            </div>
            <h2 style="color: var(--ump-navy); margin: 0; font-size: 1.6rem; font-weight: 800;">Sign In</h2>
            <p style="color: #64748b; font-size: 0.85rem; margin-top: 0.25rem;">NextStep ICT Portal &mdash; UMP</p>
        </div>

        <div class="portal-toggle">
            <button type="button" class="portal-btn <?php echo ($active_portal === 'user') ? 'active' : ''; ?>" id="btn-user" onclick="switchPortal('user')">User Portal</button>
            <button type="button" class="portal-btn <?php echo ($active_portal === 'admin') ? 'active' : ''; ?>" id="btn-admin" onclick="switchPortal('admin')">Administrator</button>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:0.85rem 1rem; border-radius:10px; margin-bottom: 1.25rem; font-size: 0.85rem; line-height: 1.4;">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 0.3rem;"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="portal" id="portal-input" value="<?php echo htmlspecialchars($active_portal); ?>">

            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="e.g. user@ump.ac.za" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="••••••••••••" required>
                </div>
                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-submit"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
        </form>

        <p style="text-align: center; margin-top: 1.75rem; font-size: 0.85rem; color: #64748b;">
            Don't have an account? <a href="register.php" style="color: var(--ump-gold-hover); font-weight: 700; text-decoration: none;">Register here</a>
        </p>
    </div>

    <script>
        function switchPortal(portal) {
            document.getElementById('portal-input').value = portal;
            if (portal === 'user') {
                document.getElementById('btn-user').classList.add('active');
                document.getElementById('btn-admin').classList.remove('active');
            } else {
                document.getElementById('btn-admin').classList.add('active');
                document.getElementById('btn-user').classList.remove('active');
            }
        }
    </script>

</body>
</html>