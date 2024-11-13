<?php
session_start();
include '../config/config.php';

date_default_timezone_set('Asia/Manila');

// Check if user_id is set in session
if (!isset($_SESSION['id'])) {
    header("Location: user-login.php"); // Redirect to login page
    exit();
}

$user_id = $_SESSION['id']; // Set user_id from session

// Verify user_id exists in staff table
$stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->bind_param("i", $user_id); // Use $user_id to verify the logged-in user
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // user_id does not exist in staff table; return an error
    die(json_encode(["error" => "User ID not found in database."]));
}

while ($row = $result->fetch_assoc()) {
    // Process the result (if necessary, e.g., to get staff details)
}

$stmt->close();

// Handle message insertion
if (isset($_POST['msg'])) {
    $msg = htmlspecialchars($_POST['msg']);
    $created_at = date('Y-m-d H:i:s');
    
    // Default to broadcast to all users if no receiver_id is provided
    if (isset($_POST['receiver_id'])) {
        $receiver_id = $_POST['receiver_id'];
    } else {
        $receiver_id = 0;  // Set to 0 for a broadcast message
    }

    // Prepare and execute message insert
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, timestamp) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $receiver_id, $msg, $created_at);

    if ($stmt->execute()) {
        echo json_encode(getMessages($conn));  // Return messages after successful insert
    } else {
        echo json_encode(["error" => "Message insertion failed"]);
    }
    $stmt->close();

    exit;
}
// Handle message editing
if (isset($_POST['edit_id']) && isset($_POST['edit_msg'])) {
    $edit_id = intval($_POST['edit_id']);
    $edit_msg = htmlspecialchars($_POST['edit_msg']);

    // Update statement for editing messages in the 'chat_messages' table
    $stmt = $conn->prepare("UPDATE chat_messages SET message = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $edit_msg, $edit_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(getMessages($conn));  // Return the updated messages
    } else {
        error_log("Error in SQL statement: " . $conn->error);
        echo json_encode(["error" => "Message update failed"]);
    }
    exit;
}

// Handle message deletion
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);

    // Delete statement for removing messages from the 'chat_messages' table
    $stmt = $conn->prepare("DELETE FROM chat_messages WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(getMessages($conn));  // Return the updated messages list
    } else {
        error_log("Error in SQL statement: " . $conn->error);
        echo json_encode(["error" => "Message deletion failed"]);
    }
    exit;
}

// Function to retrieve messages
function getMessages($conn) {
    $query = "SELECT cm.id, cm.message, cm.sender_id, cm.receiver_id, cm.timestamp, 
                     COALESCE(CONCAT(s.fname), u.fname) AS sender_name, 
                     COALESCE(s.role, 'User') AS role
              FROM chat_messages cm
              LEFT JOIN staff s ON cm.sender_id = s.id
              LEFT JOIN users u ON cm.sender_id = u.id
              WHERE cm.receiver_id = 0 OR cm.receiver_id = ?  -- 0 is for broadcast messages
              ORDER BY cm.timestamp ASC";

    $stmt = $conn->prepare($query);
  

    $stmt->bind_param("i", $_SESSION['id']);  // Use the current user ID for filtering messages
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch all results as an associative array
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    
    return $messages;
}
function getStaffRole($conn, $staffId) {
    $query = "SELECT role FROM users WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        // Handle error
        return null;
    }

    $stmt->bind_param("i", $staffId);  // Bind the staff ID parameter
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the role
    if ($row = $result->fetch_assoc()) {
        return $row['role'];
    } else {
        return null;  // No role found
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../User/Css_user/visitor-logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
<link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

<!-- jQuery (needed for Bootstrap's JavaScript plugins) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="staff-chat.php" class="nav-link active"><i class="fas fa-comments"></i> <span>Chat</span></a>

        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Dormio - Group Chat</h2>

    </div>

    <style>
        body {
            font-family: 'Arial', sans-serif;
            max-width: 800px;
            margin: 100px;
        }
        .header {
            background-color: #343a40;
            padding: 10px;
            color: white;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
        }
        #chatlogs {
            background-color: white;
            width: 100%;
            border-radius: 10px;
            padding: 10px;
            flex-grow: 1;
            height: 60vh; /* 70% of the viewport height */
            overflow-y: auto; /* Allow scrolling if content exceeds height */
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .message {
    max-width: 60%;
    margin-bottom: 10px;
    padding: 10px;
    border-radius: 15px;
    display: inline-block;
    clear: both;
    position: relative; /* For positioning the menu */
}

.message.sent {
    background-color: #37AFE1
    ;
    color: white;
    float: right;
    margin-left: auto;
    position: relative;
}

.message.received {
    background-color: #e9ecef;
    color: black;
    float: left;
    margin-right: auto;
    position: relative;
}

.message-menu {
    position: absolute;
    top: 30%;
    right: 100%;
    cursor: pointer;
    color: #1A1A1D; /* Ensure the icon color is visible */
    font-size: 1.2rem; /* Increase size if needed */
    margin-right: 5PX;
}

.menu-options {
    display: none; /* Hidden by default */
    position: absolute;
    top: 25px; /* Position below the three dots */
    right: 0;
    background-color:white;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
    z-index: 1;
    padding: 5px 0;
    width: 100px;
}

.menu-options div {
    padding: 8px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    font-size: 0.9rem;
}

.menu-options i {
    margin-right: 5px;
}

        /* Clearfix for chatlogs to contain floated elements */
        #chatlogs::after {
            content: "";
            display: table;
            clear: both; /* Ensures the container wraps floated children */
        }
        .input-group {
    border: 1px solid #ced4da;
    border-radius: 20px; /* Rounded corners */
    overflow: hidden; /* Prevent overflow */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.input-group textarea {
    resize: none;
    overflow: hidden; /* Hide scrollbars */
    border: none;
    border-radius: 0; /* Remove individual corner radius */
    padding: 10px;
    min-height: 20px;
    font-size: 1rem;
    line-height: 1.5;
    outline: none; /* Remove outline on focus */
}

.input-group textarea:focus {
    box-shadow: none;
}

.input-group .btn-primary {
    border-radius: 0; /* Reset button corner radius */
    border-top-right-radius: 20px; /* Rounded right corners */
    border-bottom-right-radius: 20px;
    font-weight: bold;
    padding: 10px 10px;
    display: flex;
    align-items: center;
}

.input-group .btn-primary:focus {
    box-shadow: none;
    outline: none;
}

        .search-bar {
            margin-bottom: 10px;
            width: 50%;
        }
        .message-buttons {
            margin-top: 2px; /* Reduced margin */
        }
        .message .small {
            font-size: 0.75em; /* Smaller font size */
            color: #6c757d;
            margin-top: 2px; /* Reduced margin */
        }
       

/* Profile picture styling */
.profile-pic {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
    flex-shrink: 0;
}


/* Scrollbar styling for chat logs */
#chatlogs::-webkit-scrollbar {
    width: 8px;
}

#chatlogs::-webkit-scrollbar-track {
    background-color: #f1f1f1;
    border-radius: 10px;
}

#chatlogs::-webkit-scrollbar-thumb {
    background-color: #ced4da;
    border-radius: 10px;
}

#chatlogs::-webkit-scrollbar-thumb:hover {
    background-color: #adb5bd;
}




.message-menu .fas.fa-ellipsis-v {
    cursor: pointer;
}



    </style>
</head>
<body>
<div class="main-content">


<div id="container" class="container-fluid d-flex flex-column h-100"style="width: 900px;">
      
        
        <div class="main flex-grow-1 d-flex flex-column" style="padding: 10px;">
        <div class="search-card">
    <!-- Search Input -->
    <div class="input-group search-bar">
        <input type="text" id="search" class="form-control" placeholder="Search messages..." aria-label="Search messages">
        <label for="roleSelect">Filter by Role:</label>
        <select id="roleSelect" class="form-control" onchange="applyFilters()">
            <option value="all">All</option>
            <option value="admin">Admin</option>
            <option value="staff">Staff</option>
            <option value="user">User</option>
        </select>
    </div>
    </div>

    <div id="chatlogs" class="d-flex flex-column">
    <?php 
    // Display messages
    foreach (getMessages($conn) as $message): 
        // Check if the message was sent by the current user
        $message_type = ($message['sender_id'] === $_SESSION['id']) ? 'sent' : 'received';
    ?>
    <div class="message-container <?= htmlspecialchars($message_type) ?>">
        <?php if ($message_type === 'received' && isset($message['profile_pic'])): ?>
            <img src="<?= htmlspecialchars($message['profile_pic']) ?>" alt="Profile Picture" class="profile-pic">
        <?php endif; ?>
        
        <div class="message <?= htmlspecialchars($message_type) ?>" data-id="<?= htmlspecialchars($message['id']) ?>">
            <?php if ($message_type === 'sent'): ?>
                <div class="message-menu">
                    <i class="fas fa-ellipsis-v" onclick="toggleMenu(this)"></i>
                    <div class="menu-options">
                        <div onclick="editMessage(<?= htmlspecialchars($message['id']) ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </div>
                        <div onclick="deleteMessage(<?= htmlspecialchars($message['id']) ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <strong><?= htmlspecialchars($message['sender_name']) ?> (<?= htmlspecialchars($message['role']) ?>)</strong>
            <div class="message-content"><?= htmlspecialchars($message['message']) ?></div>
            <div class="small text-muted text-right"><?= date('h:i A', strtotime($message['timestamp'])) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

                        
            </div>

            <form name="form1" id="messageForm" onsubmit="return submitchat()" action="staff-chat.php" method="POST" class="mt-1">
            <div class="input-group mt-2">
                    <textarea name="msg" class="form-control" placeholder="Your message here..." required></textarea>
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane fa-2x"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

       
    </div>

</div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>

function applyFilters() {
    var searchQuery = document.getElementById('search').value.toLowerCase();
    var roleFilter = document.getElementById('roleSelect').value;
    var messages = document.querySelectorAll('#chatlogs .message');
    
    messages.forEach(function(message) {
        var content = message.querySelector('.message-content').textContent.toLowerCase();
        var userRole = message.querySelector('.message').getAttribute('data-role'); // Assuming you can get role as data attribute
        var matchesSearch = content.includes(searchQuery);
        var matchesRole = roleFilter === 'all' || userRole === roleFilter;

        if (matchesSearch && matchesRole) {
            message.style.display = '';  // Show message
        } else {
            message.style.display = 'none';  // Hide message
        }
    });
}

        document.getElementById('search').addEventListener('input', function() {
    var searchQuery = this.value.toLowerCase();
    var messages = document.querySelectorAll('#chatlogs .message');

    messages.forEach(function(message) {
        var content = message.querySelector('.message-content').textContent.toLowerCase();
        if (content.includes(searchQuery)) {
            message.style.display = '';  // Show message
        } else {
            message.style.display = 'none';  // Hide message
        }
    });
});

      function toggleMenu(element) {
    var menu = element.nextElementSibling;
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    document.addEventListener('click', function(event) {
        if (!element.contains(event.target) && !menu.contains(event.target)) {
            menu.style.display = 'none';
        }
    }, { once: true });
}

function submitchat() {
    var msg = document.forms["form1"]["msg"].value;
    if (msg === '') {
        alert('Enter a message!');
        return false;  // Prevent form submission if the message is empty
    }

    $.ajax({
        type: "POST",
        url: "staff-chat.php",  // Specify the correct endpoint for sending messages
        data: { msg: msg },
        success: function(response) {
            window.location.reload();  // Reload the page after submitting the message
            document.getElementById("messageForm").reset();  // Clear the form
            scrollToBottom();  // Scroll to the bottom after sending a message

        },
        error: function() {
            alert('Message could not be sent. Please try again.');  // Handle errors
        }
    });

    return false;  // Prevent default form submission behavior
}

function updateChatLogs(messages) {
    $('#chatlogs').empty();
    messages.forEach(function(message) {
        var messageClass = message.type === 'sent' ? 'sent' : 'received';
        var messageHtml = `
            <div class="message ${messageClass}" data-id="${message.id}">
                <strong>${message.user_id}:</strong>
                <div class="message-content">${message.content}</div>
                <div class="small text-muted text-right">${new Intl.DateTimeFormat('en-US', {
                    timeZone: 'Asia/Manila',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }).format(new Date(message.created_at))} PHT</div>
                ${message.type === 'sent' ? `
                   
                ` : ''}
            </div>`;
        $('#chatlogs').append(messageHtml);
    });
    scrollToBottom();
}


function scrollToBottom() {
    var chatlogs = document.getElementById("chatlogs");
    chatlogs.scrollTop = chatlogs.scrollHeight;  // Scroll to the bottom of the chatlog
}

$(document).ready(function() {
    var chatlogs = document.getElementById("chatlogs");
    var scrollPosition = localStorage.getItem("chatScrollPosition");

    if (scrollPosition) {
        chatlogs.scrollTop = scrollPosition;
    } else {
        scrollToBottom();
    }
    localStorage.removeItem("chatScrollPosition");
});

function editMessage(messageId) {
    var newContent = prompt("Edit your message:");
    if (newContent) {
        $.ajax({
            type: "POST",
            url: "staff-chat.php",
            data: { edit_id: messageId, edit_msg: newContent },
            success: function(response) {
                window.location.reload();  // Reload the page after submitting the message
            },
            error: function() {
                alert("Message could not be edited.");
            }
        });
    }
}


function deleteMessage(messageId) {
    if (confirm("Are you sure you want to delete this message?")) {
        $.ajax({
            type: "POST",
            url: "staff-chat.php",  // Specify the correct endpoint for deleting messages
            data: { delete_id: messageId },
            success: function(response) {
               
                window.location.reload();  // Reload the page after submitting the message

            },
            error: function() {
                alert('Message could not be deleted. Please try again.');
            }
        });
    }
}

        
         // Sidebar toggle
         const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');

        sidebar.classList.add('collapsed');
        hamburgerMenu.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = hamburgerMenu.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
        });
    </script>
</body>
</html>
