<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

$login_url = $client->createAuthUrl();

if (isLoggedIn()) {
	header('Location: dashboard.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login - PortionPro</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link href="assets/css/style.css" rel="stylesheet">
	<style>
		body {
			background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #16a085 100%);
			font-family: 'Inter', sans-serif;
			position: relative;
			overflow-x: hidden;
		}

		body::before {
			content: '';
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
			pointer-events: none;
			z-index: 0;
		}

		.auth-container {
			position: relative;
			z-index: 1;
		}

		.auth-card {
			background: #f7f9f9;
			backdrop-filter: blur(20px);
			border: 1px solid rgba(255, 255, 255, 0.3);
			border-radius: 24px;
			box-shadow: 
				0 25px 50px rgba(44, 62, 80, 0.15),
				0 0 0 1px rgba(255, 255, 255, 0.2);
			padding: 48px;
			width: 100%;
			max-width: 480px;
			animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
		}

		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(40px) scale(0.95);
			}
			to {
				opacity: 1;
				transform: translateY(0) scale(1);
			}
		}

		.auth-header {
			text-align: center;
			margin-bottom: 40px;
		}

		.logo {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 16px;
			margin-bottom: 20px;
		}

		.logo i {
			font-size: 3rem;
			background: linear-gradient(135deg, #16a085 0%, #f39c12 100%);
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			background-clip: text;
			filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
		}

		.logo h1 {
			font-size: 2.5rem;
			font-weight: 700;
			color: #2c3e50;
			margin: 0;
			letter-spacing: -0.02em;
		}

		.auth-header p {
			color: #34495e;
			font-size: 1.1rem;
			font-weight: 500;
			margin: 0;
		}

		.auth-tabs {
			display: flex;
			background: rgba(52, 73, 94, 0.1);
			border-radius: 12px;
			padding: 6px;
			margin-bottom: 32px;
		}

		.tab-btn {
			flex: 1;
			padding: 12px 24px;
			border: none;
			background: transparent;
			color: #34495e;
			font-weight: 500;
			font-size: 1rem;
			border-radius: 8px;
			cursor: pointer;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		}

		.tab-btn.active {
			background: #16a085;
			color: white;
			box-shadow: 0 2px 8px rgba(22, 160, 133, 0.3);
		}

		.form-group {
			margin-bottom: 24px;
		}

		.form-group label {
			display: block;
			font-weight: 600;
			color: #2c3e50;
			margin-bottom: 8px;
			font-size: 0.95rem;
		}

		.form-group input {
			width: 100%;
			padding: 16px 20px;
			border: 2px solid rgba(52, 73, 94, 0.2);
			border-radius: 12px;
			font-size: 1rem;
			background: white;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			font-family: 'Inter', sans-serif;
		}

		.form-group input:focus {
			outline: none;
			border-color: #16a085;
			box-shadow: 0 0 0 3px rgba(22, 160, 133, 0.2);
			transform: translateY(-1px);
		}

		.form-group input::placeholder {
			color: #a0aec0;
		}

		.password-wrapper {
			position: relative;
		}

		.password-wrapper input {
			padding-right: 50px;
		}

		.password-toggle {
			position: absolute;
			right: 15px;
			top: 50%;
			transform: translateY(-50%);
			background: none;
			border: none;
			color: #7f8c8d;
			cursor: pointer;
			padding: 5px;
			font-size: 1.1rem;
			transition: color 0.3s ease;
			z-index: 10;
		}

		.password-toggle:hover {
			color: #16a085;
		}

		.password-toggle:focus {
			outline: none;
		}

		.btn {
			width: 100%;
			padding: 16px 24px;
			border: none;
			border-radius: 12px;
			font-size: 1rem;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			font-family: 'Inter', sans-serif;
			position: relative;
			overflow: hidden;
		}

		.btn-primary {
			background: linear-gradient(135deg, #16a085 0%, #f39c12 100%);
			color: white;
			box-shadow: 0 4px 15px rgba(22, 160, 133, 0.4);
			font-weight: 600;
		}

		.btn-primary:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 25px rgba(22, 160, 133, 0.6);
			background: linear-gradient(135deg, #138d75 0%, #e67e22 100%);
		}

		.btn-primary:active {
			transform: translateY(0);
		}

		.btn-secondary {
			background: rgba(52, 73, 94, 0.1);
			color: #34495e;
			border: 2px solid rgba(52, 73, 94, 0.3);
			font-weight: 600;
		}

		.btn-secondary:hover {
			background: rgba(52, 73, 94, 0.2);
			border-color: #34495e;
			transform: translateY(-1px);
			color: #2c3e50;
		}

		.google-login-btn {
			display: flex;
			align-items: center;
			justify-content: center;
			background: white;
			color: #34495e;
			border: 2px solid rgba(52, 73, 94, 0.3);
			border-radius: 12px;
			padding: 16px 24px;
			font-size: 1rem;
			font-weight: 600;
			text-decoration: none;
			margin: 20px 0;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			font-family: 'Inter', sans-serif;
		}

		.google-login-btn:hover {
			border-color: #16a085;
			background: rgba(22, 160, 133, 0.05);
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(22, 160, 133, 0.2);
			color: #16a085;
		}

		.google-login-btn .fab {
			margin-right: 12px;
			font-size: 1.2rem;
		}

		#forgotPasswordLink {
			color: #f39c12;
			text-decoration: none;
			font-weight: 600;
			font-size: 0.95rem;
			transition: all 0.3s ease;
		}

		#forgotPasswordLink:hover {
			color: #16a085;
			text-decoration: underline;
		}

		.modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.6);
			backdrop-filter: blur(8px);
			z-index: 1000;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
			animation: fadeIn 0.3s ease-out;
		}

		@keyframes fadeIn {
			from { opacity: 0; }
			to { opacity: 1; }
		}

		.modal-content {
			background: white;
			border-radius: 20px;
			box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
			animation: slideUpModal 0.4s cubic-bezier(0.16, 1, 0.3, 1);
			overflow: hidden;
		}

		@keyframes slideUpModal {
			from {
				opacity: 0;
				transform: translateY(30px) scale(0.95);
			}
			to {
				opacity: 1;
				transform: translateY(0) scale(1);
			}
		}

		.modal-header {
			background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #16a085 100%);
			color: white;
			padding: 24px 32px;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		.modal-title {
			font-size: 1.5rem;
			font-weight: 600;
			margin: 0;
		}

		.close {
			font-size: 1.8rem;
			cursor: pointer;
			opacity: 0.8;
			transition: opacity 0.3s ease;
			line-height: 1;
		}

		.close:hover {
			opacity: 1;
		}

		.modal-body {
			padding: 32px;
		}

		/* Step indicators */
		.reset-steps {
			display: flex;
			justify-content: center;
			margin-bottom: 32px;
		}

		.step {
			display: flex;
			align-items: center;
			margin: 0 8px;
		}

		.step-number {
			width: 32px;
			height: 32px;
			border-radius: 50%;
			background: #e2e8f0;
			color: #718096;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 600;
			font-size: 0.9rem;
			transition: all 0.3s ease;
		}

		.step.active .step-number {
			background: #16a085;
			color: white;
		}

		.step.completed .step-number {
			background: #f39c12;
			color: white;
		}

		.step-line {
			width: 40px;
			height: 2px;
			background: #e2e8f0;
			margin: 0 8px;
		}

		.step.completed + .step .step-line {
			background: #f39c12;
		}

		/* Loading animation */
		.loading {
			display: inline-block;
			width: 16px;
			height: 16px;
			border: 2px solid rgba(255, 255, 255, 0.3);
			border-radius: 50%;
			border-top-color: white;
			animation: spin 1s ease-in-out infinite;
			margin-right: 8px;
		}

		@keyframes spin {
			to { transform: rotate(360deg); }
		}

		/* Responsive */
		@media (max-width: 640px) {
			.auth-card {
				padding: 32px 24px;
				margin: 20px;
			}
			
			.modal-content {
				margin: 20px;
				max-width: calc(100% - 40px);
			}
			
			.modal-body {
				padding: 24px;
			}
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="auth-container">
			<div class="auth-card">
				<div class="auth-header">
					<div class="logo">
						<img src="logo/PortionPro-fill.png" alt="PortionPro Logo" style="height: 60px; width: auto;">
						<h1>PortionPro</h1>
					</div>
					<p>Food Costing Calculator for Small Food Businesses</p>
				</div>
				
				<div class="auth-tabs">
					<button class="tab-btn active" onclick="showTab('login')">Login</button>
					<button class="tab-btn" onclick="showTab('register')">Register</button>
				</div>
				
				<!-- Login Form -->
				<div id="login-form" class="auth-form active">
					<form id="loginForm" autocomplete="off">
						<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
						<div class="form-group">
							<label for="login_email">Email</label>
							<input type="email" id="login_email" name="email" autocomplete="off" required>
						</div>
						<div class="form-group">
							<label for="login_password">Password</label>
							<div class="password-wrapper">
								<input type="password" id="login_password" name="password" autocomplete="off" required>
								<button type="button" class="password-toggle" onclick="togglePassword('login_password', this)">
									<i class="fas fa-eye"></i>
								</button>
							</div>
						</div>
						<div class="form-group text-center">
							<a href="<?php echo $login_url; ?>" class="google-login-btn">
								<i class="fab fa-google"></i> Sign in with Google
							</a>
						</div>
						<div class="form-group" style="text-align:right">
							<a href="#" id="forgotPasswordLink">Forgot password?</a>
						</div>
						<button type="submit" class="btn btn-primary">Login</button>
					</form>
				</div>
				
				<!-- Register Form -->
				<div id="register-form" class="auth-form">
					<form id="registerForm" autocomplete="off">
						<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
						<div class="form-group">
							<label for="reg_username">Username</label>
							<input type="text" id="reg_username" name="username" autocomplete="off" required>
						</div>
						<div class="form-group">
							<label for="reg_email">Email</label>
							<input type="email" id="reg_email" name="email" autocomplete="off" required>
						</div>
						<div class="form-group">
							<label for="reg_business">Business Name</label>
							<input type="text" id="reg_business" name="business_name" autocomplete="off" required>
						</div>
						<div class="form-group">
							<label for="reg_password">Password</label>
							<div class="password-wrapper">
								<input type="password" id="reg_password" name="password" autocomplete="new-password" required>
								<button type="button" class="password-toggle" onclick="togglePassword('reg_password', this)">
									<i class="fas fa-eye"></i>
								</button>
							</div>
						</div>
						<div class="form-group">
							<label for="reg_confirm_password">Confirm Password</label>
							<div class="password-wrapper">
								<input type="password" id="reg_confirm_password" name="confirm_password" autocomplete="new-password" required>
								<button type="button" class="password-toggle" onclick="togglePassword('reg_confirm_password', this)">
									<i class="fas fa-eye"></i>
								</button>
							</div>
						</div>
						<button type="submit" class="btn btn-primary">Register</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Enhanced Forgot Password Modal -->
	<div id="forgotModal" class="modal" style="display:none;">
		<div class="modal-content" style="max-width:520px;">
			<div class="modal-header">
				<h2 class="modal-title">
					<i class="fas fa-key" style="margin-right: 12px;"></i>
					Reset Password
				</h2>
				<span class="close" id="closeForgot">&times;</span>
			</div>
			<div class="modal-body">
				<!-- Step Indicators -->
				<div class="reset-steps">
					<div class="step active" id="step1">
						<div class="step-number">1</div>
						<div class="step-line"></div>
					</div>
					<div class="step" id="step2">
						<div class="step-number">2</div>
						<div class="step-line"></div>
					</div>
					<div class="step" id="step3">
						<div class="step-number">3</div>
					</div>
				</div>

				<!-- Step 1: Email Input -->
				<div id="resetStepEmail">
					<div style="text-align: center; margin-bottom: 24px;">
						<i class="fas fa-envelope" style="font-size: 3rem; color: #16a085; margin-bottom: 16px;"></i>
						<h3 style="color: #2c3e50; margin: 0 0 8px 0; font-weight: 600;">Enter Your Email</h3>
						<p style="color: #34495e; margin: 0; font-size: 0.95rem; font-weight: 500;">We'll send you a verification code to reset your password</p>
					</div>
					<div class="form-group">
						<label for="fp_email">
							<i class="fas fa-envelope" style="margin-right: 8px; color: #16a085;"></i>
							Email Address
						</label>
						<input type="email" id="fp_email" placeholder="Enter your email address" required>
					</div>
					<button class="btn btn-primary" id="btnSendCode">
						Send Verification Code
					</button>
				</div>

				<!-- Step 2: Code Verification -->
				<div id="resetStepVerify" style="display:none;">
					<div style="text-align: center; margin-bottom: 24px;">
						<i class="fas fa-shield-alt" style="font-size: 3rem; color: #f39c12; margin-bottom: 16px;"></i>
						<h3 style="color: #2c3e50; margin: 0 0 8px 0; font-weight: 600;">Verify Your Identity</h3>
						<p style="color: #34495e; margin: 0; font-size: 0.95rem; font-weight: 500;">Check your email and enter the 6-digit verification code</p>
					</div>
					<div class="form-group">
						<label for="fp_code">
							<i class="fas fa-key" style="margin-right: 8px; color: #16a085;"></i>
							Verification Code
						</label>
						<input type="text" id="fp_code" placeholder="Enter 6-digit code" maxlength="6" style="text-align: center; font-size: 1.2rem; letter-spacing: 0.2em;" required>
					</div>
					<div style="text-align: center; margin-bottom: 20px;">
						<small style="color: #34495e; font-weight: 500;">
							<i class="fas fa-clock" style="margin-right: 4px;"></i>
							Code expires in 15 minutes
						</small>
					</div>
					<button class="btn btn-primary" id="btnVerifyCode">
						<i class="fas fa-check" style="margin-right: 8px;"></i>
						Verify Code
					</button>
					<div style="text-align: center; margin-top: 16px;">
						<button type="button" class="btn btn-secondary" id="btnResendCode" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">
							<i class="fas fa-redo" style="margin-right: 4px;"></i>
							Resend Code
						</button>
					</div>
				</div>

				<!-- Step 3: New Password -->
				<div id="resetStepNewPass" style="display:none;">
					<div style="text-align: center; margin-bottom: 24px;">
						<i class="fas fa-lock" style="font-size: 3rem; color: #16a085; margin-bottom: 16px;"></i>
						<h3 style="color: #2c3e50; margin: 0 0 8px 0; font-weight: 600;">Create New Password</h3>
						<p style="color: #34495e; margin: 0; font-size: 0.95rem; font-weight: 500;">Choose a strong password for your account</p>
					</div>
					<div class="form-group">
						<label for="fp_new_password">
							<i class="fas fa-lock" style="margin-right: 8px; color: #16a085;"></i>
							New Password
						</label>
						<div class="password-wrapper">
							<input type="password" id="fp_new_password" placeholder="Enter new password" required>
							<button type="button" class="password-toggle" onclick="togglePassword('fp_new_password', this)">
								<i class="fas fa-eye"></i>
							</button>
						</div>
						<small style="color: #34495e; font-size: 0.85rem; margin-top: 4px; display: block; font-weight: 500;">
							<i class="fas fa-info-circle" style="margin-right: 4px;"></i>
							Minimum 6 characters
						</small>
					</div>
					<div class="form-group">
						<label for="fp_confirm_password">
							<i class="fas fa-lock" style="margin-right: 8px; color: #16a085;"></i>
							Confirm Password
						</label>
						<div class="password-wrapper">
							<input type="password" id="fp_confirm_password" placeholder="Confirm new password" required>
							<button type="button" class="password-toggle" onclick="togglePassword('fp_confirm_password', this)">
								<i class="fas fa-eye"></i>
							</button>
						</div>
					</div>
					<button class="btn btn-primary" id="btnResetPassword">
						<i class="fas fa-save" style="margin-right: 8px;"></i>
						Reset Password
					</button>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script>
		// Clear all form fields on page load
		window.addEventListener('load', function() {
			// Clear login form
			document.getElementById('login_email').value = '';
			document.getElementById('login_password').value = '';
			
			// Clear register form
			document.getElementById('reg_username').value = '';
			document.getElementById('reg_email').value = '';
			document.getElementById('reg_business').value = '';
			document.getElementById('reg_password').value = '';
			document.getElementById('reg_confirm_password').value = '';
			
			// Disable autocomplete
			document.querySelectorAll('input').forEach(input => {
				input.setAttribute('autocomplete', 'off');
			});
		});

		// Toggle password visibility
		function togglePassword(inputId, button) {
			const input = document.getElementById(inputId);
			const icon = button.querySelector('i');
			
			if (input.type === 'password') {
				input.type = 'text';
				icon.classList.remove('fa-eye');
				icon.classList.add('fa-eye-slash');
			} else {
				input.type = 'password';
				icon.classList.remove('fa-eye-slash');
				icon.classList.add('fa-eye');
			}
		}
	</script>
	<script src="assets/js/auth.js"></script>
</body>
</html>

