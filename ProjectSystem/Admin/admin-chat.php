<?php
session_start();
include '../config/config.php';

date_default_timezone_set('Asia/Manila');

// Check if admin ID is set in session
if (!isset($_SESSION['id'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$admin_id = $_SESSION['id']; // Set admin ID from session

// Verify admin_id exists in the admin table
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id); // Use $admin_id to verify the logged-in admin
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // admin_id does not exist in the admin table; return an error
    die(json_encode(["error" => "Admin ID not found in database."]));
}

while ($row = $result->fetch_assoc()) {
    // Process the result (if necessary, e.g., to get admin details)
}

$stmt->close();

// Handle message insertion
if (isset($_POST['msg'])) {
    $msg = htmlspecialchars($_POST['msg']);
    $created_at = date('Y-m-d H:i:s');
    
    // Default to broadcast to all users if no receiver_id is provided
    $receiver_id = $_POST['receiver_id'] ?? 0; // Set to 0 for a broadcast message

    // Prepare and execute message insert
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, timestamp) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $admin_id, $receiver_id, $msg, $created_at);

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

function getMessages($conn, $receiverId = 0) {
    $query = "SELECT cm.id, cm.message, cm.sender_id, cm.receiver_id, cm.timestamp, 
                     CASE 
                         WHEN a.id IS NOT NULL THEN CONCAT(a.fname, ' ')
                         WHEN s.id IS NOT NULL THEN CONCAT(s.fname, ' ')
                         WHEN u.id IS NOT NULL THEN CONCAT(u.fname, ' ')
                         ELSE NULL
                     END AS sender_name, 
                     CASE 
                         WHEN a.id IS NOT NULL THEN 'Admin'
                         WHEN s.id IS NOT NULL THEN s.role
                         WHEN u.id IS NOT NULL THEN u.role
                         ELSE NULL
                     END AS role
              FROM chat_messages cm
              LEFT JOIN admin a ON cm.sender_id = a.id
              LEFT JOIN staff s ON cm.sender_id = s.id
              LEFT JOIN users u ON cm.sender_id = u.id
              WHERE cm.receiver_id = 0 OR cm.receiver_id = ? -- 0 for broadcast messages
              ORDER BY cm.timestamp ASC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("i", $receiverId);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    $stmt->close();
    return $messages;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../User/Css_user/visitor-logs.css">
    <link rel="icon" href="img-icon/chats.png" type="image/png">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
<!-- Bootstrap 4 CSS -->
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap 4 JS and Popper.js (required for modals) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <title>Dormio - Group Chat</title>

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
            height: 100vh; /* 70% of the viewport height */
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
/* Scrollbar styling for message details */
#messageDetails::-webkit-scrollbar {
    width: 8px;
}

#messageDetails::-webkit-scrollbar-track {
    background-color: #f1f1f1;
    border-radius: 10px;
}

#messageDetails::-webkit-scrollbar-thumb {
    background-color: #ced4da;
    border-radius: 10px;
}

#messageDetails::-webkit-scrollbar-thumb:hover {
    background-color: #adb5bd;
}



    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link" ><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>
            <a href="admin-chat.php" class="nav-link active"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>
        

        </div>
        
        <div class="logout">
        <a href="../config/logout.php" onclick="return confirmLogout();">
    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
</a>

<script>
function confirmLogout() {
    return confirm("Are you sure you want to log out?");
}
</script>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
    <h2>Dormio - Group Chat</h2>


      

</div>


    </div>
    <div class="main-content">
    <div id="container" class="container-fluid d-flex flex-column h-10" style="width: 1000px;">
        <div class="main flex-grow-1 d-flex flex-column" style="padding: 10px;">
            <div class="search-card">
                <!-- Search Input -->
                <div class="input-group search-bar mb-3">
                    <input type="text" id="search" class="form-control" placeholder="Search messages..." aria-label="Search messages">
                    
                </div>
                
            </div>

            <div class="d-flex">

<!-- Chatlogs Section (Left) -->
<div id="chatlogs" class="d-flex flex-column">
    <?php 
    // Fetch messages
    $messages = getMessages($conn);
    
    // Check if there are messages
    if (empty($messages)): ?>
        <div class="no-chat-history" style="text-align: center; color: #888; font-size: 18px; padding: 20px;">
            No chat history available.
        </div>
    <?php else: 
        // Display messages
        foreach ($messages as $message): 
            // Check if the message was sent by the current user
            $message_type = ($message['sender_id'] === $_SESSION['id']) ? 'sent' : 'received';
    ?>
    <div class="message-container <?= htmlspecialchars($message_type) ?>" onclick="showDetails(<?= htmlspecialchars($message['id']) ?>)">
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
    <?php endforeach; 
    endif; ?>
    
    <!-- Sticky Message Form -->
    <form name="form1" id="messageForm" onsubmit="return submitchat()" action="staff-chat.php" method="POST" class="mt-5" style="position: sticky; bottom: 0; background-color: white; z-index: 10; padding: 10px; border-top: 1px solid #ddd;">
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

<!-- Details Section (Right) -->
<div id="messageDetails" class="d-flex flex-column" style="width: 300px; padding-left: 20px; height: 100vh; overflow-y: auto;">
    
    <div class="container">
        
        <h4 class="text-center mb-1">Members of Group Chat</h4>

        <div class="list-group">
            <?php
            // Query to fetch user details
            $query = "SELECT id, fname, lname, role FROM users
            UNION
            SELECT id, fname, lname, role FROM staff
            ORDER BY id DESC";
              $result = mysqli_query($conn, $query);

            // Check if there are results
            if (mysqli_num_rows($result) > 0) {
                // Loop through each row and display the role and name
                while ($row = mysqli_fetch_assoc($result)) {
                    $fullName = $row['fname'] . ' ' . $row['lname']; // Concatenate first and last name
                    $role = $row['role']; // Get the user's role
            ?>
                    <!-- Button that triggers the modal -->
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-2"
        data-toggle="modal" data-target="#userModal" 
        data-id="<?= $row['id'] ?>" 
        data-fname="<?= $row['fname'] ?>" 
        data-lname="<?= $row['lname'] ?>" 
        data-role="<?= $row['role'] ?>">
    <div>
        <strong><?= htmlspecialchars($fullName) ?></strong>
        <div>
            <?php
            // Display role as badge
            if ($role == 'Admin') {
                echo '<span class="badge badge-primary">Admin</span>';
            } elseif ($role == 'Staff') {
                echo '<span class="badge badge-success">Staff</span>';
            } else {
                echo '<span class="badge badge-info">User</span>';
            }
            ?>
        </div>
    </div>
</button>

            <?php
                }
            } else {
                echo "<p>No users found.</p>";
            }

            // Close the database connection
            mysqli_close($conn);
            ?>
        </div>
    </div>
</div>


</div>
</div>


</div>

</div>

<!-- jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (required for Bootstrap's JavaScript components) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>


    function applyFilters() {
    var selectedRole = document.getElementById('roleSelect').value; // Get selected role
    var userItems = document.querySelectorAll('.list-group-item'); // Get all user items

    userItems.forEach(function(item) {
        var userRole = item.getAttribute('data-role'); // Get the role of the current user item

        if (selectedRole === 'all' || userRole === selectedRole) {
            item.style.display = ''; // Show item
        } else {
            item.style.display = 'none'; // Hide item
        }
    });
}

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
        url: "admin-chat.php",  // Specify the correct endpoint for sending messages
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
            url: "admin-chat.php",
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
            url: "admin-chat.php",  // Specify the correct endpoint for deleting messages
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
