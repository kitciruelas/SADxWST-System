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
  include '../config/login.php'; // or require 'config.php';
?>
  <div class="container">
  <!--<a href="../land-main/landing.php" class="back-link">
  <i class="fas fa-arrow-left"></i>
</a>-->

    <div class="logo"></div>
    <h2>Dormio: Admin</h2>
    <form action="../config/login.php" method="post">
    <form>
  <div class="form-group">
    <input type="email" name="email" id="email" required placeholder=" " />
    <label for="email">Email</label>
  </div>

  <div class="form-group">
    <input type="password" name="password" id="password" required placeholder=" " />
    <label for="password">Password</label>
    <i class="eye-icon fas fa-eye-slash" title="Show Password" onclick="togglePasswordVisibility('password', this)"></i>
  </div>

  <button type="submit" class="btn">Sign in</button>
  <!--<p class="forgot-password"><a href="forgot-password.php">Forgot Password?</a></p>
  <p>Don't have an account? <a href="signup.php">Sign up here</a></p>-->
</form>
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




</style>

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

  </script>
</body>
</html>
