<?php
// student/generate.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$generated_content = '';
$success = '';
$error = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Update session with latest data
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];

// Handle generate form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
    $prompt = sanitize($conn, $_POST['prompt']);
    
    if (!empty($prompt)) {
        // Simulate AI generation
        $templates = [
            "📚 Lesson Summary for: $prompt\n\n" .
            "=================================\n" .
            "KEY LEARNING OBJECTIVES:\n" .
            "=================================\n" .
            "• Understand core concepts of $prompt\n" .
            "• Identify practical applications\n" .
            "• Analyze real-world examples\n" .
            "• Practice with exercises\n\n" .
            "=================================\n" .
            "MAIN TOPICS COVERED:\n" .
            "=================================\n" .
            "1. Introduction to $prompt\n" .
            "2. Fundamental principles\n" .
            "3. Advanced concepts\n" .
            "4. Case studies and examples\n" .
            "5. Practice problems\n\n" .
            "=================================\n" .
            "QUICK REVIEW:\n" .
            "=================================\n" .
            "✓ Key terms defined\n" .
            "✓ Important formulas\n" .
            "✓ Common mistakes to avoid\n" .
            "✓ Study tips and tricks",
            
            "🎯 Study Guide: $prompt\n\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "PART 1: FOUNDATIONS\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "• Basic terminology\n" .
            "• Core principles\n" .
            "• Historical context\n\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "PART 2: DEEP DIVE\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "• Detailed explanation\n" .
            "• Key theories\n" .
            "• Expert insights\n\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "PART 3: PRACTICE\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "• 5 review questions\n" .
            "• 3 discussion topics\n" .
            "• 2 hands-on exercises",
            
            "📝 Flashcards for: $prompt\n\n" .
            "════════════════════════════\n" .
            "CARD 1 ════════════════════\n" .
            "Q: What is the main concept of $prompt?\n" .
            "A: [Insert definition here]\n\n" .
            "CARD 2 ════════════════════\n" .
            "Q: Why is $prompt important?\n" .
            "A: [Explain significance]\n\n" .
            "CARD 3 ════════════════════\n" .
            "Q: What are the key applications?\n" .
            "A: [List applications]\n\n" .
            "CARD 4 ════════════════════\n" .
            "Q: Give an example of $prompt\n" .
            "A: [Provide example]\n\n" .
            "CARD 5 ════════════════════\n" .
            "Q: Common misconceptions?\n" .
            "A: [Address myths]"
        ];
        
        $generated_content = $templates[array_rand($templates)];
        
        // Save to database
        $stmt = $conn->prepare("INSERT INTO generated_content (user_id, title, content, type) VALUES (?, ?, ?, 'generated')");
        $stmt->bind_param("iss", $user_id, $prompt, $generated_content);
        $stmt->execute();
        
        // Log activity
        $today = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type, count) 
                                VALUES (?, ?, 'generate', 1) 
                                ON DUPLICATE KEY UPDATE count = count + 1");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        
        $success = "Content generated successfully!";
    } else {
        $error = "Please enter a topic to generate content";
    }
}

// Handle save to favorites
if (isset($_POST['save_favorite'])) {
    $title = sanitize($conn, $_POST['title']);
    $content = sanitize($conn, $_POST['content']);
    
    $stmt = $conn->prepare("INSERT INTO favorites (user_id, content_type, content_data, title) VALUES (?, 'generated', ?, ?)");
    $stmt->bind_param("iss", $user_id, $content, $title);
    
    if ($stmt->execute()) {
        $success = "✅ Content saved to favorites!";
    } else {
        $error = "Failed to save to favorites";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Content - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .generate-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .prompt-box {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .prompt-box h2 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .prompt-box h2 i {
            color: #7b2cff;
        }
        
        .prompt-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #f1e8ff;
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .prompt-input:focus {
            outline: none;
            border-color: #7b2cff;
            box-shadow: 0 0 0 3px rgba(123, 44, 255, 0.1);
        }
        
        .generate-btn {
            background: #7b2cff;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .generate-btn:hover {
            background: #6a1bb9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(123, 44, 255, 0.3);
        }
        
        .result-box {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .result-header h3 {
            color: #333;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .favorite-btn {
            background: #f1e8ff;
            color: #7b2cff;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .favorite-btn:hover {
            background: #ff4444;
            color: white;
        }
        
        .generated-content {
            background: #f1e8ff;
            padding: 25px;
            border-radius: 15px;
            white-space: pre-line;
            line-height: 1.8;
            font-size: 15px;
            color: #333;
            border: 1px solid rgba(123, 44, 255, 0.1);
        }
        
        .examples-section {
            margin-top: 30px;
        }
        
        .examples-title {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .example-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .example-tag {
            background: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #f1e8ff;
        }
        
        .example-tag:hover {
            background: #7b2cff;
            color: white;
            border-color: #7b2cff;
        }
        
        .example-tag i {
            margin-right: 5px;
            font-size: 12px;
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
                <li class="active"><a href="generate.php"><i class="fas fa-magic"></i> <span>Generate</span></a></li>
                <li><a href="lessons.php"><i class="fas fa-book"></i> <span>Lessons</span></a></li>
                <li><a href="favorites.php"><i class="fas fa-heart"></i> <span>Favorites</span></a></li>
                <li><a href="rankings.php"><i class="fas fa-trophy"></i> <span>Rankings</span></a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="nav-menu">
                    <a href="generate.php" class="nav-item active">
                        <i class="fas fa-magic"></i> Generate
                    </a>
                    <a href="lessons.php" class="nav-item">
                        <i class="fas fa-book"></i> Lessons
                    </a>
                    <a href="favorites.php" class="nav-item">
                        <i class="fas fa-heart"></i> Favorites
                    </a>
                    <a href="rankings.php" class="nav-item">
                        <i class="fas fa-trophy"></i> Rankings
                    </a>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </div>
                
                <div class="user-profile" onclick="window.location.href='profile.php'" style="cursor: pointer;">
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="user-avatar-wrapper">
                        <img src="../assets/uploads/profiles/<?php echo $user['profile_picture']; ?>" alt="Profile" class="user-avatar">
                    </div>
                </div>
            </div>
            
            <!-- Generate Content -->
            <div class="generate-container fade-in">
                <!-- Display Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Prompt Box -->
                <div class="prompt-box">
                    <h2>
                        <i class="fas fa-magic"></i>
                        Generate Learning Content
                    </h2>
                    
                    <form method="POST" action="" id="generateForm">
                        <input type="text" 
                               name="prompt" 
                               class="prompt-input" 
                               placeholder="e.g., Photosynthesis, World War II, Python Programming, Algebra..." 
                               value="<?php echo isset($_POST['prompt']) ? htmlspecialchars($_POST['prompt']) : ''; ?>"
                               required>
                        
                        <button type="submit" name="generate" class="generate-btn">
                            <i class="fas fa-play"></i>
                            Generate Content
                        </button>
                    </form>
                    
                    <!-- Example Topics -->
                    <div class="examples-section">
                        <div class="examples-title">
                            <i class="fas fa-lightbulb"></i> Try these topics:
                        </div>
                        <div class="example-tags">
                            <span class="example-tag" onclick="document.querySelector('.prompt-input').value = 'Photosynthesis'">
                                <i class="fas fa-leaf"></i> Photosynthesis
                            </span>
                            <span class="example-tag" onclick="document.querySelector('.prompt-input').value = 'World War II'">
                                <i class="fas fa-globe"></i> World War II
                            </span>
                            <span class="example-tag" onclick="document.querySelector('.prompt-input').value = 'Python Programming'">
                                <i class="fas fa-code"></i> Python
                            </span>
                            <span class="example-tag" onclick="document.querySelector('.prompt-input').value = 'Algebra'">
                                <i class="fas fa-calculator"></i> Algebra
                            </span>
                            <span class="example-tag" onclick="document.querySelector('.prompt-input').value = 'Shakespeare'">
                                <i class="fas fa-feather"></i> Shakespeare
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Generated Content -->
                <?php if ($generated_content): ?>
                <div class="result-box">
                    <div class="result-header">
                        <h3>
                            <i class="fas fa-file-alt" style="color: #7b2cff;"></i>
                            Generated Result: <?php echo htmlspecialchars($_POST['prompt']); ?>
                        </h3>
                        
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="title" value="<?php echo htmlspecialchars($_POST['prompt']); ?>">
                            <input type="hidden" name="content" value="<?php echo htmlspecialchars($generated_content); ?>">
                            <button type="submit" name="save_favorite" class="favorite-btn">
                                <i class="fas fa-heart"></i>
                                Save to Favorites
                            </button>
                        </form>
                    </div>
                    
                    <div class="generated-content">
                        <?php echo nl2br(htmlspecialchars($generated_content)); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.querySelectorAll('.example-tag').forEach(tag => {
            tag.addEventListener('dblclick', function() {
                document.getElementById('generateForm').submit();
            });
        });
        
        document.getElementById('generateForm')?.addEventListener('submit', function() {
            const btn = document.querySelector('.generate-btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            btn.disabled = true;
        });
    </script>
</body>
</html>