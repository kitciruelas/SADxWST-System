<?php
session_start();

// Check if the OTP has been sent and stored in the session
if (!isset($_SESSION['otp_sent'])) {
    header('Location: send-otp-mail.php'); // Redirect back to the email input page if OTP wasn't sent
    exit();
}

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $enteredOtp = $_POST['otp'];

    // Check if OTP and email are stored in the session
    if (isset($_SESSION['otp']) && isset($_SESSION['otp_email'])) {
        // Verify OTP
        if ($enteredOtp == $_SESSION['otp']) {
            // Successfully verified OTP
            // Redirect to change-password.php
            header('Location: changepassword.php');
            exit(); // Ensure no further code is executed after redirect
            
            // Clear the OTP from session after successful verification
            unset($_SESSION['otp']);
            unset($_SESSION['otp_email']);
            unset($_SESSION['otp_sent']); // Clear OTP sent flag
        } else {
            // Incorrect OTP
            echo "<script>alert('Incorrect OTP. Please try again.');</script>";
        }
    } else {
        // Session expired or invalid state
        echo "<script>alert('Session expired. Please request a new OTP.');</script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="email-otp-style.css">
</head>
<body>

<div class="otp-container">
<img src="logo.png" alt="Dormio Logo" class="logo">
    <h2>Enter OTP</h2>
    <form method="POST">
        <label for="otp">OTP Code:</label>
        <input type="text" id="otp" name="otp" maxlength="5" required>
        <input type="submit" value="Verify OTP">
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
