<?php
// Set timezone to Philippines/Manila
date_default_timezone_set('Asia/Manila');

// Database connection
require_once '../config/config.php';

function printAttendance($user_id, $message) {
    try {
        // Get user details from the database
        global $conn;
        $sql = "SELECT firstname, lastname FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing user query");
        }
        
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            throw new Exception("User not found");
        }

        // Format the print data
        $printData = array(
            'datetime' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'name' => $user['firstname'] . ' ' . $user['lastname'],
            'message' => $message
        );

        // You can customize the printing format here
        // For now, we'll just return true to indicate success
        // If you have a specific printer setup, you can add the code here
        
        return true;
    } catch (Exception $e) {
        error_log("Print Error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputData = json_decode(file_get_contents("php://input"), true);
    $user_id = $inputData['user_id'] ?? null;

    if ($user_id) {
        $current_date = date("Y-m-d");
        $current_datetime = date("Y-m-d H:i:s");

        try {
            // Check for existing check-in
            $check_sql = "SELECT * FROM presencemonitoring WHERE user_id = ? AND date = ? AND check_out IS NULL";
            $stmt = $conn->prepare($check_sql);
            
            if (!$stmt) {
                throw new Exception("Database error in prepare statement.");
            }
            
            $stmt->bind_param("ss", $user_id, $current_date);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Process check-out
                $update_sql = "UPDATE presencemonitoring SET check_out = ? WHERE user_id = ? AND date = ? AND check_out IS NULL";
                $update_stmt = $conn->prepare($update_sql);
                
                if (!$update_stmt) {
                    throw new Exception("Database error in update statement.");
                }
                
                $update_stmt->bind_param("sss", $current_datetime, $user_id, $current_date);
                if ($update_stmt->execute()) {
                    $printStatus = printAttendance($user_id, "Check-out logged at $current_datetime");
                    $message = 'Check-out successful';
                    if (!$printStatus) {
                        $message .= ' (Print failed)';
                    }
                    echo json_encode(['status' => 'success', 'message' => $message]);
                } else {
                    throw new Exception("Failed to log check-out");
                }
                $update_stmt->close();
            } else {
                // Process check-in
                $insert_sql = "INSERT INTO presencemonitoring (user_id, check_in, date) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                
                if (!$insert_stmt) {
                    throw new Exception("Database error in insert statement.");
                }
                
                $insert_stmt->bind_param("sss", $user_id, $current_datetime, $current_date);
                if ($insert_stmt->execute()) {
                    $printStatus = printAttendance($user_id, "Check-in logged at $current_datetime");
                    $message = 'Check-in successful';
                    if (!$printStatus) {
                        $message .= ' (Print failed)';
                    }
                    echo json_encode(['status' => 'success', 'message' => $message]);
                } else {
                    throw new Exception("Failed to log check-in");
                }
                $insert_stmt->close();
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID in request']);
    }
    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Tracking and Printing</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        #result {
            display: none;
        }
        .status-message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <h2 class="text-center">QR Code Tracking and Printing</h2>
        <div class="row justify-content-center mt-4">
            <div id="result" class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title">Scanned Data</h3>
                        <p class="card-text"><strong>User ID:</strong> <span id="id_display"></span></p>
                        <div id="status_message" class="status-message"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let scannedData = '';
        let isProcessing = false;
        const PROCESSING_TIMEOUT = 2000; // 2 seconds timeout

        window.addEventListener('keydown', function(event) {
            if (isProcessing) {
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                if (scannedData.length > 0) {
                    processStudentID(scannedData.trim());
                    scannedData = '';
                }
            } else if (event.key.length === 1) {
                scannedData += event.key;
            }
        });

        function showStatus(message, isError = false) {
            const statusDiv = document.getElementById('status_message');
            statusDiv.textContent = message;
            statusDiv.className = 'status-message ' + (isError ? 'error' : 'success');
            document.getElementById('result').style.display = 'block';
        }

        function processStudentID(studentID) {
            if (isProcessing || !studentID) {
                return;
            }

            isProcessing = true;
            document.getElementById('id_display').textContent = studentID;
            document.getElementById('result').style.display = 'block';

            fetch('scan_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: studentID })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showStatus(data.message);
                } else {
                    showStatus(data.message, true);
                }
            })
            .catch(error => {
                showStatus('Error processing scan. Please try again.', true);
                console.error('Request failed:', error);
            })
            .finally(() => {
                setTimeout(() => {
                    isProcessing = false;
                }, PROCESSING_TIMEOUT);
            });
        }
    </script>
</body>
</html>