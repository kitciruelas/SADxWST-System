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
        $mail->Username = 'zyrus.programming@gmail.com'; // Your Gmail email
        $mail->Password = 'rtwl otxi kaae dqqv'; // Your generated Google App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('zyrus.programming@gmail.com', 'DORMIO');
        $mail->addAddress($email); // Recipient email

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code';
        $mail->Body    = "Your OTP code is: <b>$otp</b>";

        // Send email
        $mail->send();
        return true;
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