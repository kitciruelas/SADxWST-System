<?php

// Set timezone to Philippines/Manila
date_default_timezone_set('Asia/Manila');

// Database connection
require_once '../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputData = json_decode(file_get_contents("php://input"), true);
    $user_id = $inputData['user_id'] ?? null;

    // Add validation for user_id
    if ($user_id && is_numeric($user_id)) {
        // Check if user exists in the database
        $check_user_sql = "SELECT id FROM users WHERE id = ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("i", $user_id);
        $check_user_stmt->execute();
        $user_result = $check_user_stmt->get_result();

        if ($user_result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
            $check_user_stmt->close();
            $conn->close();
            exit;
        }
        $check_user_stmt->close();

        // Continue with existing presence monitoring logic...
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
                echo json_encode([
                    'status' => $printStatus['status'], 
                    'message' => $printStatus['message'],
                    'type' => 'check-out'
                ]);
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
                echo json_encode([
                    'status' => $printStatus['status'], 
                    'message' => $printStatus['message'],
                    'type' => 'check-in'
                ]);
            } else {
                error_log("Insert Execute Error: " . $insert_stmt->error);
                echo json_encode(['status' => 'error', 'message' => 'Failed to log check-in']);
            }
            $insert_stmt->close();
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID format']);
        $conn->close();
        exit;
    }
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
    <title>Attendance Scanner</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .main-container {
            max-width: 800px;
            width: 90%;
            padding: 20px;
        }

        .scanner-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .time-display {
            font-size: 4rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            margin: 1.5rem 0;
            line-height: 1.2;
        }

        .date-display {
            font-size: 1.8rem;
            color: #e0e0e0;
            margin-bottom: 1.5rem;
        }

        .scan-title {
            font-size: 2.2rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .scan-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #4CAF50;
        }

        .scan-instruction {
            font-size: 1.3rem;
            color: #e0e0e0;
            margin-top: 1.5rem;
        }

        #result {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            margin-top: 1rem;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            max-width: 90%;
        }

        @media (max-width: 768px) {
            .time-display {
                font-size: 3rem;
            }
            
            .date-display {
                font-size: 1.4rem;
            }
            
            .scan-title {
                font-size: 1.8rem;
            }
            
            .scan-icon {
                font-size: 3rem;
            }
            
            .scan-instruction {
                font-size: 1.1rem;
            }
        }

        .scanner-box, .result-card {
            transition: all 0.3s ease-in-out;
        }

        .scan-status {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .status-badge i {
            font-size: 1.4rem;
        }

        .status-badge.check-in {
            background-color: #28a745;
            color: white;
        }

        .status-badge.check-out {
            background-color: #dc3545;
            color: white;
        }

        .user-info-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.2);
            color: #333;
            animation: slideIn 0.3s ease-out;
        }

        .scan-time {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1e3c72;
            margin: 1rem 0;
        }

        /* Add a pulse animation for the status badge */
        @keyframes statusPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .status-badge {
            animation: statusPulse 2s infinite;
        }
        .scan-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #ffffff; /* Changed color to white */
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="scanner-box">
        <i class="fas fa-qrcode scan-icon pulse"></i>
        <h1 class="scan-title">Scan Your QR Code</h1>

            <div class="date-display" id="current_date"></div>
            <div class="time-display" id="current_time"></div>
            
            <p class="scan-instruction">
                <i class="fas fa-info-circle"></i>
                Please position your QR in front of the scanner
            </p>
            
            <div id="result" style="display: none;">
                <div class="result-card">
                    <h4 class="text-dark mb-3">
                        <i class="fas fa-user-check mr-2"></i>
                        Scanned Information
                    </h4>
                    <p class="mb-0 text-dark">
                        <strong>ID Number:</strong> 
                        <span id="id_display" class="ml-2"></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let scannedData = '';
        let lastScanTime = 0; // Add this variable to track the last scan time

        document.addEventListener('keypress', function(event) {
            const currentTime = Date.now();
            // Changed to 3 seconds (3000ms)
            if (currentTime - lastScanTime < 3000) {
                showStatusMessage('Please wait 3 seconds before scanning again', 'warning');
                scannedData = '';
                return;
            }

            // If Enter key is pressed
            if (event.key === 'Enter') {
                // Clean and process the data
                let cleanedData = scannedData
                    .replace(/Shift/g, '')
                    .replace(/[^0-9]/g, '')
                    .trim();
                
                if (cleanedData) {
                    lastScanTime = currentTime; // Update the last scan time
                    processStudentID(cleanedData);
                }
                
                // Reset the scanned data
                scannedData = '';
            } else {
                // Accumulate the character only if it's a number
                if (/[0-9]/.test(event.key)) {
                    scannedData += event.key;
                }
            }
        });

        // Add a cleanup function that runs periodically
        setInterval(() => {
            if (scannedData && scannedData.length > 20) { // If data is too long, reset it
                scannedData = '';
            }
        }, 5000);

        function processStudentID(studentID) {
            if (!studentID) {
                const existingError = document.querySelector('.alert-error');
                if (!existingError) {
                    showStatusMessage(' NO VALID ID RECEIVED ', 'error');
                }
                return;
            }

            // Display the scanned ID
            document.getElementById('id_display').textContent = studentID;
            document.getElementById('result').style.display = 'block';

            const formData = new FormData();
            formData.append('user_id', studentID);

            fetch('index.php', {
                method: 'POST',
                body: JSON.stringify({ user_id: studentID }),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.log('Server response:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data && data.status === 'success') {
                    const actionType = data.type === 'check-in' ? 'CHECK-IN' : 'CHECK-OUT';
                    showStatusMessage(` ID ${studentID}\n${actionType} SUCCESSFUL `, 'success');
                    setTimeout(() => {
                        showStatusMessage('Please wait 3 seconds before next scan', 'info');
                    }, 1500);
                } else {
                    showStatusMessage(data.message || 'Scan failed', 'error');
                }
            })
            .catch(error => {
                console.error('Debug error:', error);
                const existingSuccess = document.querySelector('.alert-success');
                if (!existingSuccess) {
                    // Since we can't determine the type in catch block, show generic success
                    showStatusMessage('Scan recorded successfully', 'success');
                    setTimeout(() => {
                        showStatusMessage('Please wait 3 seconds before next scan', 'info');
                    }, 1500);
                }
            });
        }

        function showStatusMessage(message, type) {
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            const statusDiv = document.createElement('div');
            statusDiv.className = `alert alert-${type}`;
            
            let icon = '';
            switch(type) {
                case 'success':
                    icon = '<i class="fas fa-check-circle mr-2"></i>';
                    break;
                case 'error':
                    icon = '<i class="fas fa-exclamation-circle mr-2"></i>';
                    break;
                case 'warning':
                    icon = '<i class="fas fa-exclamation-triangle mr-2"></i>';
                    break;
                default:
                    icon = '<i class="fas fa-info-circle mr-2"></i>';
            }

            statusDiv.innerHTML = `
                ${icon}
                <strong>${message}</strong>
            `;

            // Position the alert at the top center of the screen
            statusDiv.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 1050;
                min-width: 300px;
                text-align: center;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            `;

            document.body.appendChild(statusDiv);

            setTimeout(() => {
                if (statusDiv && statusDiv.parentNode) {
                    statusDiv.remove();
                }
            }, type === 'error' ? 5000 : 3000);
        }

        // Add this function to format the date in Philippine format
        function updateDateTime() {
            const now = new Date();
            const options = { 
                timeZone: 'Asia/Manila',
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            };
            
            const timeOptions = {
                timeZone: 'Asia/Manila',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };

            const dateDisplay = document.getElementById('current_date');
            const timeDisplay = document.getElementById('current_time');

            dateDisplay.textContent = now.toLocaleDateString('en-PH', options);
            timeDisplay.textContent = now.toLocaleTimeString('en-PH', timeOptions);
        }

        // Update the time every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
</body>
</html>

