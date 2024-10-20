<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dormio Login</title>
  <link rel="stylesheet" href="../CSS/loginstyle.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<?php
  // Properly include 'config.php'
  include '../config/slogin.php'; // or require 'config.php';
?>
  <div class="container">
  <a href="../land-main/login.html" class="back-link">
  <i class="fas fa-arrow-left"></i>
</a>
    <div class="logo"></div>
    <h2>Dormio: An Integrated Dorm Management System</h2>
    <form action="../config/slogin.php" method="post">
      <div>
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>
      </div>

      <div class="input-container">
        <label for="password">Password:</label>
        <div class="input-container">
          <input type="password" name="password" id="password" required>
          <i class="eye-icon fas fa-eye-slash" onclick="togglePasswordVisibility('password', this)"></i>
        </div>
      </div>
      
      <button type="submit" class="btn">Sign in</button>

      <p class="forgot-password"><a href="forgot-password.php">Forgot Password?</a></p>
      <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
    </form>
  </div>

  <script>


     
    function togglePasswordVisibility(fieldId, icon) {
      var field = document.getElementById(fieldId);
      if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye"); // Change icon to eye when showing password
      } else {
        field.type = "password";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash"); // Change icon back to eye-slash when hiding password
      }
    }
  </script>
</body>
</html>
