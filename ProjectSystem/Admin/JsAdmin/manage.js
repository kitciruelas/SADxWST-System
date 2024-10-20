// Search function to filter table rows based on user input
function searchTable() {
    const input = document.getElementById("searchInput");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("userTable");
    const tr = table.getElementsByTagName("tr");

    // Iterate through each table row (excluding header)
    for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName("td");
        let found = Array.from(td).some(cell => cell.innerHTML.toLowerCase().includes(filter));
        tr[i].style.display = found ? "" : "none";
    }
}

// Show or hide modals for user management
function showModal() {
    document.getElementById('userModal').style.display = 'flex';
}

function hideModal() {
    document.getElementById('userModal').style.display = 'none';
}

function openEditModal(id, Fname, Lname, MI, Age, Address, contact, Sex, Role) {
    const fields = {
        editUserId: id,
        editFname: Fname,
        editLname: Lname,
        editMI: MI,
        editAge: Age,
        editAddress: Address,
        editContact: contact,
        editSex: Sex,
        editRole: Role
    };

    // Populate modal fields
    for (const [key, value] of Object.entries(fields)) {
        document.getElementById(key).value = value;
    }
    
    document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

// Close modals when clicking outside of them
window.onclick = function(event) {
    const addUserModal = document.getElementById('userModal');
    const editUserModal = document.getElementById('editUserModal');
    
    if (event.target === addUserModal) {
        hideModal();
    }
    if (event.target === editUserModal) {
        closeEditModal();
    }
};

// Hamburger menu toggle for sidebar
const hamburgerMenu = document.getElementById('hamburgerMenu');
const sidebar = document.getElementById('sidebar');
sidebar.classList.add('collapsed');

hamburgerMenu.addEventListener('click', function() {
    sidebar.classList.toggle('collapsed');
    const icon = hamburgerMenu.querySelector('i');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-times');
});

// Form validation function for user input fields
function validateForm() {
    const email = document.getElementById('email') ? document.getElementById('email').value : '';
    const password = document.getElementById('password') ? document.getElementById('password').value : '';
    const contact = document.getElementById('contact') ? document.getElementById('contact').value : '';

    // Validate email format
    if (email && !email.includes("@")) {
        alert("Please enter a valid email.");
        return false;
    }

    // Validate password length
    if (password && password.length < 6) {
        alert("Password must be at least 6 characters.");
        return false;
    }

    // Validate contact number is exactly 11 digits
    const contactRegex = /^\d{11}$/;
    if (contact && !contactRegex.test(contact)) {
        alert("Contact number must be exactly 11 digits.");
        return false;
    }

    return true;
}
