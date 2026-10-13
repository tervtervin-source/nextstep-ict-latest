
<?php

/**

 * Sends OTP Email using native PHP mail() function (No external Composer dependencies required for local XAMPP testing)

 */

function sendOtpEmail($recipient_email, $recipient_name, $otp_code) {

    $to = $recipient_email;

    $subject = "NextStep ICT - Your Email Verification Code";

   

    $message = "

    <html>

    <head>

        <title>Email Verification</title>

    </head>

    <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 20px;'>

        <div style='background-color: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; max-width: 500px; margin: 0 auto;'>

            <h2 style='color: #132238; margin-top: 0;'>NextStep ICT Account Verification</h2>

            <p>Hello <strong>" . htmlspecialchars($recipient_name) . "</strong>,</p>

            <p>Thank you for registering. Your 6-digit email verification code is:</p>

            <div style='background-color: #132238; color: #f59e0b; font-size: 32px; font-weight: bold; letter-spacing: 6px; padding: 15px; text-align: center; border-radius: 6px; margin: 20px 0;'>

                {$otp_code}

            </div>

            <p>This code is valid for <strong>15 minutes</strong>. Do not share this code with anyone.</p>

            <hr style='border: none; border-top: 1px solid #e2e8f0; margin-top: 20px;'>

            <p style='font-size: 12px; color: #64748b; margin-bottom: 0;'>If you did not initiate this request, please ignore this message.</p>

        </div>

    </body>

    </html>

    ";



    $headers = "MIME-Version: 1.0" . "\r\n";

    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

    $headers .= "From: no-reply@nextstepict.ac.za" . "\r\n";



    // Dispatch email

    $mail_sent = @mail($to, $subject, $message, $headers);



    if (!$mail_sent) {

        // Log error if mail fails

        $log_file = __DIR__ . '/../logs/email_errors.log';

        $log_dir = dirname($log_file);

        if (!is_dir($log_dir)) {

            mkdir($log_dir, 0777, true);

        }

        $error_message = "[" . date('Y-m-d H:i:s') . "] Failed to send OTP to {$recipient_email}.\n";

        file_put_contents($log_file, $error_message, FILE_APPEND);

        return false;

    }



    return true;

}



/**

 * Generates a new 6-digit OTP code and updates the database record

 */

function generateAndSendNewOtp($conn, $user_id, $email, $full_name) {

    $otp_code = sprintf("%06d", mt_rand(100000, 999999));

    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));



    $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE user_id = ?");

    $stmt->bind_param("ssi", $otp_code, $otp_expires_at, $user_id);



    if ($stmt->execute()) {

        return sendOtpEmail($email, $full_name, $otp_code);

    }

    return false;

}

?>