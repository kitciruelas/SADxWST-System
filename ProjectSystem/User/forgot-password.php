<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php
    // Properly include 'config.php'
    include '../config/user-reset.php'; // or require 'config.php';
  ?>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 350px;
        }

        .logo {
            height: 40px;
            background: url('../CSS/logo.png') no-repeat center;
            background-size: contain;
            margin-bottom: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        .password-container {
            position: relative;
            margin-bottom: 10px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #218838;
        }

        p a {
            text-align: center;
            text-decoration: none; /* Remove underline */
            color: #007bff;

        }
        p a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo"></div>
        <h2>Reset Your Password</h2>
        <form action="../config/user-reset.php" method="post">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>

            <label for="new_pass">New Password:</label>
            <div class="password-container">
                <input type="password" id="new_pass" name="new_pass" required>
                <i class="fas fa-eye-slash toggle-password" id="toggleNewPass" title="Show Password"></i>
            </div>

            <label for="confirm_pass">Confirm Password:</label>
            <div class="password-container">
                <input type="password" id="confirm_pass" name="confirm_pass" required>
                <i class="fas fa-eye-slash toggle-password" id="toggleConfirmPass" title="Show Password"></i>
            </div>

            <button type="submit">Reset Password</button>
        </form>
        <p>Remember your password? <a href="user-login.php"> Sign in here</a></p>
    </div>

    <script>
        // Toggle password visibility for new password
        const toggleNewPass = document.getElementById('toggleNewPass');
        const newPassInput = document.getElementById('new_pass');

        toggleNewPass.addEventListener('click', function() {
            const type = newPassInput.type === 'password' ? 'text' : 'password';
            newPassInput.type = type;
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Toggle password visibility for confirm password
        const toggleConfirmPass = document.getElementById('toggleConfirmPass');
        const confirmPassInput = document.getElementById('confirm_pass');

        toggleConfirmPass.addEventListener('click', function() {
            const type = confirmPassInput.type === 'password' ? 'text' : 'password';
            confirmPassInput.type = type;
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
