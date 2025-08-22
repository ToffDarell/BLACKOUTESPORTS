<?php
// Start session
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$username = $email = $phone = "";
$password = $confirm_password = "";
$username_err = $email_err = $phone_err = "";
$password_err = $confirm_password_err = "";
$signup_success = false;

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Check if email is valid
        if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
            $email_err = "Invalid email format.";
        } else {
            // Check if email exists
            $sql = "SELECT id FROM users WHERE email = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_email);
                $param_email = trim($_POST["email"]);
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $email_err = "This email is already registered.";
                    } else {
                        $email = trim($_POST["email"]);
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
    }
    
    // Validate phone number
    if (!empty(trim($_POST["phone"]))) {
        // Simple phone validation (can be enhanced)
        if (!preg_match("/^[0-9]{11}$/", trim($_POST["phone"]))) {
            $phone_err = "Please enter a valid phone number (11 digits).";
        } else {
            $phone = trim($_POST["phone"]);
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($email_err) && empty($phone_err) && 
        empty($password_err) && empty($confirm_password_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, 'customer', NOW())";
         
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssss", $param_username, $param_email, $param_phone, $param_password);
            
            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_phone = $phone;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Redirect to login page
                $signup_success = true;
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Blackout Esports - Gaming Community</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #00bfff;
            --secondary-color: #ff6b6b;
            --accent-color: #7c4dff;
            --background-dark: #121212;
            --card-dark: #1e1e1e;
            --text-light: #e0e0e0;
            --text-muted: #aaaaaa;
            --success-color: #00c853;
        }
        
        body {
            background-color: var(--background-dark);
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            margin: 0;
            padding: 0;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        
        /* Header Styles */
        .site-header {
            background-color: rgba(0, 0, 0, 0.8);
            border-bottom: 1px solid rgba(0, 191, 255, 0.3);
            box-shadow: 0 2px 20px rgba(0, 191, 255, 0.2);
            padding: 15px 0;
            position: relative;
            z-index: 100;
        }
        
        .navbar-brand img {
            height: 50px;
            filter: drop-shadow(0 0 8px rgba(0, 191, 255, 0.6));
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 15px;
            margin: 0 5px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover {
            color: var(--primary-color);
        }
        
        .navbar-dark .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .navbar-dark .navbar-nav .nav-link:hover::after {
            width: 80%;
        }
        
        .navbar-dark .navbar-nav .active > .nav-link {
            color: var(--primary-color);
        }
        
        .navbar-dark .navbar-nav .active > .nav-link::after {
            width: 80%;
        }
        
        .navbar-dark .navbar-toggler {
            border-color: rgba(0, 191, 255, 0.3);
        }
        
        .navbar-dark .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 191, 255, 0.25);
        }
        
        .header-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .header-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 191, 255, 0.4);
            color: white;
        }
        
        /* Main Content Styles */
        .signup-container {
            max-width: 550px;
            margin: 50px auto;
            padding: 30px;
            background-color: rgba(30, 30, 30, 0.9);
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 191, 255, 0.3);
            border: 1px solid rgba(0, 191, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo img {
            max-width: 220px;
            filter: drop-shadow(0 0 10px rgba(0, 191, 255, 0.5));
        }
        
        h2 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 0 10px rgba(0, 191, 255, 0.5);
        }
        
        .form-control {
            background-color: rgba(45, 45, 45, 0.8);
            border: 1px solid #444;
            color: var(--text-light);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            padding: 12px;
            height: auto;
        }
        
        .form-control:focus {
            background-color: rgba(51, 51, 51, 0.8);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 191, 255, 0.25);
            color: #fff;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            width: 100%;
            padding: 14px;
            font-weight: 600;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 191, 255, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .form-text {
            color: var(--text-muted);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .login-link a:hover {
            color: var(--accent-color);
            text-shadow: 0 0 8px rgba(124, 77, 255, 0.5);
        }
        
        .input-group-text {
            background-color: rgba(45, 45, 45, 0.8);
            border: 1px solid #444;
            color: var(--text-light);
            padding: 0 15px;
        }
        
        .invalid-feedback {
            color: var(--secondary-color);
            font-size: 0.85rem;
        }
        
        .alert-success {
            background-color: rgba(0, 200, 83, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(0, 200, 83, 0.3);
            border-radius: 8px;
        }
        
        .password-strength {
            height: 5px;
            margin-top: -15px;
            margin-bottom: 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak {
            background-color: #ff4d4d;
            width: 25%;
        }
        
        .strength-medium {
            background-color: #ffaa00;
            width: 50%;
        }
        
        .strength-strong {
            background-color: #2ecc71;
            width: 100%;
        }
        
        .strength-text {
            font-size: 0.8rem;
            margin-top: -15px;
            margin-bottom: 15px;
        }
        
        .toggle-password {
            cursor: pointer;
            padding: 0 15px;
            background: none;
            border: none;
            color: var(--text-muted);
        }
        
        .toggle-password:hover {
            color: var(--primary-color);
        }
        
        .form-floating > .form-control {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }
        
        .form-floating > label {
            padding: 1rem 0.75rem;
        }
        
        .neon-border {
            position: relative;
        }
        
        .neon-border::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 15px;
            box-shadow: 0 0 20px var(--primary-color);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            z-index: -1;
        }
        
        .signup-container:hover .neon-border::after {
            opacity: 0.5;
        }
        
        .form-section {
            position: relative;
            padding: 20px;
            border-radius: 10px;
            background-color: rgba(0, 0, 0, 0.2);
            margin-bottom: 25px;
        }
        
        .form-section-title {
            position: absolute;
            top: -12px;
            left: 20px;
            background-color: var(--card-dark);
            padding: 0 10px;
            font-size: 0.9rem;
            color: var(--primary-color);
        }
        
        .avatar-selection {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .avatar-option {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 10px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            opacity: 0.7;
        }
        
        .avatar-option:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .avatar-option.selected {
            border-color: var(--primary-color);
            opacity: 1;
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.5);
        }
        
        /* Gaming-themed elements */
        .gaming-badge {
            position: absolute;
            top: -15px;
            right: -15px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.5);
        }
        
        .typing-effect {
            overflow: hidden;
            white-space: nowrap;
            margin: 0 auto;
            letter-spacing: 0.15em;
            animation: typing 3.5s steps(40, end);
        }
        
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 191, 255, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(0, 191, 255, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(0, 191, 255, 0);
            }
        }
        
        /* Loading animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 9999;
        }
        
        .loading-content {
            text-align: center;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .loading-spinner {
            border: 5px solid rgba(0, 0, 0, 0.3);
            border-radius: 50%;
            border-top: 5px solid var(--primary-color);
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Footer Styles */
        .site-footer {
            background-color: rgba(0, 0, 0, 0.8);
            border-top: 1px solid rgba(0, 191, 255, 0.3);
            padding: 30px 0;
            color: var(--text-muted);
            margin-top: 50px;
        }
        
        .footer-links h5 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .footer-links ul {
            list-style: none;
            padding-left: 0;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
            padding-left: 5px;
        }
        
        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            background-color: var(--primary-color);
            transform: translateY(-3px);
            color: white;
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <img src="assets/images/blackout-logo.png" alt="Blackout Esports" onerror="this.src='https://via.placeholder.com/180x50?text=BLACKOUT+ESPORTS'">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="computers.php">Computers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pricing.php">Pricing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tournaments.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.php">Contact</a>
                        </li>
                        <li class="nav-item ms-lg-3">
                            <a class="nav-link active" href="signup.php">Sign Up</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn header-btn" href="login.php">Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="signup-container neon-border">
            <div class="logo">
                <img src="assets/images/blackout-logo.png" alt="Blackout Esports Logo" onerror="this.src='https://via.placeholder.com/220x80?text=BLACKOUT+ESPORTS'" class="animate__animated animate__fadeIn">
            </div>
            
            <h2 class="typing-effect">BLACKOUT ESPORTS</h2>
            
            <?php if($signup_success): ?>
                <div class="alert alert-success animate__animated animate__fadeInUp" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Account created successfully! <a href="login.php" class="alert-link">Login now</a> to start gaming.
                </div>
            <?php endif; ?>
            
            <form id="signupForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-section">
                    <div class="form-section-title">Profile Info</div>
                    
                    <div class="avatar-selection">
                        <img src="https://api.dicebear.com/6.x/pixel-art/svg?seed=John" class="avatar-option" data-value="1" alt="Avatar 1">
                        <img src="https://api.dicebear.com/6.x/pixel-art/svg?seed=Jane" class="avatar-option" data-value="2" alt="Avatar 2">
                        <img src="https://api.dicebear.com/6.x/pixel-art/svg?seed=Alex" class="avatar-option" data-value="3" alt="Avatar 3">
                        <img src="https://api.dicebear.com/6.x/pixel-art/svg?seed=Sam" class="avatar-option" data-value="4" alt="Avatar 4">
                    </div>
                    <input type="hidden" name="avatar" id="avatar" value="">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Gamer Tag</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-gamepad"></i></span>
                            <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" id="username" placeholder="Choose your gamer tag">
                            <div class="invalid-feedback"><?php echo $username_err; ?></div>
                        </div>
                        <div id="usernameAvailability" class="form-text"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" id="email" placeholder="Enter your email">
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                        <div id="emailAvailability" class="form-text"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" name="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone; ?>" id="phone" placeholder="Enter your phone number">
                            <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">Security</div>
                    <div class="gaming-badge pulse">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" placeholder="Create a password">
                            <button type="button" class="toggle-password" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" placeholder="Confirm your password">
                            <button type="button" class="toggle-password" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                        </div>
                        <div id="passwordMatch" class="form-text"></div>
                    </div>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="" id="termsCheck" required>
                    <label class="form-check-label" for="termsCheck">
                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                    </label>
                    <div class="invalid-feedback">
                        You must agree before submitting.
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary animate__animated animate__pulse"
