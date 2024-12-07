<?php
session_start();

// Check if the OTP was successfully verified and the email exists in the session
if (!isset($_SESSION['otp_email'])) {
    // Redirect to the OTP verification page if the OTP email is not set
    header('Location: send-otp-mail.php');
    exit();
}

// Include the database connection file (mysqli)
require_once '/XAMPP/htdocs/SADxWST-System-main/ProjectSystem/config/config.php'; // Database connection file (update with your DB credentials)

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate new password
    if (empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Both password fields are required.',
            'icon' => 'error'
        ];
    } elseif ($newPassword !== $confirmPassword) {
        // Check if the new password and confirm password match
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Passwords do not match.',
            'icon' => 'error'
        ];
    } elseif (strlen($newPassword) < 6) {
        // Check if the new password is at least 6 characters long
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Password must be at least 6 characters long.',
            'icon' => 'error'
        ];
    } else {
        // Hash the new password securely
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Get the user's email and role from the session
        $email = $_SESSION['otp_email'];
        $role = $_SESSION['role']; // Ensure 'role' is set as 'staff' or 'users' in session

        // Determine the appropriate table based on the role
        $table = ($role === 'staff') ? 'staff' : 'users';

        // Update the password in the database using mysqli
        $query = "UPDATE $table SET password = ? WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $query)) {
            // Bind the parameters
            mysqli_stmt_bind_param($stmt, 'ss', $hashedPassword, $email);

            // Execute the statement
            if (mysqli_stmt_execute($stmt)) {
                // Clear session data to prevent resubmission
                unset($_SESSION['otp_email']);
                unset($_SESSION['otp']); // Clear OTP as well
                unset($_SESSION['otp_sent']); // Clear OTP sent flag

                $_SESSION['swal_success'] = [
                    'title' => 'Success!',
                    'text' => 'Password successfully changed!',
                    'icon' => 'success'
                ];
            } else {
                $_SESSION['swal_error'] = [
                    'title' => 'Error',
                    'text' => 'Error updating password. Please try again.',
                    'icon' => 'error'
                ];
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

input[type="password"] {
    width: 100%;
    padding: 12px 12px 12px 12px; /* Add padding for space */
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.3s ease;
}

input[type="password"]:focus {
    border-color: #007bff;
}

input[type="password"]:focus + label,
input[type="password"]:not(:placeholder-shown) + label {
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
h2.mb-2 {
    font-size: 24px; /* Adjust font size */
    margin-bottom: 20px; /* Set bottom margin to your desired spacing */
    color: #333; /* Set text color */
    font-weight: bold; /* Make the text bold */
    text-align: center; /* Center align the text */
}
.eye-icon {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 20px; /* Adjust icon size */
}

.form-group {
    position: relative;
    margin-bottom: 20px;
}

input[type="password"] {
    width: 100%;
    padding: 12px 12px 12px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.3s ease;
}

input[type="password"]:focus {
    border-color: #007bff;
}

input[type="password"]:focus + label,
input[type="password"]:not(:placeholder-shown) + label {
    top: -8px;
    left: 12px;
    font-size: 12px;
    color: #007bff;
}
input[type="Text"] {
    width: 100%;
    padding: 12px 12px 12px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.3s ease;
}

input[type="Text"]:focus {
    border-color: #007bff;
}

input[type="Text"]:focus + label,
input[type="Text"]:not(:placeholder-shown) + label {
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

.eye-icon i {
    color: gray; /* Set the icon color to gray */
}

</style>
<!-- Include Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

<div class="password-container">
    <img src="logo.png" alt="Dormio Logo" class="logo">
    <h2 class="mb-2">Change Your Password</h2>
    <form method="POST">
        <div class="form-group">
            <input type="password" id="new_password" name="new_password" required placeholder="">
            <label for="new_password">New Password</label>
            <button type="button" class="eye-icon" onclick="togglePasswordVisibility('new_password', this.querySelector('i'))">
                <i class="fas fa-eye-slash" id="eye-icon-new" style="color: gray;"></i>
            </button>
        </div>

        <div class="form-group">
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="">
            <label for="confirm_password">Confirm New Password</label>
            <button type="button" class="eye-icon" onclick="togglePasswordVisibility('confirm_password', this.querySelector('i'))">
                <i class="fas fa-eye-slash" id="eye-icon-confirm" style="color: gray;"></i>
            </button>
        </div>

        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
</div>
<script>
    function togglePasswordVisibility(fieldId, icon) {
        var field = document.getElementById(fieldId);
        if (field.type === "password") {
            field.type = "text"; // Show the password
            icon.classList.remove("fa-eye-slash"); // Remove closed eye icon
            icon.classList.add("fa-eye"); // Add open eye icon
            icon.setAttribute("title", "Hide Password"); // Update tooltip text
        } else {
            field.type = "password"; // Hide the password
            icon.classList.remove("fa-eye"); // Remove open eye icon
            icon.classList.add("fa-eye-slash"); // Add closed eye icon
            icon.setAttribute("title", "Show Password"); // Update tooltip text
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['swal_success'])): ?>
            Swal.fire({
                title: '<?php echo $_SESSION['swal_success']['title']; ?>',
                text: '<?php echo $_SESSION['swal_success']['text']; ?>',
                icon: '<?php echo $_SESSION['swal_success']['icon']; ?>'
            }).then(() => {
                window.location.href = '../User/user-login.php';
            });
            <?php unset($_SESSION['swal_success']); ?>
        <?php elseif (isset($_SESSION['swal_error'])): ?>
            Swal.fire({
                title: '<?php echo $_SESSION['swal_error']['title']; ?>',
                text: '<?php echo $_SESSION['swal_error']['text']; ?>',
                icon: '<?php echo $_SESSION['swal_error']['icon']; ?>'
            });
            <?php unset($_SESSION['swal_error']); ?>
        <?php endif; ?>
    });
</script>


</body>
</html>
