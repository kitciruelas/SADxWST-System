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
        echo "<script>alert('Please enter both email and password.'); window.history.back();</script>";
        exit();
    }

    // Step 1: Try finding the user in the 'users' table first
    $sql = "SELECT id, fname, password, 'General User' as role FROM users WHERE email = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $param_username);
        $param_username = $username;

        // Execute the statement
        $stmt->execute();
        $stmt->store_result();

        // Check if the user is found in the 'users' table
        if ($stmt->num_rows == 1) {
            // Bind result variables
            $stmt->bind_result($id, $fname, $hashed_password, $role);
            $stmt->fetch();

            // Verify the password
            if (password_verify($password, $hashed_password)) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Set session variables
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $id;
                $_SESSION["username"] = $fname;
                $_SESSION["role"] = $role; // Automatically set role as 'General User'

                // Redirect to General User dashboard
                echo "<script>
                        alert('Login successful! Redirecting to user dashboard...');
                        window.location.href = '../User/user-dashboard.php';
                      </script>";
                exit();
            } else {
                // Invalid password
                echo "<script>alert('Invalid email or password. Please try again.'); window.history.back();</script>";
                exit();
            }
        }
        $stmt->close();
    }

    // Step 2: If the user is not found in 'users', check the 'staff' table
    $sql = "SELECT id, fname, password, 'Staff' as role FROM staff WHERE email = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $param_username);
        $param_username = $username;

        // Execute the statement
        $stmt->execute();
        $stmt->store_result();

        // Check if the user is found in the 'staff' table
        if ($stmt->num_rows == 1) {
            // Bind result variables
            $stmt->bind_result($id, $fname, $hashed_password, $role);
            $stmt->fetch();

            // Verify the password
            if (password_verify($password, $hashed_password)) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Set session variables
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $id;
                $_SESSION["username"] = $fname;
                $_SESSION["role"] = $role; // Automatically set role as 'Staff'

                // Redirect to Staff dashboard
                echo "<script>
                        alert('Login successful! Redirecting to staff dashboard...');
                        window.location.href = '../Staff/user-dashboard.php';
                      </script>";
                exit();
            } else {
                // Invalid password
                echo "<script>alert('Invalid email or password. Please try again.'); window.history.back();</script>";
                exit();
            }
        } else {
            // User not found in both tables
            echo "<script>alert('Invalid email or password. Please try again.'); window.history.back();</script>";
        }
        $stmt->close();
    }

    // Close the connection
    $conn->close();
}
?>
