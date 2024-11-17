<?php
// Set timezone to Philippines/Manila
date_default_timezone_set('Asia/Manila');

// Database connection
require_once '../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputData = json_decode(file_get_contents("php://input"), true);
    $user_id = $inputData['user_id'] ?? null; // Expect 'user_id' instead of 'student_id'

    if ($user_id) {
        // Get the current date and time
        $current_date = date("Y-m-d");
        $current_datetime = date("Y-m-d H:i:s");

        // Check if there is already a check-in entry for the user today
        $check_sql = "SELECT * FROM presencemonitoring WHERE user_id = ? AND date = ? AND check_out IS NULL";
        $stmt = $conn->prepare($check_sql);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Database error in prepare statement.']);
            exit;
        }
        
        $stmt->bind_param("is", $user_id, $current_date); // Correct binding
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Entry exists for today without check_out, so update the check_out time
            $update_sql = "UPDATE presencemonitoring SET check_out = ? WHERE user_id = ? AND date = ? AND check_out IS NULL";
            $update_stmt = $conn->prepare($update_sql);
            
            if (!$update_stmt) {
                error_log("Update Prepare failed: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Database error in update statement.']);
                exit;
            }
            
            $update_stmt->bind_param("sss", $current_datetime, $user_id, $current_date); // Correct binding for all parameters
            if ($update_stmt->execute()) {
                // Send check-out confirmation for printing
                $printStatus = printAttendance($user_id, "Check-out logged at $current_datetime");
                echo json_encode(['status' => $printStatus['status'], 'message' => $printStatus['message']]);
            } else {
                error_log("Update Execute Error: " . $update_stmt->error);
                echo json_encode(['status' => 'error', 'message' => 'Failed to log check-out']);
            }
            $update_stmt->close();
        } else {
            // No open entry for today, so insert a new row with check_in
            $insert_sql = "INSERT INTO presencemonitoring (user_id, check_in, date) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            if (!$insert_stmt) {
                error_log("Insert Prepare failed: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Database error in insert statement.']);
                exit;
            }
            
            $insert_stmt->bind_param("sss", $user_id, $current_datetime, $current_date); // Correct binding for all parameters
            if ($insert_stmt->execute()) {
                // Send check-in confirmation for printing
                $printStatus = printAttendance($user_id, "Check-in logged at $current_datetime");
                echo json_encode(['status' => $printStatus['status'], 'message' => $printStatus['message']]);
            } else {
                error_log("Insert Execute Error: " . $insert_stmt->error);
                echo json_encode(['status' => 'error', 'message' => 'Failed to log check-in']);
            }
            $insert_stmt->close();
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID in request']);
    }
    $conn->close();
}

class GoojprtPrinter {
    public function __construct() {
        // Placeholder for printer connection initialization
    }

    public function printText($content) {
        if ($this->sendToPrinter($content)) {
            return true;
        } else {
            throw new Exception("Failed to send content to Goojprt printer.");
        }
    }

    private function sendToPrinter($content) {
        // Placeholder for actual printer communication logic
        return true; // Assume success in this placeholder
    }
}

function printAttendance($user_id, $message) {
    try {
        $printContent = "User ID: $user_id\n$message\nDate: " . date("Y-m-d H:i:s");
        $printer = new GoojprtPrinter();
        $printer->printText($printContent);

        return ['status' => 'success', 'message' => 'Printed successfully'];
    } catch (Exception $e) {
        error_log('Print Error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Failed to print: ' . $e->getMessage()];
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Tracking and Printing</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        #result {
            display: none; /* Hidden by default */
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <h2 class="text-center">QR Code Tracking and Printing</h2>

        <!-- Row with Scanned Data Card -->
        <div class="row justify-content-center mt-4">
            <div id="result" class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title">Scanned Data</h3>
                        <p class="card-text"><strong>Users ID:</strong> <span id="id_display"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
     let scannedData = '';

// Listen for all keydown events to capture scanner input
window.addEventListener('keydown', function(event) {
    console.log(event.key); // Debug log for each key pressed
    // Check if Enter key is pressed to indicate the end of the scanned data
    if (event.key === 'Enter') {
        processStudentID(scannedData);
        scannedData = ''; // Clear the buffer after processing
    } else {
        // Append each character to the scannedData buffer
        scannedData += event.key;
    }
});

function processStudentID(studentID) {
    if (!studentID) {
        console.error('No ID received');
        return;
    }

    // Display the scanned 
    document.getElementById('id_display').textContent = studentID;
    document.getElementById('result').style.display = 'block';

    fetch('scan_qr.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ user_id: studentID }) // Send 'user_id'
})
.then(response => {
    if (!response.ok) {
        throw new Error(`Server responded with status ${response.status}`);
    }
    return response.json(); // Attempt to parse JSON
})
.then(printResult => {
    console.log(printResult); // Log the result from the server
    if (printResult.status === 'success') {
        console.log('Scan processed and saved:', printResult.message);
        alert('Nadali mo ineng / utoy.');
    } else {
        console.error('Error:', printResult.message);
        alert(`Server error: ${printResult.message}`);
    }
})

.catch(error => {
    // Differentiate error types based on error message or code
    if (error.message.includes('Failed to fetch')) {
        alert('Network error: Unable to connect to the server.');
    } else if (error.message.includes('Server responded with status')) {
        alert(`Server error: ${error.message}`);
    } else {
        alert('An unexpected error occurred. Please try again.');
    }
    console.error('Request failed:', error);
});
}
    </script>
</body>
</html>
