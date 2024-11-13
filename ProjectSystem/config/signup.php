<?php
// Include database connection file
require_once "config.php";

$username = $email = $fname = $lname = "";
$username_err = $email_err = $password_err = $fname_err = $lname_err = "";

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and assign the POST data to variables
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $fname = trim($_POST["fname"]);
    $lname = trim($_POST["lname"]);

    // Basic validation
    if (empty($username)) {
        $username_err = "Please enter a username.";
    }

    if (empty($email)) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    }

    if (empty($fname)) {
        $fname_err = "Please enter your first name.";
    }

    if (empty($lname)) {
        $lname_err = "Please enter your last name.";
    }

    if (empty($password)) {
        $password_err = "Please enter a password.";
    } elseif ($password !== $confirm_password) {
        $password_err = "Passwords do not match.";
    }

    // Proceed only if there are no errors
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($fname_err) && empty($lname_err)) {
        // Check if the username or email already exists
        $sql = "SELECT id FROM admin WHERE username = ? OR email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $param_username, $param_email);
            $param_username = $username;
            $param_email = $email;

            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // If username or email exists, show an error
                $stmt->close();
                $username_err = "Username or Email already taken.";
            } else {
                // Insert new user data with first name and last name
                $sql = "INSERT INTO admin (username, email, password, fname, lname) VALUES (?, ?, ?, ?, ?)";
                if ($stmt = $conn->prepare($sql)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param("sssss", $username, $email, $hashed_password, $fname, $lname);

                    if ($stmt->execute()) {
                        // Registration success
                        echo "<script>
                                alert('Registration successful! Redirecting to login page...');
                                setTimeout(function(){
                                    window.location.href = '../Admin/index.php';
                                }, 2000);
                              </script>";
                    } else {
                        // Database insertion failed
                        echo "<script>alert('Something went wrong. Please try again.');</script>";
                    }
                    $stmt->close();
                }
            }
        }
    }
    $conn->close();
}
?>
