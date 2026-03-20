<?php
// auth/register.php
require_once '../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($conn, $_POST['full_name']);
    $username = sanitize($conn, $_POST['username']);
    $email = sanitize($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (strlen($username) < 3) $errors[] = "Username must be at least 3 characters";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    
    // Check if username exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = "Username already exists";
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = "Email already registered";
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $student_id = 'STU' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, student_id, role) VALUES (?, ?, ?, ?, ?, 'student')");
        $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $student_id);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Initialize rankings
            $stmt2 = $conn->prepare("INSERT INTO rankings (user_id) VALUES (?)");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            
            $success = "Registration successful! You can now login.";
            header("refresh:3;url=login.php");
        } else {
            $error = "Registration failed: " . $conn->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
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
        }
        
        .register-wrapper {
            width: 100%;
            max-width: 500px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-3 {
            margin-top: 15px;
        }
        
        .auth-link {
            color: var(--primary-purple);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="register-wrapper fade-in">
        <div class="form-container">
            <div class="text-center" style="margin-bottom: 30px;">
                <h2 style="color: var(--primary-purple);">Create Account</h2>
                <p style="color: var(--gray);">Join our learning platform</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>
            
            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php" class="auth-link">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>