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
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(135deg, #c7bdd8 0%, #a594c2 100%);
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 400px;
        }
        
        .form-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-4 {
            margin-top: 20px;
        }
        
        .auth-link {
            color: #7b2cff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .auth-link:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #f1e8ff;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #7b2cff;
            box-shadow: 0 0 0 3px rgba(123, 44, 255, 0.1);
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: #7b2cff;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #6a1bb9;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="login-wrapper fade-in">
        <div class="form-container">
            <div class="text-center" style="margin-bottom: 30px;">
                <h2 style="color: #7b2cff; font-size: 28px;"><?php echo SITE_NAME; ?></h2>
                <p style="color: #666;">Welcome back! Please login to continue</p>
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
                <p>Don't have an account? <a href="register.php" class="auth-link">Register here</a></p>
            </div>
            
            <div class="text-center mt-4">
                <!-- <p style="color: #666; font-size: 12px;">
                    <strong>Demo Credentials:</strong><br>
                    Admin: admin / admin123<br>
                    Student: john.doe / student123
                </p> -->
            </div>
        </div>
    </div>
</body>
</html>