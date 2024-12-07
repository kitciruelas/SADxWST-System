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
    <link rel="icon" href="../img-icon/otp.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="email-otp-style.css">
</head>
<body>

<div class="otp-container">
    <img src="logo.png" alt="Dormio Logo" class="logo">
    <h2>Enter OTP</h2>
    <form method="POST">
        <div class="form-group">
            <input type="text" id="otp" name="otp" maxlength="5" placeholder=" " required>
            <label for="otp">OTP Code</label>
        </div>
        <button type="submit" class="btn btn-primary">Verify OTP</button>
    </form>

    <!-- Resend OTP link -->
    <a href="change-password.php" class="resend-otp-link">Resend OTP</a>
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
    margin-bottom: 20px;
    color: #333;
}

.form-group {
    position: relative;
    margin-bottom: 20px;
    text-align: left;
}

input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.3s ease;
}

input[type="text"]:focus {
    border-color: #007bff;
}

input[type="text"]:focus + label,
input[type="text"]:not(:placeholder-shown) + label {
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
.resend-otp-link {
    display: inline-block;
    margin-top: 10px; /* Adds space above the link */
    color: #007bff; /* Bootstrap primary color */
    text-decoration: none; /* Removes underline */
    font-size: 14px; /* Adjusts the font size */
    font-weight: bold; /* Makes the text bold */
}

.resend-otp-link:hover {
    color: #0056b3; /* Darker shade on hover */
    text-decoration: underline; /* Underline the link on hover */
}


</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
