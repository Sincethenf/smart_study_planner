<?php
// student/favorites.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle remove favorite
if (isset($_POST['remove_favorite'])) {
    $favorite_id = $_POST['favorite_id'];
    $stmt = $conn->prepare("DELETE FROM favorites WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $favorite_id, $user_id);
    $stmt->execute();
}

// Get user's favorites
$favorites = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC");
$favorites->bind_param("i", $user_id);
$favorites->execute();
$favorites = $favorites->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorites - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <li class="active"><a href="favorites.php"><i class="fas fa-heart"></i> <span>Favorites</span></a></li>
                <li><a href="rankings.php"><i class="fas fa-trophy"></i> <span>Rankings</span></a></li>
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
                    <a href="favorites.php" class="nav-item active">Favorites</a>
                    <a href="rankings.php" class="nav-item">Rankings</a>
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <h4><?php echo $_SESSION['full_name']; ?></h4>
                        <p>Student</p>
                    </div>
                    <img src="../assets/uploads/profiles/default-avatar.png" alt="Profile">
                </div>
            </div>
            
            <!-- Favorites Content -->
            <div class="fade-in">
                <h2 style="color: var(--dark); margin-bottom: 20px;">
                    <i class="fas fa-heart" style="color: #ff4444;"></i> My Favorites
                </h2>
                
                <?php if ($favorites->num_rows == 0): ?>
                    <div class="alert alert-warning" style="text-align: center; padding: 50px;">
                        <i class="fas fa-heart-broken" style="font-size: 48px; color: #ff4444;"></i>
                        <h3 style="margin: 20px 0;">No favorites yet</h3>
                        <p>Start generating content or save lessons to see them here!</p>
                        <a href="generate.php" class="btn btn-primary" style="margin-top: 20px;">Generate Content</a>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php while($favorite = $favorites->fetch_assoc()): ?>
                            <div class="content-card">
                                <div class="card-header">
                                    <h3 class="card-title"><?php echo htmlspecialchars($favorite['title']); ?></h3>
                                    <div class="card-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="favorite_id" value="<?php echo $favorite['id']; ?>">
                                            <button type="submit" name="remove_favorite" style="background: none; border: none; cursor: pointer;">
                                                <i class="fas fa-trash" style="color: #ff4444;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <?php echo substr(htmlspecialchars($favorite['content_data']), 0, 150); ?>...
                                </div>
                                <div class="card-footer">
                                    <span class="badge"><?php echo $favorite['content_type']; ?></span>
                                    <small><?php echo date('M d, Y', strtotime($favorite['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>