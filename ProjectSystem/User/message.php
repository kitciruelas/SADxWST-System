<?php
session_start();
include '../config/config.php';


?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/userdashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link active"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>

        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Message</h2>

    </div>
    <div class="main-content">      
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-4">
                <h4>Groups</h4>
                <ul class="list-group" id="groupList">
                    <!-- Group names populated here via PHP or AJAX -->
                </ul>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Chat Room</div>
                    <div class="card-body" id="chatBox" style="height: 400px; overflow-y: scroll;">
                        <!-- Messages loaded here -->
                    </div>
                    <div class="card-footer">
                        <form id="messageForm">
                            <input type="text" id="messageInput" class="form-control" placeholder="Type a message">
                            <button type="submit" class="btn btn-primary mt-2">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>






<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
    // JavaScript for handling message sending
    $('#messageForm').on('submit', function(e) {
            e.preventDefault();
            let message = $('#messageInput').val();
            $.post('send_message.php', { message: message, group_id: selectedGroupId }, function() {
                $('#messageInput').val('');
                loadMessages(selectedGroupId);
            });
        });

        function loadMessages(groupId) {
            $.get('fetch_messages.php', { group_id: groupId }, function(data) {
                $('#chatBox').html(data);
            });
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
