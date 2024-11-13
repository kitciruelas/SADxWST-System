<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Code for Users</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Generate QR Code for Users</h5>
                </div>
                <div class="card-body">
                <?php 
require '../phpqrcode/qrlib.php'; // Path to phpqrcode library
require_once '../config/config.php'; // Database connection file

// Check if form was submitted to generate QR code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']); // Sanitize user ID

    // Fetch the selected user's details along with the room number
    $sql = "
        SELECT users.id, users.fname, users.lname, rooms.room_number 
        FROM users 
        LEFT JOIN roomassignments ON users.id = roomassignments.user_id 
        LEFT JOIN rooms ON roomassignments.room_id = rooms.room_id 
        WHERE users.id = $user_id
    ";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Data to encode in QR code (ID, Name, and Room Number)
        $text = "ID: " . $row['id'] . "\nName: " . $row['fname'] . " " . $row['lname'];
        $text .= !empty($row['room_number']) ? "\nRoom Number: " . $row['room_number'] : "\nRoom: No room assigned";

        // Ensure the QR codes directory exists
        $directory = '../qrcodes/';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        // Define the file name for the QR code image
        $file = $directory . 'user_' . $row['id'] . '.png';

        // Generate and save the QR code
        QRcode::png($text, $file, QR_ECLEVEL_L, 10);

        echo "<div class='alert alert-success'>Generated QR code for user: " . htmlspecialchars($row['fname']) . " " . htmlspecialchars($row['lname']) . "</div>";
        echo "<div class='text-center'><img src='$file' alt='QR Code' class='img-thumbnail'></div><br>";
    } else {
        echo "<div class='alert alert-danger'>User not found or database error.</div>";
    }
}

// Fetch users from the `users` table for the dropdown
$sql = "SELECT id, fname, lname FROM users";
$result = $conn->query($sql);

// Debugging output: check if query is successful and if there are results
if (!$result) {
    echo "<div class='alert alert-danger'>Error in fetching users: " . $conn->error . "</div>";
} elseif ($result->num_rows == 0) {
    echo "<div class='alert alert-warning'>No users found in the database.</div>";
}

// Display the form with user dropdown
?>
<form method="POST" action="">
    <div class="form-group">
        <label for="user">Select a user to generate QR code:</label>
        <select name="user_id" id="user" class="form-control" required>
            <option value="" disabled selected>Select a user</option>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['fname']) . " " . htmlspecialchars($row['lname']) . "</option>";
                }
            }
            ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Generate QR Code</button>
</form>

                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Generate QR Code</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
