<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dormio Sign Up</title>
  <link rel="stylesheet" href="Css_user/usersignup.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<?php
  include '../config/user-signup.php';
?>

  <div class="container">
    <div class="logo"></div>
    <h2>Create an Account</h2>
    <form id="signupForm" action="../config/user-signup.php" method="post">
      
      <!-- Step 1: Personal Information -->
      <div class="form-step form-step-active">
        <div class="column">
          <div>
            <label for="fname">First Name:</label>
            <input type="text" name="fname" id="fname" required>
          </div>
          <div>
            <label for="lname">Last Name:</label>
            <input type="text" name="lname" id="lname" required>
          </div>
          <div>
            <label for="mi">Middle Initial:</label>
            <input type="text" name="mi" id="mi" maxlength="1" required>
          </div>
          <div>
            <label for="age">Age:</label>
            <input type="number" name="age" id="age" min="1" max="120" required>
          </div>
          <div>
            <label for="address">Address:</label>
            <input type="text" id="Address" name="Address" required>
          </div>
          <div>
            <label for="contact">Contact Number:</label>
            <input type="text" name="contact" id="contact" required pattern="[0-9]{10,11}" title="Please enter a valid contact number (10-11 digits)">
          </div>
          <div>
            <label for="sex">Sex:</label>
            <select name="sex" id="sex" required>
              <option value="">Select Sex</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div>
            <label for="role">Role:</label>
            <select name="role" id="role" required>
              <option value="">Select Role</option>
              <option value="General User">General User</option>
              <option value="Staff">Staff</option>
            </select>
          </div>
          <p>Already have an account? <a href="user-login.php">Sign in here</a></p>
        </div>
        <div class="full-width">
          <button type="button" class="btn next-step">Next</button>
        </div>
      </div>
      
      <!-- Step 2: Account Information -->
      <div class="form-step">
        <div class="box-container">
          <div class="small-input">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>
          </div>
          <div>
            <label for="password">Password:</label>
            <div class="input-container">
              <input type="password" name="password" id="password" required minlength="6" title="Password must be at least 6 characters long.">
              <i class="eye-icon fas fa-eye-slash" onclick="togglePasswordVisibility('password', this)"></i>
            </div>
          </div>
          <div>
            <label for="confirm_password">Confirm Password:</label>
            <div class="input-container">
              <input type="password" name="confirm_password" id="confirm_password" required>
              <i class="eye-icon fas fa-eye-slash" onclick="togglePasswordVisibility('confirm_password', this)"></i>
            </div>
          </div>
        </div>
        <div class="full-width">
          <input type="checkbox" id="privacy" name="privacy" required>
          <label for="privacy">I agree to the <a href="https://dict.gov.ph/ra-10173/" target="_blank">Privacy Policy</a></label>
        </div>
        <div class="full-width">
          <button type="button" class="btn prev-step">Previous</button>
          <button type="submit" class="btn">Sign up</button>
        </div>
      </div>
    </form>
  </div>

  <script>
    // Toggle Password Visibility
    function togglePasswordVisibility(fieldId, icon) {
      const field = document.getElementById(fieldId);
      if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      } else {
        field.type = "password";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      }
    }

    const steps = document.querySelectorAll('.form-step');
    const nextBtns = document.querySelectorAll('.next-step');
    const prevBtns = document.querySelectorAll('.prev-step');
    let currentStep = 0;

    // Move to the next step if the current step is valid
    nextBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (validateStep(currentStep)) {
          steps[currentStep].classList.remove('form-step-active');
          currentStep = Math.min(currentStep + 1, steps.length - 1);
          steps[currentStep].classList.add('form-step-active');
        }
      });
    });

    // Move to the previous step
    prevBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        steps[currentStep].classList.remove('form-step-active');
        currentStep = Math.max(currentStep - 1, 0);
        steps[currentStep].classList.add('form-step-active');
      });
    });

    // Validate the current step
    function validateStep(stepIndex) {
      const inputs = steps[stepIndex].querySelectorAll('input[required], select[required]');
      let valid = true;
      let alertMessage = '';

      // Check if all required fields are filled
      inputs.forEach(input => {
        if (!input.value.trim()) {
          valid = false;
          input.classList.add('invalid');
          if (!alertMessage) {
            alertMessage = 'Please fill out all required fields.';
          }
        } else {
          input.classList.remove('invalid');
        }
      });

      if (!valid) {
        alert(alertMessage);
        return false;
      }

      // Step-specific validations
      if (stepIndex === 0) {
        const contact = document.getElementById('contact').value;
        const contactPattern = /^[0-9]{10,11}$/;
        if (!contactPattern.test(contact)) {
          alert('Please enter a valid contact number (10-11 digits).');
          return false;
        }
      } else if (stepIndex === 1) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        if (password.length < 6) {
          alert('Password must be at least 6 characters.');
          return false;
        } else if (password !== confirmPassword) {
          alert('Passwords do not match.');
          return false;
        }
      }

      return true;
    }

    // Real-time validation feedback
    document.querySelectorAll('input[required], select[required]').forEach(input => {
      input.addEventListener('input', () => {
        if (input.value.trim() === '') {
          input.classList.add('invalid');
        } else {
          input.classList.remove('invalid');
        }
      });
    });
  </script>

  <style>
    input.invalid, select.invalid {
      border-color: red;
    }
  </style>

</body>
</html>
