<?php
// student/generate.php
require_once '../config/database.php';
require_once '../includes/notifications.php';
requireLogin();

$user_id          = $_SESSION['user_id'];
$generated_content = '';
$success          = '';
$error            = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$_SESSION['full_name']       = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];

// Handle generate form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['prompt'])) {
    $prompt = trim($_POST['prompt']);

    if (!empty($prompt)) {
        // Call Gemini API
        $apiKey = 'AIzaSyDKtuLyWJqaYnms-eY-fTWSNis319pTNfE';
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;
        
        $requestData = [
            'contents' => [[
                'parts' => [[
                    'text' => "Create comprehensive study material about: $prompt. Include key concepts, explanations, examples, and practice questions. Format it clearly with sections and bullet points."
                ]]
            ]],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048
            ]
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $error = 'Connection error: ' . $curlError;
        } elseif ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'JSON decode error: ' . json_last_error_msg();
            } elseif (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $generated_content = $data['candidates'][0]['content']['parts'][0]['text'];
                
                // Save to database
                $stmt = $conn->prepare("INSERT INTO generated_content (user_id, title, content, type) VALUES (?, ?, ?, 'generated')");
                $stmt->bind_param("iss", $user_id, $prompt, $generated_content);
                $stmt->execute();

                // Log activity
                $today = date('Y-m-d');
                $stmt  = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type, count)
                                          VALUES (?, ?, 'generate', 1)
                                          ON DUPLICATE KEY UPDATE count = count + 1");
                $stmt->bind_param("is", $user_id, $today);
                $stmt->execute();

                $success = "Content generated successfully!";
            } else {
                $error = 'API response invalid. ' . (isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error');
            }
        } else {
            // Decode error response
            $errorData = json_decode($response, true);
            $errorMsg = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Unknown error';
            $error = 'API Error (HTTP ' . $httpCode . '): ' . $errorMsg . '. The API key may be invalid. Get a new key from https://aistudio.google.com/app/apikey';
        }
    } else {
        $error = "Please enter a topic to generate content.";
    }
}

// Handle save to favorites
if (isset($_POST['save_favorite'])) {
    $fav_title   = sanitize($conn, $_POST['title']);
    $fav_content = sanitize($conn, $_POST['content']);

    $stmt = $conn->prepare("INSERT INTO favorites (user_id, content_type, content_data, title) VALUES (?, 'generated', ?, ?)");
    $stmt->bind_param("iss", $user_id, $fav_content, $fav_title);

    if ($stmt->execute()) {
        $success = "Content saved to favorites!";
    } else {
        $error = "Failed to save to favorites.";
    }
}

// Recent generations for history panel
$histQ = $conn->prepare("SELECT id, title, type, created_at FROM generated_content WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$histQ->bind_param("i", $user_id);
$histQ->execute();
$history = $histQ->get_result();

// Total count
$totalQ = $conn->query("SELECT COUNT(*) as c FROM generated_content WHERE user_id = $user_id");
$totalGen = $totalQ->fetch_assoc()['c'];

// Get unread notification count
$unread_notifications = getUnreadNotificationCount($conn, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════
   RESET & ROOT
═══════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07080f;--bg2:#0c0e1a;--bg3:#111320;
  --surface:#161929;--surface2:#1c2135;
  --border:rgba(255,255,255,.055);--border-hi:rgba(255,255,255,.11);
  --blue:#3b82f6;--blue-dim:rgba(59,130,246,.14);
  --violet:#8b5cf6;--violet-dim:rgba(139,92,246,.13);
  --emerald:#10b981;--emerald-dim:rgba(16,185,129,.13);
  --amber:#f59e0b;--amber-dim:rgba(245,158,11,.12);
  --rose:#f43f5e;--rose-dim:rgba(244,63,94,.12);
  --cyan:#06b6d4;--cyan-dim:rgba(6,182,212,.12);
  --text:#dde2f0;--text2:#8892aa;--text3:#4a5270;
  --sidebar-w:252px;--topbar-h:66px;
  --radius:14px;--radius-sm:9px;
  --ease:cubic-bezier(.4,0,.2,1);
}
html,body{height:100%;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;line-height:1.6}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

/* ═══════════════════════════════════════════════
   SHELL + SIDEBAR
═══════════════════════════════════════════════ */
.shell{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:200;transition:transform .3s var(--ease)}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem;letter-spacing:.04em}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 14px;margin:2px 8px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .18s var(--ease)}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:linear-gradient(90deg,var(--violet-dim),transparent);color:var(--violet);border-left:2px solid var(--violet);padding-left:12px}
.nav-link.active i{color:var(--violet)}
.sidebar-footer{padding:14px 8px;border-top:1px solid var(--border)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);background:var(--surface)}
.user-chip-avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));overflow:hidden}
.user-chip-avatar img{width:100%;height:100%;object-fit:cover}
.user-chip-name{font-size:.8rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-chip-role{font-size:.66rem;color:var(--text3)}

/* ═══════════════════════════════════════════════
   MAIN + TOPBAR
═══════════════════════════════════════════════ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:var(--topbar-h);display:flex;align-items:center;padding:0 28px;gap:14px;background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.hamburger{display:none;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);width:36px;height:36px;place-items:center;color:var(--text2);cursor:pointer;font-size:.95rem}
.topbar-title{font-size:1.05rem;font-weight:700;letter-spacing:-.02em}
.topbar-sub{font-size:.72rem;color:var(--text3)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.icon-btn{width:36px;height:36px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);display:grid;place-items:center;color:var(--text2);cursor:pointer;transition:all .18s var(--ease);text-decoration:none;font-size:.88rem;position:relative}
.icon-btn:hover{border-color:var(--border-hi);color:var(--text)}
.notif-pip{position:absolute;top:7px;right:7px;width:16px;height:16px;background:var(--rose);border-radius:50%;border:2px solid var(--bg2);color:#fff;font-size:.65rem;font-weight:700;display:grid;place-items:center;font-family:'JetBrains Mono',monospace}
.user-pill{display:flex;align-items:center;gap:9px;padding:5px 14px 5px 6px;border-radius:30px;background:var(--surface);border:1px solid var(--border);cursor:pointer;text-decoration:none;transition:border-color .18s var(--ease)}
.user-pill:hover{border-color:var(--border-hi)}
.pill-avatar{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));overflow:hidden}
.pill-avatar img{width:100%;height:100%;object-fit:cover}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ═══════════════════════════════════════════════
   PAGE
═══════════════════════════════════════════════ */
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:22px}

/* Page header */
.page-header{display:flex;align-items:center;justify-content:space-between;animation:slideUp .4s var(--ease) both}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--violet-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--violet);flex-shrink:0;box-shadow:0 0 20px rgba(139,92,246,.2)}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}
.gen-count-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:20px;background:var(--violet-dim);color:var(--violet);font-size:.72rem;font-weight:700;font-family:'JetBrains Mono',monospace;border:1px solid rgba(139,92,246,.18)}

/* ═══════════════════════════════════════════════
   TWO COLUMN LAYOUT
═══════════════════════════════════════════════ */
.gen-layout{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start}

/* ═══════════════════════════════════════════════
   PROMPT CARD
═══════════════════════════════════════════════ */
.prompt-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:26px 28px;
  animation:slideUp .4s var(--ease) .05s both;
  position:relative;overflow:hidden;
}
.prompt-card::before{
  content:'';position:absolute;top:-60px;right:-60px;
  width:250px;height:250px;border-radius:50%;
  background:radial-gradient(circle,var(--violet-dim),transparent 70%);
  pointer-events:none;
}
.prompt-card-title{font-size:.95rem;font-weight:700;letter-spacing:-.01em;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.prompt-card-title i{color:var(--violet)}
.prompt-card-sub{font-size:.78rem;color:var(--text3);margin-bottom:20px}

/* Input row */
.input-row{display:flex;gap:10px;margin-bottom:16px}
.prompt-input{
  flex:1;padding:12px 16px;
  background:var(--bg3);border:1.5px solid var(--border);
  border-radius:var(--radius-sm);
  font-family:'Outfit',sans-serif;font-size:.9rem;color:var(--text);
  outline:none;transition:border-color .18s var(--ease),box-shadow .18s var(--ease);
}
.prompt-input::placeholder{color:rgba(136,146,170,.4)}
.prompt-input:focus{border-color:var(--violet);box-shadow:0 0 0 3px rgba(139,92,246,.12)}

.gen-btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:12px 22px;border-radius:var(--radius-sm);
  background:var(--violet);color:#fff;border:none;
  font-family:'Outfit',sans-serif;font-size:.9rem;font-weight:700;
  cursor:pointer;white-space:nowrap;
  transition:background .18s var(--ease),transform .15s var(--ease),box-shadow .18s var(--ease);
  flex-shrink:0;
}
.gen-btn:hover{background:#7c3aed;transform:translateY(-1px);box-shadow:0 6px 20px rgba(139,92,246,.35)}
.gen-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}

/* Example tags */
.examples-row{margin-top:4px}
.examples-label{font-size:.72rem;color:var(--text3);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.tags-wrap{display:flex;flex-wrap:wrap;gap:8px}
.topic-tag{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 13px;border-radius:20px;
  background:var(--bg3);border:1px solid var(--border);
  font-size:.78rem;font-weight:500;color:var(--text2);
  cursor:pointer;transition:all .18s var(--ease);
}
.topic-tag i{font-size:.72rem}
.topic-tag:hover{background:var(--violet-dim);border-color:rgba(139,92,246,.3);color:var(--violet)}
.topic-tag.selected{background:var(--violet-dim);border-color:rgba(139,92,246,.3);color:var(--violet)}

/* ═══════════════════════════════════════════════
   ALERTS
═══════════════════════════════════════════════ */
.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--radius-sm);font-size:.84rem;line-height:1.5;animation:slideUp .3s var(--ease) both}
.alert i{font-size:.88rem;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--emerald-dim);color:var(--emerald);border:1px solid rgba(16,185,129,.2)}
.alert-error  {background:var(--rose-dim);  color:var(--rose);   border:1px solid rgba(244,63,94,.2)}

/* ═══════════════════════════════════════════════
   RESULT CARD
═══════════════════════════════════════════════ */
.result-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  animation:slideUp .4s var(--ease) both;
}
.result-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:18px 22px;border-bottom:1px solid var(--border);
  gap:12px;flex-wrap:wrap;
}
.result-title{font-size:.9rem;font-weight:700;display:flex;align-items:center;gap:8px;flex:1;min-width:0}
.result-title i{color:var(--violet);flex-shrink:0}
.result-topic{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--violet)}
.result-actions{display:flex;gap:8px;flex-shrink:0}

.btn-copy{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:var(--radius-sm);
  background:var(--bg3);border:1px solid var(--border);
  font-family:'Outfit',sans-serif;font-size:.78rem;font-weight:600;
  color:var(--text2);cursor:pointer;transition:all .18s var(--ease);
}
.btn-copy:hover{border-color:var(--border-hi);color:var(--text)}
.btn-copy.copied{background:var(--emerald-dim);border-color:rgba(16,185,129,.3);color:var(--emerald)}

.btn-fav{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:var(--radius-sm);
  background:var(--rose-dim);border:1px solid rgba(244,63,94,.2);
  font-family:'Outfit',sans-serif;font-size:.78rem;font-weight:600;
  color:var(--rose);cursor:pointer;border:none;transition:all .18s var(--ease);
}
.btn-fav:hover{background:var(--rose);color:#fff}

.result-content{
  padding:22px;
  background:var(--bg3);
  font-family:'JetBrains Mono',monospace;
  font-size:.82rem;line-height:1.85;
  color:var(--text2);
  white-space:pre-wrap;
  word-break:break-word;
  max-height:480px;
  overflow-y:auto;
}
/* Highlight section headers inside content */
.result-content .sec-head{color:var(--violet);font-weight:600}

/* ═══════════════════════════════════════════════
   HISTORY PANEL (right column)
═══════════════════════════════════════════════ */
.history-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  animation:slideUp .4s var(--ease) .1s both;
  position:sticky;top:calc(var(--topbar-h) + 20px);
}
.history-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 18px;border-bottom:1px solid var(--border);
}
.history-title{font-size:.875rem;font-weight:700}
.history-count{font-size:.7rem;color:var(--text3);font-family:'JetBrains Mono',monospace;background:var(--bg3);padding:2px 8px;border-radius:10px}
.history-list{display:flex;flex-direction:column;max-height:460px;overflow-y:auto}
.history-item{
  display:flex;align-items:center;gap:10px;
  padding:12px 18px;border-bottom:1px solid var(--border);
  transition:background .15s var(--ease);cursor:pointer;
}
.history-item:last-child{border-bottom:none}
.history-item:hover{background:var(--bg3)}
.history-icon{
  width:30px;height:30px;border-radius:8px;
  background:var(--violet-dim);display:grid;place-items:center;
  font-size:.75rem;color:var(--violet);flex-shrink:0;
}
.history-body{flex:1;min-width:0}
.history-item-title{font-size:.8rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.history-item-date{font-size:.68rem;color:var(--text3);font-family:'JetBrains Mono',monospace;margin-top:1px}
.history-empty{padding:28px;text-align:center;color:var(--text3);font-size:.82rem}

/* Tips card */
.tips-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:18px;
  animation:slideUp .4s var(--ease) .15s both;
}
.tips-title{font-size:.82rem;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:7px}
.tips-title i{color:var(--amber)}
.tip-item{display:flex;align-items:flex-start;gap:8px;font-size:.78rem;color:var(--text2);line-height:1.5;margin-bottom:8px}
.tip-item:last-child{margin-bottom:0}
.tip-dot{width:5px;height:5px;border-radius:50%;background:var(--violet);flex-shrink:0;margin-top:7px}

/* ═══════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════ */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
.fa-spin-custom{animation:spin .8s linear infinite}

/* Overlay */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* ═══════════════════════════════════════════════
   LOADING SCREEN
═══════════════════════════════════════════════ */
.loading-screen {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  transition: opacity 0.5s ease, visibility 0.5s ease;
}
.loading-screen.hidden {
  opacity: 0;
  visibility: hidden;
}
.loader {
  width: 60px;
  height: 60px;
  border: 4px solid rgba(139, 92, 246, 0.2);
  border-top-color: #8b5cf6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}
.loading-text {
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.9rem;
  font-weight: 500;
  letter-spacing: 0.5px;
}

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media(max-width:1100px){.gen-layout{grid-template-columns:1fr}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:16px 14px}
  .topbar{padding:0 14px}
  .input-row{flex-direction:column}
  .gen-btn{width:100%;justify-content:center}
  .history-card{position:static}
}
</style>
</head>
<body>
<div class="loading-screen" id="loadingScreen">
  <div class="loader"></div>
  <div class="loading-text">Loading Generator...</div>
</div>
<div class="shell">

<!-- ════════════════════════════════════
     SIDEBAR
════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text"><?php echo SITE_NAME; ?><span>Student Portal</span></div>
  </div>
  <div class="sidebar-nav">
    <div class="nav-group-label">Main</div>
    <a href="dashboard.php" class="nav-link"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <a href="generate.php"  class="nav-link active"><i class="fas fa-wand-magic-sparkles"></i> Generate</a>
    <a href="lessons.php"   class="nav-link"><i class="fas fa-book-open"></i> Lessons</a>
    <a href="favorites.php" class="nav-link"><i class="fas fa-heart"></i> Favorites</a>
    <div class="nav-group-label">Community</div>
    <a href="rankings.php"  class="nav-link"><i class="fas fa-trophy"></i> Rankings</a>
    <a href="forum.php"     class="nav-link"><i class="fas fa-comments"></i> Forum</a>
    <div class="nav-group-label">Account</div>
    <a href="profile.php"   class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a>
    <a href="../auth/logout.php" class="nav-link" style="color:var(--rose)"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
  </div>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-chip-avatar">
        <?php if (!empty($user['profile_picture'])): ?>
          <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
        <?php else: ?>
          <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="user-chip-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="user-chip-role">Student</div>
      </div>
    </div>
  </div>
</aside>
<div class="overlay" id="overlay"></div>

<!-- ════════════════════════════════════
     MAIN
════════════════════════════════════ -->
<div class="main">
  <header class="topbar">
    <button class="hamburger" id="menuBtn"><i class="fas fa-bars"></i></button>
    <div>
      <div class="topbar-title">Generate Content</div>
      <div class="topbar-sub">AI-powered learning material generator</div>
    </div>
    <div class="topbar-right">
      <?php include '../includes/notification_dropdown.php'; ?>
      <a href="profile.php" class="user-pill">
        <div class="pill-avatar">
          <?php if (!empty($user['profile_picture'])): ?>
            <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
          <?php else: ?>
            <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
          <?php endif; ?>
        </div>
        <span class="pill-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
      </a>
    </div>
  </header>

  <div class="page">

    <!-- ── Page header ──────────────────────── -->
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon"><i class="fas fa-wand-magic-sparkles"></i></div>
        <div>
          <div class="page-title">Generate Content</div>
          <div class="page-sub">Instant study materials for any topic</div>
        </div>
      </div>
      <span class="gen-count-chip"><i class="fas fa-bolt-lightning"></i> <?php echo $totalGen; ?> generated</span>
    </div>

    <!-- ── Alerts ────────────────────────────── -->
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ── Two-column layout ─────────────────── -->
    <div class="gen-layout">

      <!-- LEFT: generator + result -->
      <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Prompt card -->
        <div class="prompt-card">
          <div class="prompt-card-title"><i class="fas fa-wand-magic-sparkles"></i> Generate Learning Content</div>
          <div class="prompt-card-sub">Enter any topic and get instant study materials</div>

          <form method="POST" id="generateForm">
            <div class="input-row">
              <input type="text"
                     name="prompt"
                     id="promptInput"
                     class="prompt-input"
                     placeholder="e.g., Photosynthesis, World War II, Python Programming, Algebra…"
                     value="<?php echo isset($_POST['prompt']) ? htmlspecialchars($_POST['prompt']) : ''; ?>"
                     autocomplete="off"
                     required>
              <button type="submit" class="gen-btn" id="genBtn">
                <i class="fas fa-bolt-lightning" id="genIcon"></i>
                <span id="genLabel">Generate</span>
              </button>
            </div>
          </form>

          <!-- Topic chips -->
          <div class="examples-row">
            <div class="examples-label"><i class="fas fa-lightbulb" style="color:var(--amber)"></i> Try these topics:</div>
            <div class="tags-wrap">
              <?php
              $topics = [
                ['Photosynthesis',    'fa-leaf'],
                ['World War II',      'fa-globe'],
                ['Python Programming','fa-code'],
                ['Algebra',           'fa-calculator'],
                ['Shakespeare',       'fa-feather'],
                ['Cell Biology',      'fa-microscope'],
                ['Solar System',      'fa-star'],
                ['French Revolution', 'fa-flag'],
              ];
              foreach ($topics as [$label, $icon]): ?>
              <span class="topic-tag" onclick="setTopic('<?php echo $label; ?>')">
                <i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?>
              </span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Result card -->
        <?php if ($generated_content): ?>
        <div class="result-card">
          <div class="result-head">
            <div class="result-title">
              <i class="fas fa-file-lines"></i>
              <span class="result-topic"><?php echo htmlspecialchars($_POST['prompt']); ?></span>
            </div>
            <div class="result-actions">
              <button class="btn-copy" id="copyBtn" onclick="copyContent()">
                <i class="fas fa-copy"></i> Copy
              </button>
              <form method="POST" action="generate.php" style="display:inline">
                <input type="hidden" name="title"   value="<?php echo htmlspecialchars($_POST['prompt']); ?>">
                <input type="hidden" name="content" value="<?php echo htmlspecialchars($generated_content); ?>">
                <button type="submit" name="save_favorite" value="1" class="btn-fav">
                  <i class="fas fa-heart"></i> Save
                </button>
              </form>
            </div>
          </div>
          <div class="result-content" id="resultContent"><?php echo nl2br(htmlspecialchars($generated_content)); ?></div>
        </div>
        <?php endif; ?>

      </div><!-- /left col -->

      <!-- RIGHT: history + tips -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- History -->
        <div class="history-card">
          <div class="history-head">
            <div class="history-title">Recent Generations</div>
            <span class="history-count"><?php echo $totalGen; ?></span>
          </div>
          <div class="history-list">
            <?php if ($history->num_rows > 0): ?>
              <?php while ($h = $history->fetch_assoc()): ?>
              <div class="history-item" onclick="document.getElementById('promptInput').value = '<?php echo htmlspecialchars(addslashes($h['title'])); ?>'; document.getElementById('promptInput').focus()">
                <div class="history-icon"><i class="fas fa-file-lines"></i></div>
                <div class="history-body">
                  <div class="history-item-title"><?php echo htmlspecialchars($h['title']); ?></div>
                  <div class="history-item-date"><?php echo date('M d, Y', strtotime($h['created_at'])); ?></div>
                </div>
              </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="history-empty">
                <i class="fas fa-clock" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>
                No generations yet.<br>Try generating your first topic!
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tips -->
        <div class="tips-card">
          <div class="tips-title"><i class="fas fa-lightbulb"></i> Pro Tips</div>
          <div class="tip-item"><span class="tip-dot"></span>Be specific — "Mitosis vs Meiosis" gives better results than just "cells"</div>
          <div class="tip-item"><span class="tip-dot"></span>Double-click a topic chip to generate immediately</div>
          <div class="tip-item"><span class="tip-dot"></span>Save your best results to Favorites for quick access later</div>
          <div class="tip-item"><span class="tip-dot"></span>Each generation earns you activity points toward your ranking</div>
        </div>

      </div><!-- /right col -->
    </div><!-- /gen-layout -->

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
// ── Loading Screen ────────────────────────────────────────
window.addEventListener('load', function() {
  setTimeout(() => {
    document.getElementById('loadingScreen').classList.add('hidden');
  }, 1000);
});

// ── Topic chip click ──────────────────────────────────────
function setTopic(label) {
  document.getElementById('promptInput').value = label;
  document.querySelectorAll('.topic-tag').forEach(t => t.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('promptInput').focus();
}

// ── Double-click chip → generate immediately ──────────────
document.querySelectorAll('.topic-tag').forEach(tag => {
  tag.addEventListener('dblclick', () => {
    document.getElementById('generateForm').submit();
  });
});

// ── Form submit → loading state ───────────────────────────
document.getElementById('generateForm').addEventListener('submit', function() {
  const btn   = document.getElementById('genBtn');
  const icon  = document.getElementById('genIcon');
  const label = document.getElementById('genLabel');
  btn.disabled = true;
  icon.className  = 'fas fa-spinner fa-spin-custom';
  label.textContent = 'Generating…';
});

// ── Copy to clipboard ─────────────────────────────────────
function copyContent() {
  const el  = document.getElementById('resultContent');
  const btn = document.getElementById('copyBtn');
  if (!el) return;

  const text = el.innerText || el.textContent;
  navigator.clipboard.writeText(text).then(() => {
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    btn.classList.add('copied');
    setTimeout(() => {
      btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
      btn.classList.remove('copied');
    }, 2000);
  }).catch(() => {
    // Fallback for older browsers
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    btn.classList.add('copied');
    setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; btn.classList.remove('copied'); }, 2000);
  });
}

// ── Mobile sidebar ────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
document.getElementById('menuBtn')?.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
});
overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('open');
});
</script>
</body>
</html>