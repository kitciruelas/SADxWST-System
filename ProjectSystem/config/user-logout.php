<?php
session_start();
require_once "config.php"; // Include database connection file

// Check if user is logged in
if (isset($_SESSION["id"]) && isset($_SESSION["username"]) && isset($_SESSION["role"])) {
    $user_id = $_SESSION["id"];
    $username = $_SESSION["username"];
    $role = $_SESSION["role"];

    // Debugging: Log the session data
    error_log("User ID: $user_id, Username: $username, Role: $role");

    // Verify if user exists in the correct table based on their role (No Admin table anymore)
    if ($role == "General User") {
        $sql_check_user = "SELECT id FROM users WHERE id = ?";
    } elseif ($role == "Staff") {
        $sql_check_user = "SELECT id FROM staff WHERE id = ?";
    } else {
        // If role is not recognized (i.e., not "General User" or "Staff")
        echo "<script>alert('Invalid user role.'); window.location.href = '../User/user-login.php';</script>";
        exit();
    }

    // Prepare and execute the SQL statement to check if the user exists in the correct table
    if ($stmt_check_user = $conn->prepare($sql_check_user)) {
        $stmt_check_user->bind_param("i", $user_id);
        $stmt_check_user->execute();
        $stmt_check_user->store_result();

        if ($stmt_check_user->num_rows == 1) {
            // User found, proceed with logging the activity
            $activity_type = "Logout";
            $activity_details = "$username ($role), logged out.";

            // Insert activity log into the database (including user_id for the foreign key constraint)
            $log_sql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
            if ($log_stmt = $conn->prepare($log_sql)) {
                $log_stmt->bind_param("iss", $user_id, $activity_type, $activity_details);
                $log_stmt->execute();
                $log_stmt->close();
            } else {
                // Error logging activity
                error_log("Failed to log activity: " . $conn->error);
            }

            // Unset all session variables
            $_SESSION = array();

            // Destroy the session
            session_destroy();

            // Redirect to login page
            header("location: ../User/user-login.php");
            exit;
        } else {
            // If user not found in the correct table, show error and redirect
            echo "<script>alert('User session not found in the database.'); window.location.href = '../User/user-login.php';</script>";
            exit();
        }
    } else {
        // If SQL preparation failed
        echo "<script>alert('Database error. Please try again later.'); window.location.href = '../User/user-login.php';</script>";
        exit();
    }
} else {
    // If the user is not logged in, redirect to the login page
    header("location: ../User/user-login.php");
    exit();
}
?>
