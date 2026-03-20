<?php
// student/profile.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($conn, $_POST['full_name']);
        $email = sanitize($conn, $_POST['email']);
        $phone = sanitize($conn, $_POST['phone'] ?? '');
        $address = sanitize($conn, $_POST['address'] ?? '');
        $bio = sanitize($conn, $_POST['bio'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($full_name)) {
            $errors[] = "Full name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email already exists for another user
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, bio = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $full_name, $email, $phone, $address, $bio, $user_id);
            
            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                
                // Update session
                $_SESSION['full_name'] = $full_name;
            } else {
                $error = "Failed to update profile: " . $conn->error;
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password: " . $conn->error;
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// Handle cover photo upload
if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    if (!in_array($_FILES['cover_photo']['type'], $allowed_types)) {
        $error = "Only JPG, PNG and GIF images are allowed";
    } elseif ($_FILES['cover_photo']['size'] > $max_size) {
        $error = "File size must be less than 5MB";
    } else {
        $upload_dir = '../assets/uploads/covers/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $extension = pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'cover_' . $user_id . '_' . time() . '.' . $extension;
        if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $upload_dir . $filename)) {
            if (!empty($user['cover_photo'])) {
                $old_file = $upload_dir . $user['cover_photo'];
                if (file_exists($old_file)) unlink($old_file);
            }
            $stmt = $conn->prepare("UPDATE users SET cover_photo = ? WHERE id = ?");
            $stmt->bind_param("si", $filename, $user_id);
            if ($stmt->execute()) {
                $success = "Cover photo updated successfully!";
                $user['cover_photo'] = $filename;
            } else {
                $error = "Failed to update cover photo in database";
            }
        } else {
            $error = "Failed to upload cover photo";
        }
    }
}

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
        $error = "Only JPG, PNG and GIF images are allowed";
    } elseif ($_FILES['profile_picture']['size'] > $max_size) {
        $error = "File size must be less than 5MB";
    } else {
        $upload_dir = '../assets/uploads/profiles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
            // Delete old profile picture if not default
            if ($user['profile_picture'] != 'default-avatar.png') {
                $old_file = $upload_dir . $user['profile_picture'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Update database
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $filename, $user_id);
            
            if ($stmt->execute()) {
                $success = "Profile picture updated successfully!";
                $user['profile_picture'] = $filename;
            } else {
                $error = "Failed to update profile picture in database";
            }
        } else {
            $error = "Failed to upload profile picture";
        }
    }
}

// Get user statistics
$stats = [];
$stats['total_lessons'] = $conn->query("SELECT COUNT(*) as count FROM user_activity WHERE user_id = $user_id AND activity_type = 'lesson'")->fetch_assoc()['count'];
$stats['total_generates'] = $conn->query("SELECT COUNT(*) as count FROM user_activity WHERE user_id = $user_id AND activity_type = 'generate'")->fetch_assoc()['count'];
$stats['total_favorites'] = $conn->query("SELECT COUNT(*) as count FROM favorites WHERE user_id = $user_id")->fetch_assoc()['count'];
$stats['join_date'] = date('F d, Y', strtotime($user['join_date']));
$stats['last_login'] = $user['last_login'] ? date('F d, Y', strtotime($user['last_login'])) : 'Never';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .student-header {
            background: var(--white);
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .cover-photo {
            position: relative;
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #7b2cff 0%, #a855f7 50%, #c084fc 100%);
            overflow: hidden;
            border-radius: 20px 20px 0 0;
        }

        .cover-edit-btn {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.45);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.3s;
            z-index: 10;
        }

        .cover-edit-btn:hover {
            background: rgba(0,0,0,0.65);
        }

        #cover-input { display: none; }

        .cover-photo img.cover-img {
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        .profile-header {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            padding: 0 30px 20px 30px;
            margin-top: -75px;
            position: relative;
            z-index: 20;
        }

        .profile-avatar-large {
            position: relative;
            width: 150px;
            height: 150px;
            flex-shrink: 0;
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--white);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .upload-overlay {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 38px;
            height: 38px;
            background: var(--primary-purple);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            border: 3px solid var(--white);
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            background: #6a1bb9;
            transform: scale(1.1);
        }

        .upload-overlay i { font-size: 16px; }

        #file-input { display: none; }

        .profile-title { padding-bottom: 10px; }

        .profile-title h2 {
            color: var(--dark);
            font-size: 26px;
            margin-bottom: 4px;
        }

        .profile-title p {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .stat-card i {
            font-size: 32px;
            color: var(--primary-purple);
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--light-purple);
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            color: var(--gray);
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .tab-btn i {
            margin-right: 8px;
        }
        
        .tab-btn:hover {
            color: var(--primary-purple);
            background: var(--light-purple);
        }
        
        .tab-btn.active {
            color: var(--primary-purple);
            background: var(--light-purple);
        }
        
        .tab-content {
            display: none;
            background: var(--white);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--light-purple);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-purple);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group.readonly input,
        .form-group.readonly select {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .btn-save {
            background: var(--primary-purple);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            background: #6a1bb9;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #f5f5f5;
            color: var(--gray);
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        
        .btn-cancel:hover {
            background: #e0e0e0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: var(--light-purple);
            border-radius: 10px;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .cover-photo { height: 150px; }
            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                margin-top: -50px;
            }
            
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <h2><?php echo SITE_NAME; ?></h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="generate.php"><i class="fas fa-magic"></i> <span>Generate</span></a></li>
                <li><a href="lessons.php"><i class="fas fa-book"></i> <span>Lessons</span></a></li>
                <li><a href="favorites.php"><i class="fas fa-heart"></i> <span>Favorites</span></a></li>
                <li><a href="rankings.php"><i class="fas fa-trophy"></i> <span>Rankings</span></a></li>
                <li class="active"><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="nav-menu">
                    <a href="generate.php" class="nav-item">Generate</a>
                    <a href="lessons.php" class="nav-item">Lessons</a>
                    <a href="favorites.php" class="nav-item">Favorites</a>
                    <a href="rankings.php" class="nav-item">Rankings</a>
                    <a href="profile.php" class="nav-item active">Profile</a>
                </div>
                <div class="user-profile" onclick="window.location.href='profile.php'" style="cursor: pointer;">
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <img src="../assets/uploads/profiles/<?php echo $user['profile_picture']; ?>" alt="Profile">
                </div>
            </div>
            
            <div class="profile-container">
                <!-- Student Header with Cover Photo -->
                <div class="student-header">
                    <div class="cover-photo">
                        <?php if (!empty($user['cover_photo'])): ?>
                            <img src="../assets/uploads/covers/<?php echo $user['cover_photo']; ?>" alt="Cover" class="cover-img">
                        <?php endif; ?>
                        <form id="coverForm" method="POST" enctype="multipart/form-data">
                            <input type="file" id="cover-input" name="cover_photo" accept="image/*" onchange="this.form.submit()">
                        </form>
                        <button class="cover-edit-btn" onclick="document.getElementById('cover-input').click();">
                            <i class="fas fa-camera"></i> Edit Cover Photo
                        </button>
                    </div>
                    <div class="profile-header">
                        <div class="profile-avatar-large">
                            <img src="../assets/uploads/profiles/<?php echo $user['profile_picture']; ?>" alt="Profile" id="profileImage">
                            <div class="upload-overlay" onclick="document.getElementById('file-input').click();">
                                <i class="fas fa-camera"></i>
                            </div>
                            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                                <input type="file" id="file-input" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                            </form>
                        </div>
                        <div class="profile-title">
                            <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><i class="fas fa-id-card"></i> Student ID: <?php echo htmlspecialchars($user['student_id']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Display Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="profile-stats">
                    <div class="stat-card">
                        <i class="fas fa-book-open"></i>
                        <h3><?php echo $stats['total_lessons']; ?></h3>
                        <p>Lessons Completed</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-magic"></i>
                        <h3><?php echo $stats['total_generates']; ?></h3>
                        <p>Content Generated</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-heart"></i>
                        <h3><?php echo $stats['total_favorites']; ?></h3>
                        <p>Favorites</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-fire"></i>
                        <h3><?php echo $user['login_streak']; ?></h3>
                        <p>Day Streak</p>
                    </div>
                </div>
                
                <!-- Profile Tabs -->
                <div class="profile-tabs">
                    <button class="tab-btn active" onclick="openTab(event, 'personal-info')">
                        <i class="fas fa-user"></i> Personal Information
                    </button>
                    <button class="tab-btn" onclick="openTab(event, 'edit-profile')">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                    <button class="tab-btn" onclick="openTab(event, 'change-password')">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                    <button class="tab-btn" onclick="openTab(event, 'account-info')">
                        <i class="fas fa-info-circle"></i> Account Info
                    </button>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personal-info" class="tab-content active">
                    <h3 style="margin-bottom: 20px;">Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Bio</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['bio'] ?? 'No bio yet'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Student ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['student_id']); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Profile Tab -->
                <div id="edit-profile" class="tab-content">
                    <h3 style="margin-bottom: 20px;">Edit Profile</h3>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Student ID</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['student_id']); ?>" readonly class="readonly">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea name="bio" placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div>
                            <button type="submit" name="update_profile" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn-cancel" onclick="openTab(event, 'personal-info')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password Tab -->
                <div id="change-password" class="tab-content">
                    <h3 style="margin-bottom: 20px;">Change Password</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Current Password *</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password *</label>
                                <input type="password" name="new_password" required>
                                <small style="color: var(--gray);">Minimum 6 characters</small>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password *</label>
                                <input type="password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div>
                            <button type="submit" name="change_password" class="btn-save">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Account Info Tab -->
                <div id="account-info" class="tab-content">
                    <h3 style="margin-bottom: 20px;">Account Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Role</div>
                            <div class="info-value"><?php echo ucfirst($user['role']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Join Date</div>
                            <div class="info-value"><?php echo $stats['join_date']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Login</div>
                            <div class="info-value"><?php echo $stats['last_login']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Account Status</div>
                            <div class="info-value">
                                <?php if ($user['is_active']): ?>
                                    <span style="color: #00C851;">Active</span>
                                <?php else: ?>
                                    <span style="color: #ff4444;">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Points</div>
                            <div class="info-value"><?php echo $user['points']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching function
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tab buttons
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            // Show current tab and add active class to button
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        
        // Preview image before upload
        document.getElementById('file-input').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImage').src = e.target.result;
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        // Confirm before leaving with unsaved changes
        let formChanged = false;
        
        document.querySelectorAll('#edit-profile input, #edit-profile textarea').forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Reset form changed flag after save
        document.querySelector('#edit-profile form').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>