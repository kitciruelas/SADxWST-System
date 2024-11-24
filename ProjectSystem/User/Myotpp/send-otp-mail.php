<?php
// Include the PHPMailer files
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include your database connection
require_once '../../config/config.php'; // Adjust the path to your config file
require_once '/XAMPP/htdocs/SADxWST-System-main/ProjectSystem/config/config.php'; // Correct path to your dbcon.php

if (!isset($conn)) { // Ensure $conn is initialized
    die("Database connection is not established. Please check config.php.");
}

session_start(); // Start session for storing OTP

// Generate a 6-digit OTP
function generateOtp() {
    return rand(100000, 999999);
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
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('dormioph@gmail.com', 'DORMIOPH');
        $mail->addAddress($email); // Recipient email

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code';
        $mail->Body    = "Your OTP code is: <b>$otp</b>";

        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "<script>alert('Email could not be sent. Mailer Error: {$mail->ErrorInfo}');</script>";
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_otp'])) {
    $email = $_POST['email'];

    // Check if the email exists in the database using mysqli
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query); // Use $conn for the database connection

    if (!$stmt) {
        die("Error in query: " . $conn->error);
    }

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
    <title>Send OTP</title>
    <link rel="stylesheet" href="email-otp-style.css">
</head>
<body>

<div class="otp-container">
    <h2>Enter Your Email</h2>
    <form method="POST">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <input type="submit" name="send_otp" value="Send OTP">
    </form>
</div>

</body>
</html>
