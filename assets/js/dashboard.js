// Dashboard JavaScript for PortionPro

// Logout function
function logout() {
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Yes, logout!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Add any dashboard-specific initialization here
    console.log('PortionPro Dashboard loaded');
});
