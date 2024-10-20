<?php
// Include database connection
include 'config.php';

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize user inputs
    $email = trim($_POST['email']);
    $new_pass = trim($_POST['new_pass']);
    $confirm_pass = trim($_POST['confirm_pass']);

    // Check if new password and confirm password match
    if ($new_pass !== $confirm_pass) {
        echo "<script>alert('Passwords do not match! Please try again.'); window.location.href='../User/forgot-password.php';</script>";
        exit;
    }

    // Hash the new password
    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

    // Function to update the password in a specific table
    function updatePassword($conn, $email, $hashed_pass, $table) {
        $sql = "UPDATE $table SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            echo "<script>alert('Failed to prepare the update statement.'); window.location.href='../User/forgot-password.php';</script>";
            exit;
        }
        $stmt->bind_param("ss", $hashed_pass, $email);
        return $stmt->execute();
    }

    // Check if the email exists in the 'users' table
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // If email found in 'users', update password and redirect
        if (updatePassword($conn, $email, $hashed_pass, 'users')) {
            echo "<script>alert('Password reset successfully!'); window.location.href='../User/user-login.php';</script>";
        } else {
            echo "<script>alert('Error updating password.'); window.location.href='../User/forgot-password.php';</script>";
        }
    } else {
        // If not found in 'users', check in the 'staff' table
        $stmt = $conn->prepare("SELECT id FROM staff WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // If email found in 'staff', update password and redirect
            if (updatePassword($conn, $email, $hashed_pass, 'staff')) {
                echo "<script>alert('Password reset successfully!'); window.location.href='../User/user-login.php';</script>";
            } else {
                echo "<script>alert('Error updating password.'); window.location.href='../User/forgot-password.php';</script>";
            }
        } else {
            // If email not found in both tables, show error
            echo "<script>alert('Email not found! Please try again.'); window.location.href='../User/forgot-password.php';</script>";
        }
    }

    // Close the prepared statement
    $stmt->close();
}

// Close the database connection
$conn->close();
?>
