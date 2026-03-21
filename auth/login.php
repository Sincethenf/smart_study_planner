<?php
// auth/login.php
require_once '../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../student/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($conn, $_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, profile_picture FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Store ALL user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                
                // Update login streak
                updateLoginStreak($conn, $user['id']);
                
                // Redirect based on role
                if ($user['role'] == 'admin') {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../student/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            overflow: hidden;
        }
        
        .login-wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
        }
        
        .left-section {
            flex: 1;
            background: linear-gradient(135deg, #7b2cff 0%, #9b4dff 100%);
            padding: 80px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .left-section::before {
            content: '';
            position: absolute;
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .left-section::after {
            content: '';
            position: absolute;
            bottom: -15%;
            right: -15%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite reverse;
        }
        
        .background-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        
        .shape1 {
            width: 300px;
            height: 300px;
            top: 10%;
            right: 15%;
            animation: float 7s ease-in-out infinite;
        }
        
        .shape2 {
            width: 200px;
            height: 200px;
            bottom: 20%;
            left: 10%;
            animation: float 5s ease-in-out infinite reverse;
        }
        
        .shape3 {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 5%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape4 {
            width: 100px;
            height: 100px;
            top: 25%;
            left: 30%;
            background: rgba(255,255,255,0.05);
            animation: float 4s ease-in-out infinite;
        }
        
        .shape5 {
            width: 180px;
            height: 180px;
            bottom: 10%;
            right: 25%;
            background: rgba(255,255,255,0.06);
            animation: float 5.5s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            50% {
                transform: translateY(-30px) translateX(20px);
            }
        }
        
        .platform-info {
            position: relative;
            z-index: 1;
        }
        
        .platform-logo h1 {
            color: white;
            font-size: 42px;
            margin-bottom: 15px;
            text-shadow: 0 4px 15px rgba(0,0,0,0.3);
            font-weight: 700;
        }
        
        .platform-tagline {
            color: rgba(255,255,255,0.95);
            font-size: 20px;
            margin-bottom: 50px;
            font-weight: 300;
        }
        
        .features-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: white;
            transition: transform 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateX(10px);
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.25);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .feature-text h3 {
            font-size: 17px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .feature-text p {
            font-size: 14px;
            color: rgba(255,255,255,0.85);
            font-weight: 300;
        }
        
        .form-container {
            flex: 1;
            background: rgba(30, 30, 47, 0.98);
            padding: 80px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }
        
        .form-header {
            margin-bottom: 30px;
        }
        
        .form-header h2 {
            color: #9b4dff;
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .form-header p {
            color: #b0b0b0;
            font-size: 15px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-4 {
            margin-top: 20px;
        }
        
        .auth-link {
            color: #9b4dff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .auth-link:hover {
            color: #7b2cff;
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: rgba(255, 68, 68, 0.15);
            color: #ff6b6b;
            border: 1px solid rgba(255, 68, 68, 0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #e0e0e0;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(123, 44, 255, 0.3);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: rgba(20, 20, 35, 0.8);
            color: #ffffff;
        }
        
        .form-control::placeholder {
            color: #888;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #7b2cff;
            box-shadow: 0 0 0 3px rgba(123, 44, 255, 0.2);
            background: rgba(20, 20, 35, 1);
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #7b2cff 0%, #9b4dff 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(123, 44, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #6a1bb9 0%, #8a3de6 100%);
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(123, 44, 255, 0.4);
        }
        
        @media (max-width: 1024px) {
            .left-section {
                padding: 60px 40px;
            }
            
            .form-container {
                padding: 60px 40px;
            }
            
            .platform-logo h1 {
                font-size: 36px;
            }
            
            .platform-tagline {
                font-size: 18px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                overflow-y: auto;
            }
            
            .login-wrapper {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }
            
            .left-section {
                padding: 50px 30px;
                min-height: auto;
            }
            
            .form-container {
                padding: 50px 30px;
            }
            
            .platform-logo h1 {
                font-size: 32px;
            }
            
            .platform-tagline {
                font-size: 16px;
                margin-bottom: 35px;
            }
            
            .feature-item {
                margin-bottom: 20px;
            }
            
            .form-header h2 {
                font-size: 28px;
            }
        }
        
        @media (max-width: 480px) {
            .left-section {
                padding: 40px 20px;
            }
            
            .form-container {
                padding: 40px 20px;
            }
            
            .platform-logo h1 {
                font-size: 28px;
            }
            
            .platform-tagline {
                font-size: 15px;
                margin-bottom: 30px;
            }
            
            .feature-icon {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }
            
            .feature-text h3 {
                font-size: 15px;
            }
            
            .feature-text p {
                font-size: 13px;
            }
            
            .feature-item {
                margin-bottom: 18px;
            }
            
            .form-header h2 {
                font-size: 24px;
            }
            
            .form-header p {
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 18px;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .btn-primary {
                padding: 12px;
                font-size: 15px;
            }
            
            .mt-4 {
                margin-top: 15px;
            }
        }
        
        @media (max-width: 360px) {
            .left-section {
                padding: 30px 15px;
            }
            
            .form-container {
                padding: 30px 15px;
            }
            
            .platform-logo h1 {
                font-size: 24px;
            }
            
            .platform-tagline {
                font-size: 14px;
            }
            
            .feature-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
            
            .feature-text h3 {
                font-size: 14px;
            }
            
            .feature-text p {
                font-size: 12px;
            }
            
            .form-header h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper fade-in">
        <div class="left-section">
            <div class="background-shapes">
                <div class="shape shape1"></div>
                <div class="shape shape2"></div>
                <div class="shape shape3"></div>
                <div class="shape shape4"></div>
                <div class="shape shape5"></div>
            </div>
            
            <div class="platform-info">
                <div class="platform-logo">
                    <h1><i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?></h1>
                    <p class="platform-tagline">Your Smart Learning Companion</p>
                </div>
                
                <ul class="features-list">
                    <li class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="feature-text">
                            <h3>Interactive Lessons</h3>
                            <p>Learn with engaging content</p>
                        </div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="feature-text">
                            <h3>Track Progress</h3>
                            <p>Monitor your learning journey</p>
                        </div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="feature-text">
                            <h3>Earn Rewards</h3>
                            <p>Get points and achievements</p>
                        </div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="feature-text">
                            <h3>Compete & Collaborate</h3>
                            <p>Join the learning community</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="form-container">
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Please login to continue your learning journey</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username or email" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="text-center mt-4">
                <p style="color: #b0b0b0; font-size: 14px;">Don't have an account? <a href="register.php" class="auth-link">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>