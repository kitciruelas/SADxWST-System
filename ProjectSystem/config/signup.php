<?php
// Include database connection file
require_once "config.php";

$username = $email = "";
$username_err = $email_err = $password_err = "";

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Basic validation
    if (empty($username)) {
        $username_err = "Please enter a username.";
    }

    if (empty($email)) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    }

    if (empty($password)) {
        $password_err = "Please enter a password.";
    } elseif ($password !== $confirm_password) {
        $password_err = "Passwords do not match.";
        // Redirect back to signup page with alert
        echo "<script>alert('$password_err'); window.location.href = '../Admin/signup.php';</script>";
        exit; // Stop further execution
    }

    // Check if there are no errors before proceeding
    if (empty($username_err) && empty($email_err) && empty($password_err)) {
        // Check if the username or email already exists
        $sql = "SELECT id FROM admin WHERE username = ? OR email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $param_username, $param_email);
            $param_username = $username;
            $param_email = $email;

            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                echo "<script>alert('Username or Email already taken.');window.location.href = 'signup.html'</script>";
            } else {
                // Insert new user data
                $sql = "INSERT INTO admin (username, email, password) VALUES (?, ?, ?)";
                if ($stmt = $conn->prepare($sql)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param("sss", $username, $email, $hashed_password);

                    if ($stmt->execute()) {
                        echo "<script>
                                alert('Registration successful! Redirecting to login page...');
                                setTimeout(function(){
                                    window.location.href = '../Admin/login.html';
                                }, 2000);
                              </script>";
                    } else {
                        echo "<script>alert('Something went wrong. Please try again.');</script>";
                    }
                }
            }
            $stmt->close();
        }
    } else {
        // Show password error without redirecting
        if (!empty($password_err)) {
            echo "<script>alert('$password_err');</script>";
        }
    }
    $conn->close();
}
?>
