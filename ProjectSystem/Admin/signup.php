<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dormio Sign Up</title>
  <link rel="stylesheet" href="../CSS/signu-pstyle.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <?php
  // Properly include 'config.php'
  include '../config/config.php'; // or require 'config.php';
?>
  <div class="container">
    <div class="logo"></div>
    <h2>Create an Account</h2>
    <form action="signup.php" method="post">
      <div>
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" required>
      </div>
      <div>
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>
      </div>
      <div>
        <label for="password">Password:</label>
        <div class="input-container">
          <input type="password" name="password" id="password" required>
          <i class="eye-icon fas fa-eye" onclick="togglePasswordVisibility('password', this)"></i>
        </div>
      </div>
      <div>
        <label for="confirm_password">Confirm Password:</label>
        <div class="input-container">
          <input type="password" name="confirm_password" id="confirm_password" required>
          <i class="eye-icon fas fa-eye" onclick="togglePasswordVisibility('confirm_password', this)"></i>
        </div>
      </div>
      
      <button type="submit" class="btn">Sign up</button>
    </form>
  </div>

  <script>
    function togglePasswordVisibility(fieldId, icon) {
      var field = document.getElementById(fieldId);
      if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash"); // Change icon to eye-slash (indicating password is visible)
      } else {
        field.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye"); // Change icon to eye (indicating password is hidden)
      }
    }
</script>


</body>
</html>
