<?php
// Include database connection file
require_once "config.php";

// Start session at the beginning of the script
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $captcha = $_POST['g-recaptcha-response']; // reCAPTCHA response token from form

    // Basic validation for email, password, and CAPTCHA
    if (empty($username) || empty($password)) {
        echo "<script>alert('Please enter both email and password.'); window.history.back();</script>";
        exit();
    }
    if (empty($captcha)) {
        echo "<script>alert('Please complete the CAPTCHA.');  window.location.href = '../User/user-login.php';</script>";
        exit();
    }

    // Verify CAPTCHA with Google's reCAPTCHA API
    $secretKey = '6LfVgHUqAAAAAPu4IsmVAI8j0uqaBhrILi7i5pQW'; // Replace with your reCAPTCHA secret key
    $verifyResponse = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$captcha");
    $responseData = json_decode($verifyResponse);

    if (!$responseData->success) {
        echo "<script>
        alert('CAPTCHA verification failed. Please try again.');
        window.location.href = '../User/user-login.php';
      </script>";
        exit();
    }

    // Initialize variables for activity log
    $activity_type = "Login Attempt";
    $activity_status = "Failed";
    $role = "Unknown";

    // Step 1: Try finding the user in the 'users' table first
    $sql = "SELECT id, fname, password, status, 'General User' as role FROM users WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $param_username);
        $param_username = $username;
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $fname, $hashed_password, $status, $role);
            $stmt->fetch();

            if ($status !== 'active') {
                echo "<script>alert('Your account is inactive. Please contact support.'); window.location.href = '../User/user-login.php';</script>";
                exit();
            }

            if (password_verify($password, $hashed_password)) {
                // Successful login
                session_regenerate_id(true);
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $id;
                $_SESSION["username"] = $fname;
                $_SESSION["role"] = $role;

                // Update activity log details
                $activity_type = "Login";
                $activity_status = "Successful";

                echo "<script>alert('Login successful! Redirecting to user dashboard...'); window.location.href = '../User/user-dashboard.php';</script>";
            } else {
                echo "<script>alert('Invalid email or password. Please try again.'); window.location.href = '../User/user-login.php';</script>";
            }
        }
        $stmt->close();
    }

    // Step 2: If the user is not found in 'users', check the 'staff' table
    $sql = "SELECT id, fname, password, status, 'Staff' as role FROM staff WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $param_username);
        $param_username = $username;
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $fname, $hashed_password, $status, $role);
            $stmt->fetch();

            if ($status !== 'active') {
                echo "<script>alert('Your account is inactive. Please contact support.'); window.location.href = '../User/user-login.php';</script>";
                exit();
            }

            if (password_verify($password, $hashed_password)) {
                // Successful login
                session_regenerate_id(true);
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $id;
                $_SESSION["username"] = $fname;
                $_SESSION["role"] = $role;

                // Update activity log details
                $activity_type = "Login";
                $activity_status = "Successful";

                echo "<script>alert('Login successful! Redirecting to staff dashboard...'); window.location.href = '../Staff/user-dashboard.php';</script>";
            } else {
                echo "<script>alert('Invalid email or password. Please try again.'); window.location.href = '../User/user-login.php';</script>";
            }
        } else {
            echo "<script>alert('Invalid email or password. Please try again.'); window.location.href = '../User/user-login.php';</script>";
        }
        $stmt->close();
    }
// Log activity
$activity_type = "Login";
$activity_details = "$role $fname with email $username logged in."; // Role dynamically added
$log_sql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
if ($log_stmt = $conn->prepare($log_sql)) {
    $log_stmt->bind_param("iss", $id, $activity_type, $activity_details);
    $log_stmt->execute();
    $log_stmt->close();
}


    $conn->close();
    exit();
}
?>
