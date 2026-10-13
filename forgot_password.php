<?php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Save token to database
                $stmt_token = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                if ($stmt_token) {
                    $stmt_token->bind_param("sss", $email, $token, $expiry);
                    $stmt_token->execute();
                }

                $success = 'If an account exists with that email, password reset instructions have been sent.';
            } else {
                // For security, display the same message even if email is not found
                $success = 'If an account exists with that email, password reset instructions have been sent.';
            }
        }
    } else {
        $error = 'Please enter your email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #132238;
            --ump-gold: #f59e0b;
            --ump-gold-hover: #d97706;
            --ump-light: #f8fafc;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--ump-light); color: #334155; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 440px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); box-sizing: border-box; }
        .back-link { display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 600; margin-bottom: 1.5rem; }
        .back-link:hover { color: var(--ump-navy); }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--ump-navy); }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 1rem; color: #94a3b8; font-size: 0.95rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; box-sizing: border-box; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--ump-gold); }
        .btn-submit { background-color: var(--ump-gold); color: #ffffff; width: 100%; padding: 0.85rem; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem; margin-top: 1rem; transition: background 0.2s; }
        .btn-submit:hover { background-color: var(--ump-gold-hover); }
    </style>
</head>
<body>

    <div class="card">
        <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Sign In</a>

        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="background: #fef3c7; width: 50px; height: 50px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-key" style="font-size: 1.5rem; color: var(--ump-gold-hover);"></i>
            </div>
            <h2 style="color: var(--ump-navy); margin: 0; font-size: 1.6rem; font-weight: 800;">Reset Password</h2>
            <p style="color: #64748b; font-size: 0.85rem; margin-top: 0.25rem;">Enter your email to receive a recovery link</p>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:0.85rem 1rem; border-radius:10px; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; padding:0.85rem 1rem; border-radius:10px; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="e.g. user@ump.ac.za" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Send Reset Link</button>
        </form>
    </div>

</body>
</html>