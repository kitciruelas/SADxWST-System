<?php
// Include the config file with DB connection variables
include 'config.php'; // Ensure this file initializes `$conn`

// Helper function to sanitize inputs
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
} 

// Helper function to insert user into the correct table
function insertUser($conn, $fname, $lname, $mi, $age, $address, $contact, $sex, $role, $email, $password, $table) {
    $sql = "INSERT INTO $table (fname, lname, mi, age, address, contact, sex, role, email, password)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssissssss", $fname, $lname, $mi, $age, $address, $contact, $sex, $role, $email, $password);
        return $stmt->execute();
    } else {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = sanitizeInput($_POST['fname']);
    $lname = sanitizeInput($_POST['lname']);
    $mi = sanitizeInput($_POST['mi']);
    $age = intval($_POST['age']);
    $address = sanitizeInput($_POST['Address']);
    $contact = sanitizeInput($_POST['contact']);
    $sex = sanitizeInput($_POST['sex']);
    $role = sanitizeInput($_POST['role']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs with alert() for error messages
    if (empty($fname) || empty($lname) || empty($age) || empty($address) || empty($contact) || empty($sex) || empty($role) || empty($email) || empty($password) || empty($confirm_password)) {
        echo "<script>alert('All fields are required.'); window.history.back();</script>";
        exit;
    }

    // Middle initial validation: Ensure it's a single character or empty
    if (!empty($mi) && !preg_match("/^[a-zA-Z]$/", $mi)) {
        echo "<script>alert('Middle initial should be a single alphabetic character.'); window.history.back();</script>";
        exit;
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email address.'); window.history.back();</script>";
        exit;
    }

    // Password validation: At least 6 characters, one number, one letter
    if (strlen($password) < 6 || !preg_match("/[0-9]/", $password) || !preg_match("/[a-zA-Z]/", $password)) {
        echo "<script>alert('Password must be at least 6 characters long and contain at least one letter and one number.'); window.history.back();</script>";
        exit;
    }

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
        exit;
    }

    // Age validation
    if ($age < 1 || $age > 120) {
        echo "<script>alert('Please enter a valid age.'); window.history.back();</script>";
        exit;
    }

    // Contact number validation (10-11 digits)
    if (!preg_match('/^[0-9]{10,11}$/', $contact)) {
        echo "<script>alert('Please enter a valid contact number (10-11 digits).'); window.history.back();</script>";
        exit;
    }

    // Sex validation (ensure it's a valid option)
    $validSexOptions = ['Male', 'Female', 'Other'];
    if (!in_array($sex, $validSexOptions)) {
        echo "<script>alert('Please select a valid option for sex.'); window.history.back();</script>";
        exit;
    }

    // Role validation: Only allow 'General User' or 'Staff'
    $validRoles = ['General User', 'Staff'];
    if (!in_array($role, $validRoles)) {
        echo "<script>alert('Invalid role selection.'); window.history.back();</script>";
        exit;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Determine the target table and role name based on the role
    if ($role === 'Staff') {
        $table = 'staff';
        $dashboard = '../User/user-login.php';
        $roleName = 'Staff';
    } else {
        $table = 'users';
        $dashboard = '../User/user-login.php';
        $roleName = 'General User';
    }

    // Insert the user data into the appropriate table
    if (insertUser($conn, $fname, $lname, $mi, $age, $address, $contact, $sex, $roleName, $email, $hashed_password, $table)) {
        echo "<script>alert('$roleName account successfully created! Redirecting to login...'); window.location.href = '$dashboard';</script>";
    } else {
        echo "<script>alert('An error occurred while creating the account. Please try again.'); window.history.back();</script>";
    }

    // Close the database connection
    $conn->close();
}
?>
