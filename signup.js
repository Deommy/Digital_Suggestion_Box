// Signup Page JavaScript
// signup.js

document.addEventListener('DOMContentLoaded', function () {
  const signupForm = document.getElementById('signupForm');
  const fullNameInput = document.getElementById('fullName');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const signupBtn = document.getElementById('signupBtn');
  const alertMessage = document.getElementById('alertMessage');

  // ✅ One regex only (consistent everywhere)
  const schoolEmailRegex = /^[^\s@]+@panpacificu\.edu\.ph$/i;

  // Form submission
  signupForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    clearErrors();

    if (!validateForm()) return;

    signupBtn.disabled = true;
    signupBtn.textContent = 'Creating Account...';

    const formData = new FormData();
    formData.append('action', 'signup');
    formData.append('fullName', fullNameInput.value.trim());
    formData.append('email', emailInput.value.trim().toLowerCase());
    formData.append('password', passwordInput.value);

    try {
      const response = await fetch('auth.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showAlert('success', data.message);

        setTimeout(() => {
          window.location.href = `verify.html?email=${encodeURIComponent(
            emailInput.value.trim()
          )}`;
        }, 1500);
      } else {
        showAlert('error', data.message);
        signupBtn.disabled = false;
        signupBtn.textContent = 'Create Account';
      }
    } catch (error) {
      showAlert('error', 'An error occurred. Please try again.');
      signupBtn.disabled = false;
      signupBtn.textContent = 'Create Account';
    }
  });

  // Form validation
  function validateForm() {
    let isValid = true;

    // Full name validation
    const fullName = fullNameInput.value.trim();
    if (fullName.length < 3) {
      showError('nameError', 'Please enter your full name');
      fullNameInput.classList.add('error');
      isValid = false;
    }

    // Email validation
    const email = emailInput.value.trim().toLowerCase();
    if (!schoolEmailRegex.test(email)) {
      showError('emailError', 'Please use a valid institutional email (@panpacificu.edu.ph)');
      emailInput.classList.add('error');
      isValid = false;
    }

    // Password validation
    const password = passwordInput.value;
    if (password.length < 8) {
      showError('passwordError', 'Password must be at least 8 characters');
      passwordInput.classList.add('error');
      isValid = false;
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(password)) {
      showError('passwordError', 'Password must contain uppercase, lowercase, and numbers');
      passwordInput.classList.add('error');
      isValid = false;
    }

    // Confirm password validation
    const confirmPassword = confirmPasswordInput.value;
    if (password !== confirmPassword) {
      showError('confirmPasswordError', 'Passwords do not match');
      confirmPasswordInput.classList.add('error');
      isValid = false;
    }

    return isValid;
  }

  function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (!errorElement) return;
    errorElement.textContent = message;
    errorElement.classList.add('show');
  }

  function clearErrors() {
    document.querySelectorAll('.form-error').forEach(el => {
      el.textContent = '';
      el.classList.remove('show');
    });

    document.querySelectorAll('.form-input').forEach(input => {
      input.classList.remove('error');
    });

    // clear alert too (optional)
    if (alertMessage) {
      alertMessage.className = 'alert';
      alertMessage.textContent = '';
    }
  }

  function showAlert(type, message) {
    if (!alertMessage) return;
    alertMessage.className = `alert alert-${type} show`;
    alertMessage.textContent = message;
  }

  // Real-time validation
  emailInput.addEventListener('blur', function () {
    const email = this.value.trim().toLowerCase();

    if (email && !schoolEmailRegex.test(email)) {
      showError('emailError', 'Please use a valid institutional email (@panpacificu.edu.ph)');
      this.classList.add('error');
    } else {
      const emailErr = document.getElementById('emailError');
      if (emailErr) {
        emailErr.textContent = '';
        emailErr.classList.remove('show');
      }
      this.classList.remove('error');
    }
  });

  passwordInput.addEventListener('input', function () {
    if (this.value.length > 0) {
      const err = document.getElementById('passwordError');
      if (err) err.classList.remove('show');
      this.classList.remove('error');
    }
  });

  confirmPasswordInput.addEventListener('input', function () {
    if (this.value === passwordInput.value) {
      const err = document.getElementById('confirmPasswordError');
      if (err) err.classList.remove('show');
      this.classList.remove('error');
    }
  });
});
