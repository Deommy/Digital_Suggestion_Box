// Login Page JavaScript
// login.js

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.getElementById('loginBtn');
    const alertMessage = document.getElementById('alertMessage');

    // Form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Clear previous errors
        clearErrors();

        const email = emailInput.value.trim().toLowerCase();
        const password = passwordInput.value;

        // Basic validation
        if (!email || !password) {
            showAlert('error', 'Please fill in all fields');
            return;
        }

        // Disable button
        loginBtn.disabled = true;
        loginBtn.textContent = 'Signing In...';

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('email', email);
        formData.append('password', password);

        try {
            const response = await fetch('auth.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showAlert('success', data.message);
                
                // Redirect based on role
                setTimeout(() => {
                    if (data.role === 'admin') {
                        window.location.href = 'admin-dashboard.php';
                    } else {
                        window.location.href = 'student-dashboard.html';
                    }
                }, 1000);
            } else {
                showAlert('error', data.message);
                loginBtn.disabled = false;
                loginBtn.textContent = 'Sign In';
                
                // Clear password field
                passwordInput.value = '';
            }
        } catch (error) {
            showAlert('error', 'An error occurred. Please try again.');
            loginBtn.disabled = false;
            loginBtn.textContent = 'Sign In';
        }
    });

    // Clear errors
    function clearErrors() {
        const errorElements = document.querySelectorAll('.form-error');
        errorElements.forEach(element => {
            element.textContent = '';
            element.classList.remove('show');
        });

        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.classList.remove('error');
        });
    }

    // Show alert message
    function showAlert(type, message) {
        alertMessage.className = `alert alert-${type} show`;
        alertMessage.textContent = message;
    }

    // Check if already logged in
    fetch('check-session.php')
        .then(response => response.json())
        .then(data => {
            if (data.loggedIn) {
                if (data.role === 'admin') {
                    window.location.href = 'admin-dashboard.php';
                } else {
                    window.location.href = 'student-dashboard.html';
                }
            }
        })
        .catch(error => {
            console.error('Session check failed:', error);
        });
});