<?php
session_start();

include '../config/config.php'; // Ensure this is correct


date_default_timezone_set('Asia/Manila');

// Check if username is set in session
if (!isset($_SESSION['username'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

// Handle message insertion
if (isset($_POST['msg'])) {
    $msg = htmlspecialchars($_POST['msg']);
    $username = $_SESSION['id'];
    $created_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO messages (content, username, type, created_at) VALUES (?, ?, 'sent', ?)");
    $stmt->bind_param("sss", $msg, $username, $created_at);
    $stmt->execute();
    $stmt->close();
    echo json_encode(getMessages($conn));
    exit;
}

// Handle received message insertion
if (isset($_POST['received_msg'])) {
    $received_msg = htmlspecialchars($_POST['received_msg']);
    $username = 'OtherUser'; // Replace with the actual sender's username
    $created_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO messages (content, username, type, created_at) VALUES (?, ?, 'received', ?)");
    $stmt->bind_param("sss", $received_msg, $username, $created_at);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(getMessages($conn));
    exit;
}

// Handle message editing
if (isset($_POST['edit_id']) && isset($_POST['edit_msg'])) {
    $edit_id = intval($_POST['edit_id']);
    $edit_msg = htmlspecialchars($_POST['edit_msg']);

    $stmt = $conn->prepare("UPDATE messages SET content = ? WHERE id = ?");
    $stmt->bind_param("si", $edit_msg, $edit_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(getMessages($conn));
    exit;
}

// Handle message deletion
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);

    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(getMessages($conn));
    exit;
}


function getMessages($conn) {
    $query = "
    SELECT 
        messages.id, 
        messages.content, 
        messages.user_id, 
        messages.type, 
        messages.created_at, 
        users.Fname, 
        users.profile_pic 
    FROM messages 
    JOIN users ON messages.user_id = users.id 
    ORDER BY messages.created_at ASC";

    
    $result = $conn->query($query);
    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    return $messages;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    

    <title>Pansol Group Chat</title>
    <style>
      
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
            border-radius: 10px;
            padding: 10px;
            flex-grow: 1;
            height: 80vh; /* 70% of the viewport height */
            overflow-y: auto; /* Allow scrolling if content exceeds height */
            margin-bottom: 15px;
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
    background-color: #007bff;
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
    top: 5px;
    right: 10px;
    cursor: pointer;
    color: gray; /* Ensure the icon color is visible */
    font-size: 1.2rem; /* Increase size if needed */
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
        .footer {
            padding: 10px; /* Reduced padding */
            text-align: center;
            font-size: 0.75em; /* Smaller font size */
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div id="container" class="container-fluid d-flex flex-column h-100">
        <div class="header">
            <h1>Pansol Group Chat</h1>
            <h2 class="mt-3 text-center">Welcome to the chat, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>

        </div>
        
        <div class="main flex-grow-1 d-flex flex-column" style="padding: 10px;">

            <div class="input-group search-bar">
                <input type="text" id="search" class="form-control" placeholder="Search messages..." aria-label="Search messages">
            </div>

            <div id="chatlogs" class="d-flex flex-column">
    <?php 
    $current_user = $_SESSION['username']; // Store current user's username
    foreach (getMessages($conn) as $message): 
        $message_type = ($message['Fname'] === $current_user) ? 'sent' : 'received';
    ?>
        <div class="message <?= htmlspecialchars($message_type) ?>" data-id="<?= htmlspecialchars($message['id']) ?>">
            <?php if ($message['Fname'] === $current_user): ?>
                <div class="message-menu">
                    <i class="fas fa-ellipsis-h" onclick="toggleMenu(this)"></i>
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
            <strong><?= htmlspecialchars($message['Fname']) ?></strong>
            <div class="message-content"><?= htmlspecialchars($message['content']) ?></div>
            <div class="small text-muted text-right"><?= date('h:i A', strtotime($message['created_at'])) ?></div>
        </div>
    <?php endforeach; ?>
</div>


            <form name="form1" id="messageForm" action="admin-chat.php" method="POST" onsubmit="return submitchat()" class="mt-3">
                <div class="input-group mt-2">
                    <textarea name="msg" class="form-control" placeholder="Your message here..." required></textarea>
                    <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">
    <i class="fas fa-paper-plane fa-2x"></i></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="footer">
            <p>&copy; 2024 Pansol Group Chat</p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>

function toggleMenu(element) {
    var menu = element.nextElementSibling; // Select the menu-options div
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';

    // Close the menu if clicking outside of it
    document.addEventListener('click', function(event) {
        if (!element.contains(event.target) && !menu.contains(event.target)) {
            menu.style.display = 'none';
        }
    }, { once: true });
}
function validateMessage() {
        var msg = document.forms["form1"]["msg"].value;
        if (msg === '') {
            alert('Enter a message!');
            return false;
        }
        return true; // Proceed with the form submission
    }
    function updateChatLogs(messages) {
    $('#chatlogs').empty(); // Clear current chat logs
    messages.forEach(function(message) {
        var messageClass = message.type === 'sent' ? 'sent' : 'received';
        var messageHtml = `
            <div class="message ${messageClass}" data-id="${message.id}">
                <strong>${message.username}:</strong>
                <div class="message-content">${message.content}</div>
                <div class="small text-muted text-right">${new Intl.DateTimeFormat('en-US', {
                    timeZone: 'Asia/Manila',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }).format(new Date(message.created_at))} PHT</div>
                <div class="message-buttons">
                    ${message.username === '<?= $_SESSION['username'] ?>' ? `<button onclick="editMessage(${message.id})" class="btn btn-sm btn-warning">Edit</button>
                    <button onclick="deleteMessage(${message.id})" class="btn btn-sm btn-danger">Delete</button>` : ''}
                </div>
            </div>`;
        $('#chatlogs').append(messageHtml);
    });
    
    scrollToBottom(); // Ensure it scrolls to bottom after updating
}

function scrollToBottom() {
    var chatlogs = document.getElementById("chatlogs");
    setTimeout(() => { // Add a slight delay to ensure all content is rendered
        chatlogs.scrollTop = chatlogs.scrollHeight;
    }, 50); // Adjust delay as needed
}

// Save scroll position before the page unloads
window.addEventListener("beforeunload", function() {
    var chatlogs = document.getElementById("chatlogs");
    localStorage.setItem("chatScrollPosition", chatlogs.scrollTop);
});

// Restore scroll position on page load
$(document).ready(function() {
    var chatlogs = document.getElementById("chatlogs");
    var scrollPosition = localStorage.getItem("chatScrollPosition");

    // Set scroll position from local storage if available
    if (scrollPosition) {
        chatlogs.scrollTop = scrollPosition;
    } else {
        scrollToBottom(); // Default to bottom if no position is stored
    }

    // Clear stored scroll position after using it
    localStorage.removeItem("chatScrollPosition");
});


function editMessage(messageId) {
    // Get the message content and set it for editing
    var messageElement = $(`.message[data-id='${messageId}']`);
    var messageContent = messageElement.find('.message-content').text();
    var newContent = prompt("Edit your message:", messageContent);
    
    if (newContent !== null) {
        $.ajax({
            type: "POST",
            url: "admin-chat.php",
            data: { edit_id: messageId, edit_msg: newContent },
            success: function() {
                location.reload(); // Reloads the page after editing
            }
        });
    }
}

    function deleteMessage(messageId) {
        if (confirm("Are you sure you want to delete this message?")) {
            $.ajax({
                type: "POST",
                url: "",
                data: { delete_id: messageId },
                success: function(response) {
                    updateChatLogs(JSON.parse(response));
                    scrollToBottom(); // Ensure we scroll down after deletion too
                }
            });
        }
    }

    function scrollToBottom() {
        var chatlogs = document.getElementById("chatlogs");
        chatlogs.scrollTop = chatlogs.scrollHeight;
    }

    // Optional: Add search functionality
    $('#search').on('keyup', function() {
        var searchValue = $(this).val().toLowerCase();
        $('#chatlogs .message').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(searchValue) > -1)
        });
    });
    </script>
</body>
</html>
