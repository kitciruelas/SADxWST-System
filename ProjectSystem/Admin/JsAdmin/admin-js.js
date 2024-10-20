
// Show modal when 'openEditUserModal' is clicked
document.getElementById('openEditUserModal').onclick = function() {
    document.getElementById('editUserModal').style.display = 'flex';
};

// Hide modal when 'closeEditModal' is clicked
document.getElementById('closeEditModal').onclick = function() {
    document.getElementById('editUserModal').style.display = 'none';
};

// Close the modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('editUserModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};

// Form validation function
function validateForm() {
    const fname = document.getElementById('editFname').value.trim();
    const lname = document.getElementById('editLname').value.trim();
    const age = document.getElementById('editAge').value.trim();
    const contact = document.getElementById('editContact').value.trim();

    // Validate form fields
    if (!fname || !lname || !age || !contact) {
        alert('Please fill out all required fields.');
        return false;
    }
    
    return true;
}

// Toggle sidebar visibility when 'hamburgerMenu' is clicked
const hamburgerMenu = document.getElementById('hamburgerMenu');
const sidebar = document.getElementById('sidebar');

// Initially set sidebar to collapsed
sidebar.classList.add('collapsed');

hamburgerMenu.addEventListener('click', function() {
    sidebar.classList.toggle('collapsed');
    
    // Change the icon based on the sidebar state
    const icon = hamburgerMenu.querySelector('i');
    if (sidebar.classList.contains('collapsed')) {
        icon.classList.remove('fa-times'); 
        icon.classList.add('fa-bars');
    } else {
        icon.classList.remove('fa-bars'); 
        icon.classList.add('fa-times');
    }
});

// Add 'active' class to clicked sidebar link and remove from others
const sidebarLinks = document.querySelectorAll('.sidebar a');
sidebarLinks.forEach(link => {
    link.addEventListener('click', function() {
        // Remove the active class from all links
        sidebarLinks.forEach(link => link.classList.remove('active'));
        
        // Add the active class to the clicked link
        this.classList.add('active');
    });
});
