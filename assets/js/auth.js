function showTab(tabName) {
    document.querySelectorAll('.auth-form').forEach(form => {
        form.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById(tabName + '-form').classList.add('active');
    if (event && event.target) {
        event.target.classList.add('active');
    } else {
        const btn = document.querySelector(`.tab-btn[onclick="showTab('${tabName}')"]`);
        if (btn) btn.classList.add('active');
    }
}

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'login');
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.innerHTML = '<span class="loading"></span> Logging in...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Login successful!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = result.redirect || 'dashboard.php';
            });
        } else {
            if (result.requires_verification) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Email Verification Required',
                    html: `
                        <p>${result.message}</p>
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f39c12;">
                            <h4 style="margin: 0 0 10px 0; color: #856404;">📧 Check Your Email</h4>
                            <p style="margin: 0; color: #856404;">We've sent you a verification link. Click the link in your email to activate your account.</p>
                        </div>
                        <p><strong>Didn't receive the email?</strong><br>
                        Check your spam folder or <a href="#" onclick="resendVerificationEmail('${result.email}')">click here to resend</a></p>
                    `,
                    confirmButtonText: 'Got it!',
                    allowOutsideClick: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    text: result.message || 'Invalid credentials'
                });
            }
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred during login'
        });
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'register');
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Password Mismatch',
            text: 'Passwords do not match'
        });
        return;
    }
    submitBtn.innerHTML = '<span class="loading"></span> Registering...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (result.requires_verification) {
                Swal.fire({
                    icon: 'success',
                    title: 'Registration Successful!',
                    html: `
                        <p>${result.message}</p>
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #27ae60;">
                            <h4 style="margin: 0 0 10px 0; color: #27ae60;">📧 Check Your Email</h4>
                            <p style="margin: 0; color: #27ae60;">We've sent you a verification link. Click the link in your email to activate your account.</p>
                        </div>
                        <p><strong>Didn't receive the email?</strong><br>
                        Check your spam folder or <a href="#" onclick="resendVerificationEmail()">click here to resend</a></p>
                    `,
                    confirmButtonText: 'Got it!',
                    allowOutsideClick: false
                }).then(() => {
                    showTab('login');
                    document.getElementById('registerForm').reset();
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Registration Successful!',
                    text: 'Your account has been created. You can now login.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    showTab('login');
                    document.getElementById('registerForm').reset();
                });
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: result.message || 'An error occurred during registration'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred during registration'
        });
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

const forgotLink = document.getElementById('forgotPasswordLink');
const forgotModal = document.getElementById('forgotModal');
const closeForgot = document.getElementById('closeForgot');
if (forgotLink && forgotModal && closeForgot) {
    forgotLink.addEventListener('click', function(ev){ 
        ev.preventDefault(); 
        resetForgotPasswordModal();
        forgotModal.style.display = 'flex'; 
    });
    closeForgot.addEventListener('click', function(){ 
        forgotModal.style.display = 'none'; 
        resetForgotPasswordModal();
    });
    window.addEventListener('click', function(e){ 
        if (e.target === forgotModal) {
            forgotModal.style.display = 'none'; 
            resetForgotPasswordModal();
        }
    });
}

function resetForgotPasswordModal() {
    document.getElementById('resetStepEmail').style.display = 'block';
    document.getElementById('resetStepVerify').style.display = 'none';
    document.getElementById('resetStepNewPass').style.display = 'none';
    document.getElementById('step1').classList.add('active');
    document.getElementById('step1').classList.remove('completed');
    document.getElementById('step2').classList.remove('active', 'completed');
    document.getElementById('step3').classList.remove('active', 'completed');
    document.getElementById('fp_email').value = '';
    document.getElementById('fp_code').value = '';
    document.getElementById('fp_new_password').value = '';
    document.getElementById('fp_confirm_password').value = '';
}

function updateStepIndicator(step) {
    document.getElementById('step1').classList.remove('active', 'completed');
    document.getElementById('step2').classList.remove('active', 'completed');
    document.getElementById('step3').classList.remove('active', 'completed');
    
    if (step === 1) {
        document.getElementById('step1').classList.add('active');
    } else if (step === 2) {
        document.getElementById('step1').classList.add('completed');
        document.getElementById('step2').classList.add('active');
    } else if (step === 3) {
        document.getElementById('step1').classList.add('completed');
        document.getElementById('step2').classList.add('completed');
        document.getElementById('step3').classList.add('active');
    }
}

const btnSendCode = document.getElementById('btnSendCode');
const btnVerifyCode = document.getElementById('btnVerifyCode');
const btnResetPassword = document.getElementById('btnResetPassword');

async function postReset(data) {
    const resp = await fetch('api/password_reset.php', { method: 'POST', body: data });
    return resp.json();
}

if (btnSendCode) {
    btnSendCode.addEventListener('click', async function(){
        const email = document.getElementById('fp_email').value.trim();
        if (!email) { 
            Swal.fire({
                icon:'warning', 
                title:'Email Required',
                text:'Please enter your email address'
            }); 
            return; 
        }
        
        const originalText = btnSendCode.innerHTML;
        btnSendCode.innerHTML = '<span class="loading"></span> Sending...';
        btnSendCode.disabled = true;
        
        const fd = new FormData();
        fd.append('action','send_code');
        fd.append('email', email);
        
        try {
            const res = await postReset(fd);
            if (res.success) {
                Swal.fire({
                    icon:'success', 
                    title:'Code Sent!', 
                    text:'Check your email for the verification code',
                    timer: 3000,
                    showConfirmButton: false
                });
                document.getElementById('resetStepEmail').style.display='none';
                document.getElementById('resetStepVerify').style.display='block';
                updateStepIndicator(2);
            } else {
                Swal.fire({
                    icon:'error', 
                    title:'Failed to Send', 
                    text: res.message || 'Failed to send verification code. Please try again.'
                });
            }
        } catch (error) {
            Swal.fire({
                icon:'error', 
                title:'Error', 
                text:'An error occurred. Please try again.'
            });
        } finally {
            btnSendCode.innerHTML = originalText;
            btnSendCode.disabled = false;
        }
    });
}

if (btnVerifyCode) {
    btnVerifyCode.addEventListener('click', async function(){
        const email = document.getElementById('fp_email').value.trim();
        const code = document.getElementById('fp_code').value.trim();
        
        if (!code || code.length !== 6) {
            Swal.fire({
                icon:'warning', 
                title:'Invalid Code',
                text:'Please enter the 6-digit verification code'
            });
            return;
        }
        
        const originalText = btnVerifyCode.innerHTML;
        btnVerifyCode.innerHTML = '<span class="loading"></span> Verifying...';
        btnVerifyCode.disabled = true;
        
        const fd = new FormData();
        fd.append('action','verify_code');
        fd.append('email', email);
        fd.append('code', code);
        
        try {
            const res = await postReset(fd);
            if (res.success) {
                Swal.fire({
                    icon:'success', 
                    title:'Code Verified!',
                    text:'You can now set your new password',
                    timer: 2000,
                    showConfirmButton: false
                });
                document.getElementById('resetStepVerify').style.display='none';
                document.getElementById('resetStepNewPass').style.display='block';
                updateStepIndicator(3);
            } else {
                Swal.fire({
                    icon:'error', 
                    title:'Invalid Code', 
                    text: res.message || 'The verification code is incorrect or expired. Please try again.'
                });
            }
        } catch (error) {
            Swal.fire({
                icon:'error', 
                title:'Error', 
                text:'An error occurred. Please try again.'
            });
        } finally {
            btnVerifyCode.innerHTML = originalText;
            btnVerifyCode.disabled = false;
        }
    });
}

if (btnResetPassword) {
    btnResetPassword.addEventListener('click', async function(){
        const email = document.getElementById('fp_email').value.trim();
        const pass = document.getElementById('fp_new_password').value;
        const conf = document.getElementById('fp_confirm_password').value;
        
        if (!pass || pass.length < 6) {
            Swal.fire({
                icon:'warning', 
                title:'Password Too Short',
                text:'Password must be at least 6 characters long'
            });
            return;
        }
        
        if (pass !== conf) {
            Swal.fire({
                icon:'warning', 
                title:'Passwords Don\'t Match',
                text:'Please make sure both password fields match'
            });
            return;
        }
        
        const originalText = btnResetPassword.innerHTML;
        btnResetPassword.innerHTML = '<span class="loading"></span> Resetting...';
        btnResetPassword.disabled = true;
        
        const fd = new FormData();
        fd.append('action','reset_password');
        fd.append('email', email);
        fd.append('new_password', pass);
        fd.append('confirm_password', conf);
        
        try {
            const res = await postReset(fd);
            if (res.success) {
                Swal.fire({
                    icon:'success', 
                    title:'Password Reset Successfully!',
                    text:'Your password has been updated. You can now login with your new password.',
                    confirmButtonText: 'Go to Login'
                }).then(()=>{ 
                    forgotModal.style.display='none'; 
                    resetForgotPasswordModal();
                    showTab('login'); 
                });
            } else {
                Swal.fire({
                    icon:'error', 
                    title:'Reset Failed', 
                    text: res.message || 'Failed to reset password. Please try again.'
                });
            }
        } catch (error) {
            Swal.fire({
                icon:'error', 
                title:'Error', 
                text:'An error occurred. Please try again.'
            });
        } finally {
            btnResetPassword.innerHTML = originalText;
            btnResetPassword.disabled = false;
        }
    });
}

const btnResendCode = document.getElementById('btnResendCode');
if (btnResendCode) {
    btnResendCode.addEventListener('click', async function(){
        const email = document.getElementById('fp_email').value.trim();
        if (!email) return;
        
        const originalText = btnResendCode.innerHTML;
        btnResendCode.innerHTML = '<span class="loading"></span> Resending...';
        btnResendCode.disabled = true;
        
        const fd = new FormData();
        fd.append('action','send_code');
        fd.append('email', email);
        
        try {
            const res = await postReset(fd);
            if (res.success) {
                Swal.fire({
                    icon:'success', 
                    title:'Code Resent!', 
                    text:'A new verification code has been sent to your email',
                    timer: 3000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon:'error', 
                    title:'Failed to Resend', 
                    text: res.message || 'Failed to resend verification code'
                });
            }
        } catch (error) {
            Swal.fire({
                icon:'error', 
                title:'Error', 
                text:'An error occurred. Please try again.'
            });
        } finally {
            btnResendCode.innerHTML = originalText;
            btnResendCode.disabled = false;
        }
    });
}

function validateForm(form) {
    const inputs = form.querySelectorAll('input[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#e74c3c';
            isValid = false;
        } else {
            input.style.borderColor = '#e0e6ed';
        }
    });
    
    return isValid;
}

document.querySelectorAll('input[required]').forEach(input => {
    input.addEventListener('blur', function() {
        if (!this.value.trim()) {
            this.style.borderColor = '#e74c3c';
        } else {
            this.style.borderColor = '#e0e6ed';
        }
    });
    
    input.addEventListener('input', function() {
        if (this.value.trim()) {
            this.style.borderColor = '#e0e6ed';
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('fp_code');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
            if (this.value.length === 6) {
                setTimeout(() => {
                    const verifyBtn = document.getElementById('btnVerifyCode');
                    if (verifyBtn && !verifyBtn.disabled) {
                        verifyBtn.click();
                    }
                }, 500);
            }
        });
        codeInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const numbers = paste.replace(/[^0-9]/g, '').slice(0, 6);
            this.value = numbers;
            if (numbers.length === 6) {
                setTimeout(() => {
                    const verifyBtn = document.getElementById('btnVerifyCode');
                    if (verifyBtn && !verifyBtn.disabled) {
                        verifyBtn.click();
                    }
                }, 500);
            }
        });
    }
    const newPasswordInput = document.getElementById('fp_new_password');
    const confirmPasswordInput = document.getElementById('fp_confirm_password');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = getPasswordStrength(password);
            updatePasswordStrengthIndicator(strength);
        });
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = document.getElementById('fp_new_password').value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.style.borderColor = '#e74c3c';
            } else if (confirm && password === confirm) {
                this.style.borderColor = '#48bb78';
            } else {
                this.style.borderColor = '#e2e8f0';
            }
        });
    }
});

function getPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    return strength;
}

async function resendVerificationEmail(email = null) {
    if (!email) {
        const emailInput = document.getElementById('reg_email');
        email = emailInput ? emailInput.value : null;
    }
    
    if (!email) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please enter your email address first'
        });
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'resend_verification');
        formData.append('email', email);
        
        const response = await fetch('api/email_verification.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Verification Email Sent!',
                text: result.message,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed to Send Email',
                text: result.message || 'An error occurred while sending the verification email'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while sending the verification email'
        });
    }
}

function updatePasswordStrengthIndicator(strength) {
    const input = document.getElementById('fp_new_password');
    if (!input) return;
    
    const colors = ['#e74c3c', '#f39c12', '#f1c40f', '#2ecc71', '#27ae60', '#16a085'];
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    
    if (strength > 0) {
        input.style.borderColor = colors[strength - 1];
    } else {
        input.style.borderColor = '#e2e8f0';
    }
}
