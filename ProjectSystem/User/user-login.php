<?php
// Include database connection file
require_once "../config/config.php";

// Start session at the beginning of the script
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $captcha = $_POST['g-recaptcha-response']; // reCAPTCHA response token from form

    // Basic validation for email, password, and CAPTCHA
    if (empty($username) || empty($password)) {
        $_SESSION['swal_error'] = [
            'title' => 'Error!',
            'text' => 'Please enter both email and password.',
            'icon' => 'error'
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    if (empty($captcha)) {
        $_SESSION['swal_error'] = [
            'title' => 'Error!',
            'text' => 'Please complete the CAPTCHA.',
            'icon' => 'error'
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Verify CAPTCHA with Google's reCAPTCHA API
  // Verify CAPTCHA with Google's reCAPTCHA API
  $secretKey = '6LfVgHUqAAAAAPu4IsmVAI8j0uqaBhrILi7i5pQW'; // Replace with your reCAPTCHA secret key
  $verifyResponse = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$captcha");
  $responseData = json_decode($verifyResponse);
  
    if (!$responseData->success) {
        $_SESSION['swal_error'] = [
            'title' => 'CAPTCHA verification failed',
            'text' => 'Please try again.',
            'icon' => 'error'
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
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
              $_SESSION['swal_error'] = [
                  'title' => 'Inactive Account',
                  'text' => 'Your account is inactive. Please contact support.',
                  'icon' => 'warning'
              ];
              header("Location: " . $_SERVER['PHP_SELF']);
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

              $_SESSION['swal_success'] = [
                  'title' => 'Success!',
                  'text' => 'You have logged in successfully.',
                  'icon' => 'success'
              ];
              session_write_close(); // Close the session to ensure the alert is available
              header("Location: ../User/user-dashboard.php");
              exit();
          } else {
              $_SESSION['swal_error'] = [
                  'title' => 'Error!',
                  'text' => 'Invalid email or password.',
                  'icon' => 'error'
              ];
              header("Location: ../User/user-login.php");
              exit();
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
              $_SESSION['swal_error'] = [
                  'title' => 'Inactive Account',
                  'text' => 'Your account is inactive. Please contact support.',
                  'icon' => 'warning'
              ];
              header("Location: " . $_SERVER['PHP_SELF']);
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

              $_SESSION['swal_success'] = [
                  'title' => 'Success!',
                  'text' => 'You have logged in successfully.',
                  'icon' => 'success'
              ];
              session_write_close(); // Close the session to ensure the alert is available
              header("Location: ../Staff/user-dashboard.php");
              exit();
          } else {
              $_SESSION['swal_error'] = [
                  'title' => 'Error!',
                  'text' => 'Invalid email or password.',
                  'icon' => 'error'
              ];
              header("Location: ../User/user-login.php");
              exit();
          }
      } else {
          $_SESSION['swal_error'] = [
              'title' => 'Error!', 
              'text' => 'Invalid email or password.',
              'icon' => 'error'
          ];
          header("Location: ../User/user-login.php");
          exit();
      }
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dormio Login</title>
  <link rel="stylesheet" href="../CSS/loginstyle.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css">
</head>
<body>
  <div class="container">
    <a href="../land-main/index.php" class="back-link">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div class="logo"></div>
    <h2>Log in</h2>
    <form action="user-login.php" method="post">
      <div class="form-group">
        <input type="email" name="email" id="email" required placeholder=" " />
        <label for="email">Email</label>
      </div>

      <div class="form-group">
        <input type="password" name="password" id="password" required placeholder=" " />
        <label for="password">Password</label>
        <i class="eye-icon fas fa-eye-slash" title="Show Password" onclick="togglePasswordVisibility('password', this)"></i>
      </div>

      <!-- Google reCAPTCHA widget -->
      <div class="form-group">
        <div class="g-recaptcha" data-sitekey="6LfVgHUqAAAAAJtQJXShsLo2QbyGby2jquueTZYV"></div>
      </div>

      <button type="submit" class="btn">Log in</button>

      <p class="forgot-password"><a href="change-password.php">Forgot Password?</a></p>
    </form>
  </div>
  <style>
    .form-group {
      position: relative;
      margin-bottom: 20px;
    }

    input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc; /* Initial border color */
      border-radius: 4px;
      font-size: 16px;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease; /* Smooth transition for border and shadow */
    }

    input:focus {
      border-color: #007bff; /* Change this to your desired color */
      box-shadow: 0 0 5px rgba(0, 123, 255, 0.5); /* Add a shadow for emphasis */
    }

    /* Adjust label styles */
    label {
      position: absolute;
      left: 12px;
      top: 40%; /* Center the label vertically */
      transform: translateY(-50%); /* Adjust for perfect centering */
      transition: all 0.2s ease;
      color: #999;
      pointer-events: none;
      padding: 0 4px; /* Add some padding to create a background effect */
    }

    input:focus + label,
    input:not(:placeholder-shown) + label {
      top: -8px; /* Move label up slightly */
      left: 12px; /* Keep it within the input */
      font-size: 12px; /* Smaller font size when floating */
      color: #007bff; /* Change this to your desired color */
    }

    .g-recaptcha {
      transform: scale(0.9);
      transform-origin: 0 0;
      margin-top: 5px;
    }
  </style>

  <!-- Include SweetAlert script -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

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

    function handleAlert(title, text, icon) {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            confirmButtonText: 'OK'
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['swal_error'])): ?>
            Swal.fire({
                title: "<?php echo $_SESSION['swal_error']['title']; ?>",
                text: "<?php echo $_SESSION['swal_error']['text']; ?>",
                icon: "<?php echo $_SESSION['swal_error']['icon']; ?>",
                confirmButtonText: 'OK'
            });
            <?php unset($_SESSION['swal_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['swal_success'])): ?>
            Swal.fire({
                title: "<?php echo $_SESSION['swal_success']['title']; ?>",
                text: "<?php echo $_SESSION['swal_success']['text']; ?>",
                icon: "<?php echo $_SESSION['swal_success']['icon']; ?>",
                confirmButtonText: 'OK'
            });
            <?php unset($_SESSION['swal_success']); ?>
        <?php endif; ?>
    });
  </script>
</body>
</html>