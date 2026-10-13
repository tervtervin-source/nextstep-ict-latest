<?php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: register.php");
    exit();
}

$user_id = $_SESSION['pending_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp_code'] ?? '');

    if (empty($entered_otp)) {
        $error = 'Please enter the 6-digit OTP code sent to your email.';
    } else {
        $stmt = $conn->prepare("SELECT otp_code, otp_expires_at, role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if ($user['otp_code'] === $entered_otp) {
                if (strtotime($user['otp_expires_at']) >= time()) {
                    // OTP is valid - mark email as verified
                    $update = $conn->prepare("UPDATE users SET is_email_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE user_id = ?");
                    $update->bind_param("i", $user_id);
                    $update->execute();

                    unset($_SESSION['pending_user_id']);
                    $success = 'Email verified successfully! You can now log in.';
                } else {
                    $error = 'The OTP code has expired. Please request a new one.';
                }
            } else {
                $error = 'Invalid OTP code. Please check your email and try again.';
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
    <title>Verify Email OTP | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ump-navy: #132238;
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

        .otp-container {
            max-width: 480px;
            margin: 4rem auto;
            padding: 0 1.5rem;
        }

        .otp-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            text-align: center;
        }

        .otp-icon {
            width: 64px;
            height: 64px;
            background-color: #fef3c7;
            color: var(--ump-gold-hover);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            text-align: center;
            box-sizing: border-box;
            font-weight: 700;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--ump-gold);
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
            margin-top: 1.25rem;
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
    </nav>

    <div class="otp-container">
        <div class="otp-card">
            <div class="otp-icon">
                <i class="fa-solid fa-envelope-circle-check"></i>
            </div>
            <h1 style="color: var(--ump-navy); font-size: 1.5rem; margin-bottom: 0.5rem;">Verify Your Email</h1>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">
                We have sent a 6-digit OTP code to <strong><?= htmlspecialchars($_SESSION['pending_email'] ?? 'your email') ?></strong>. Please enter it below to complete your registration.
            </p>

            <?php if (!empty($error)): ?>
                <div style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:0.85rem; border-radius:8px; margin-bottom:1.25rem; font-size:0.875rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; padding:1rem; border-radius:8px; margin-bottom:1.25rem; font-size:0.9rem;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
                    <br><br>
                    <a href="login.php" style="color: #166534; font-weight: 700; text-decoration: underline;">Proceed to Sign In <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            <?php else: ?>
                <form action="verify_otp.php" method="POST">
                    <div style="margin-bottom: 1.25rem;">
                        <input type="text" name="otp_code" maxlength="6" class="form-control" placeholder="123456" required autocomplete="off">
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-shield-halved"></i> Verify OTP Code
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>