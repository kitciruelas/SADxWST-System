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

    // Check if the email exists in either users or staff table
    $query = "SELECT 'users' AS role, email FROM users WHERE email = ?
              UNION
              SELECT 'staff' AS role, email FROM staff WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Email exists in the database
        $role = $user['role']; // Determine the role (users or staff)
        $_SESSION['role'] = $role; // Store role in session

        // Generate OTP and send email
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
        // Email doesn't exist in either table
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
    <link rel="icon" href="../img-icon/email.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet"><!-- DataTables CSS -->

    <link rel="stylesheet" href="email-otp-style.css">
</head>
<body>

<div class="otp-container">
    <img src="logo.png" alt="Dormio Logo" class="logo">
    <h2>Reset Your Password</h2>
    <p>Enter your email associated with your account. We will send you an OTP to reset your password.</p>
    
    <form method="POST">
        <div class="form-group">
            <input type="email" id="email" name="email" placeholder=" " required>
            <label for="email">Email</label>
        </div>
        <button type="submit" name="send_otp" class="btn btn-primary">Submit</button>
    </form>

    <div class="login-link">
        <p>Remember your Password? <a href="user-login.php">Log in here</a></p>
    </div>
</div>

<style>
    .otp-container {
    max-width: 400px;
    margin: 50px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 10px;
    background-color: #f9f9f9;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.logo {
    width: 100px;
    margin-bottom: 20px;
}

h2 {
    font-size: 24px;
    margin-bottom: 10px;
    color: #333;
}

p {
    color: #555;
    margin-bottom: 20px;
}

.form-group {
    position: relative;
    margin-bottom: 20px;
    text-align: left;
}

input[type="email"] {
    width: 100%;
    padding: 12px 12px 12px 12px; /* Add padding for space */
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.3s ease;
}

input[type="email"]:focus {
    border-color: #007bff;
}

input[type="email"]:focus + label,
input[type="email"]:not(:placeholder-shown) + label {
    top: -8px;
    left: 12px;
    font-size: 12px;
    color: #007bff;
}

label {
    position: absolute;
    top: 50%;
    left: 12px;
    transform: translateY(-50%);
    font-size: 16px;
    color: #aaa;
    pointer-events: none;
    transition: all 0.3s ease;
}

button.btn {
    display: inline-block;
    width: 100%;
    padding: 10px;
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

button.btn-primary {
    background-color: #007bff;
    color: #fff;
    transition: background-color 0.3s ease;
}

button.btn-primary:hover {
    background-color: #0056b3;
}

.login-link p {
    margin-top: 20px;
    font-size: 14px;
    color: #333;
}

.login-link a {
    color: #007bff;
    text-decoration: none;
}

.login-link a:hover {
    text-decoration: underline;
}


</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
