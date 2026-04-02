// Verify Page JavaScript
// verify.js

document.addEventListener('DOMContentLoaded', function () {
  const emailDisplay = document.getElementById('emailDisplay');
  const verifyForm = document.getElementById('verifyForm');
  const codeInput = document.getElementById('verificationCode');
  const verifyBtn = document.getElementById('verifyBtn');
  const resendLink = document.getElementById('resendLink');
  const alertMessage = document.getElementById('alertMessage');
  const codeError = document.getElementById('codeError');

  // Get email from URL: verify.html?email=...
  const params = new URLSearchParams(window.location.search);
  const email = (params.get('email') || '').trim().toLowerCase();

  // Show email
  emailDisplay.textContent = email || 'your email';

  // If no email in URL, disable verification
  if (!email) {
    showAlert('error', 'Missing email. Please sign up again.');
    verifyBtn.disabled = true;
  }

  // Helper: show alert
  function showAlert(type, message) {
    alertMessage.className = `alert alert-${type} show`;
    alertMessage.textContent = message;
  }

  // Helper: show code error
  function showCodeError(message) {
    if (!codeError) return;
    codeError.textContent = message;
    codeError.classList.add('show');
    codeInput.classList.add('error');
  }

  // Helper: clear errors
  function clearErrors() {
    if (codeError) {
      codeError.textContent = '';
      codeError.classList.remove('show');
    }
    codeInput.classList.remove('error');

    if (alertMessage) {
      alertMessage.className = 'alert';
      alertMessage.textContent = '';
    }
  }

  // Auto-format: allow only numbers, max 6
  codeInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length > 0) {
      clearErrors();
    }
  });

  // Submit verification
  verifyForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    clearErrors();

    if (!email) {
      showAlert('error', 'Missing email. Please sign up again.');
      return;
    }

    const code = codeInput.value.trim();

    // Validate code: 6 digits
    if (!/^\d{6}$/.test(code)) {
      showCodeError('Please enter a valid 6-digit code.');
      return;
    }

    verifyBtn.disabled = true;
    verifyBtn.textContent = 'Verifying...';

    const formData = new FormData();
    formData.append('action', 'verify');
    formData.append('email', email);
    formData.append('code', code);

    try {
      const response = await fetch('auth.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showAlert('success', data.message || 'Email verified! Redirecting to login...');
        setTimeout(() => {
          window.location.href = 'login.html';
        }, 1200);
      } else {
        showAlert('error', data.message || 'Verification failed.');
        verifyBtn.disabled = false;
        verifyBtn.textContent = 'Verify Email';
      }
    } catch (err) {
      showAlert('error', 'An error occurred. Please try again.');
      verifyBtn.disabled = false;
      verifyBtn.textContent = 'Verify Email';
    }
  });

  // Resend code
  resendLink.addEventListener('click', async function (e) {
    e.preventDefault();
    clearErrors();

    if (!email) {
      showAlert('error', 'Missing email. Please sign up again.');
      return;
    }

    resendLink.textContent = 'Sending...';
    resendLink.style.pointerEvents = 'none';

    const formData = new FormData();
    formData.append('action', 'resend');
    formData.append('email', email);

    try {
      const response = await fetch('auth.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showAlert('success', data.message || 'New code sent! Please check your email.');
      } else {
        showAlert('error', data.message || 'Failed to resend code.');
      }
    } catch (err) {
      showAlert('error', 'An error occurred. Please try again.');
    } finally {
      // Re-enable resend after 20 seconds
      let seconds = 20;
      const originalText = 'Resend Code';

      resendLink.textContent = `Resend in ${seconds}s`;
      const timer = setInterval(() => {
        seconds--;
        resendLink.textContent = seconds > 0 ? `Resend in ${seconds}s` : originalText;

        if (seconds <= 0) {
          clearInterval(timer);
          resendLink.style.pointerEvents = 'auto';
          resendLink.textContent = originalText;
        }
      }, 1000);
    }
  });
});
