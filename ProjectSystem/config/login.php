<?php
// Include database connection file
require_once "config.php";

// Start session at the beginning of the script
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["email"]); // Assuming you are using email as username
    $password = trim($_POST["password"]);

    // Basic validation
    if (empty($username) || empty($password)) {
        echo "<script>alert('Please enter both username and password.'); location.reload();</script>";
    } else {
        // Prepare the SQL query
        $sql = "SELECT id, username, password FROM admin WHERE email = ?"; // Changed to email for consistency
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            $stmt->execute();
            $stmt->store_result();

            // Check if user exists
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $username, $hashed_password);
                $stmt->fetch();

                // Verify password
                if (password_verify($password, $hashed_password)) {
                    // Set session variables
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["username"] = $username;

                    // Success message and delayed redirection to dashboard
                    echo "<script>
                            alert('Login successful! Redirecting to your dashboard...');
                            window.location.href = '../Admin/dashboard.php'; // Immediate redirect
                          </script>";
                } else {
                    // Redirect back to login page on invalid password
                    echo "<script>alert('Wrong email and password. Please try again.'); window.location.href = '../Admin/index.php';</script>";
                }
            } else {
                // Redirect back to login page if no account found
                echo "<script>alert('Wrong email and password. Please try again.'); window.location.href = '../Admin/index.php';</script>";
            }
            $stmt->close();
        }
    }
    $conn->close();
}


?>
