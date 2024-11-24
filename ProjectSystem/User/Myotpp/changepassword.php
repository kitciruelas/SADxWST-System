<?php
session_start();

// Check if the OTP was successfully verified and the email exists in the session
if (!isset($_SESSION['otp_email'])) {
    // Redirect to the OTP verification page if the OTP email is not set
    header('Location: send-otp-mail.php');
    exit();
}
// Include the PHPMailer files
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';


// Include the database connection file (mysqli)
require_once '../../config/config.php'; // Adjust the path to your config file

// Handle form submission to change the password
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate new password
    if (empty($newPassword) || empty($confirmPassword)) {
        echo "<script>alert('Both password fields are required.');</script>";
    } elseif ($newPassword !== $confirmPassword) {
        // Check if the new password and confirm password match
        echo "<script>alert('Passwords do not match.');</script>";
    } else {
        // Hash the new password securely
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Get the user's email from the session
        $email = $_SESSION['otp_email'];

        // Update the password in the database using mysqli
        $query = "UPDATE users SET password = ? WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $query)) {
            // Bind the parameters
            mysqli_stmt_bind_param($stmt, 'ss', $hashedPassword, $email);

            // Execute the statement
            if (mysqli_stmt_execute($stmt)) {
                // Clear session data to prevent resubmission
                unset($_SESSION['otp_email']);
                unset($_SESSION['otp']); // Clear OTP as well
                unset($_SESSION['otp_sent']); // Clear OTP sent flag

                // Success message and redirect to login page
                echo "<script>alert('Password successfully changed!'); window.location.href = '/SADxWST-System-main/user-login.php';</script>";
            } else {
                echo "<script>alert('Error updating password. Please try again.');</script>";
            }

            // Close the statement
            mysqli_stmt_close($stmt);
        } else {
            echo "<script>alert('Error preparing statement.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="email-otp-style.css">
</head>

<body>
<style>
    /* styles.css */

/* Basic reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Set background and full height */
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f9;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

/* Style for the password change container */
.password-container {
    background-color: rgba(255, 255, 255, 0.9); /* White background with slight transparency */
    border-radius: 8px;
    padding: 30px;
    width: 100%;
    max-width: 400px; /* Set maximum width for the form */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Heading styling */
h2 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}

/* Form styling */
form {
    display: flex;
    flex-direction: column;
}

/* Label styling */
label {
    font-size: 14px;
    color: #333;
    margin-bottom: 8px;
}

/* Input fields styling */
input[type="password"] {
    padding: 12px;
    margin-bottom: 20px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 4px;
    outline: none;
}

input[type="password"]:focus {
    border-color: #007bff; /* Highlight border on focus */
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.2); /* Optional: Add a subtle glow effect */
}

/* Submit button styling */
input[type="submit"] {
    padding: 12px;
    
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
}

input[type="submit"]:hover {
  transition: background-color 0.3s ease;
}

</style>
<div class="password-container">
    <h2>Change Your Password</h2>
    <form method="POST">
        <label for="new_password">New Password:</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <input type="submit" value="Change Password">
    </form>
</div>

</body>
</html>
