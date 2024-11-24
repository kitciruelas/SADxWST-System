<?php
// Include the PHPMailer files
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include your database connection
require_once '/XAMPP/htdocs/SADxWST-System-main/ProjectSystem/config/config.php'; // Correct path to your dbcon.php

session_start();

// Generate a 5-digit OTP
function generateOtp() {
    return rand(10000, 99999);
}

// Send OTP email
function sendOtpEmail($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Use Gmail SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'dormioph@gmail.com'; // Your Gmail email
        $mail->Password = 'ymrd smvk acxa whdy'; // Your generated Google App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use STARTTLS encryption
        $mail->Port = 587; // Port for STARTTLS

        // Disable SSL certificate verification (for testing purposes)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Debug output (use 2 for detailed debug)
        $mail->SMTPDebug = 2; // Enable debugging

        // Recipients
        $mail->setFrom('dormioph@gmail.com', 'DORMIOPH');
        $mail->addAddress($email); // Recipient email

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code';
        $mail->Body    = "Your OTP code is: <b>$otp</b>";

        // Send email
        if ($mail->send()) {
            return true;
        } else {
            echo "Mailer Error: " . $mail->ErrorInfo;
            return false;
        }
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}

// Handle OTP sending
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_otp'])) {
    $email = $_POST['email'];

    // Check if the email exists in the database using mysqli
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Email exists, generate OTP and send email
        $otp = generateOtp();
        $_SESSION['otp'] = $otp; // Store OTP in session for verification
        $_SESSION['otp_email'] = $email; // Store email for tracking

        if (sendOtpEmail($email, $otp)) {
            $_SESSION['otp_sent'] = true; // Flag to show OTP form
            header('Location: verify-otp-mail.php'); // Redirect to OTP form
            exit();
        } else {
            echo "<script>alert('Failed to send OTP. Please try again.');</script>";
        }
    } else {
        // Email doesn't exist in the database
        echo "<script>alert('The email you entered is not registered. Please try again.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - Dormio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="email-otp-style.css">
</head>
<body>

<div class="otp-container">
    <img src="logo.png" alt="Dormio Logo" class="logo">
    <h2>Reset Your Password</h2>
    <p>Enter your email associated with your account. We will send you an OTP to reset your password.</p>
    
    <form method="POST">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <input type="submit" name="send_otp" value="Submit" class="submit-button">
    </form>

    <div class="login-link">
        <p>Remember your Password? <a href="./../user/user-login.php">Log in here</a></p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
