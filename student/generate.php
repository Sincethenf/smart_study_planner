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
        $apiKey = '';
        // AIzaSyDUWOAwZKeR13yq-UxH7M0W04mU9q0Nw0Q
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
        
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
$histQ = $conn->prepare("SELECT id, title, type, created_at FROM generated_content WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
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
html,body{height:100%;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;line-height:1.6;width:100%;max-width:100vw}
body{position:relative}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

/* ═══════════════════════════════════════════════
   SHELL + SIDEBAR
═══════════════════════════════════════════════ */
.shell{display:flex;min-height:100vh;width:100%;max-width:100vw;overflow-x:hidden}
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
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;width:100%;max-width:100%;overflow-x:hidden}
.topbar{height:var(--topbar-h);display:flex;align-items:center;padding:0 28px;gap:14px;background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;width:100%;max-width:100%}
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
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:22px;width:100%;max-width:100%;overflow-x:hidden}

/* Page header */
.page-header{display:flex;align-items:center;justify-content:space-between;animation:slideUp .4s var(--ease) both;flex-wrap:wrap;gap:10px;width:100%;max-width:100%}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--violet-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--violet);flex-shrink:0;box-shadow:0 0 20px rgba(139,92,246,.2)}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}
.notes-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:20px;background:var(--cyan-dim);color:var(--cyan);font-size:.82rem;font-weight:700;border:1px solid rgba(6,182,212,.2);cursor:pointer;transition:all .18s var(--ease);font-family:'Outfit',sans-serif}
.notes-btn:hover{background:var(--cyan);color:#fff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(6,182,212,.3)}
.notes-btn i{font-size:.85rem}
.gen-count-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:20px;background:var(--violet-dim);color:var(--violet);font-size:.72rem;font-weight:700;font-family:'JetBrains Mono',monospace;border:1px solid rgba(139,92,246,.18)}

/* ═══════════════════════════════════════════════
   TWO COLUMN LAYOUT
═══════════════════════════════════════════════ */
.gen-layout{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;width:100%;max-width:100%;box-sizing:border-box;overflow-x:hidden}
.gen-layout > *{min-width:0;max-width:100%;box-sizing:border-box}

/* ═══════════════════════════════════════════════
   PROMPT CARD
═══════════════════════════════════════════════ */
.prompt-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:26px 28px;
  animation:slideUp .4s var(--ease) .05s both;
  position:relative;overflow:hidden;
  max-width:100%;box-sizing:border-box;width:100%;
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
.input-row{display:flex;gap:10px;margin-bottom:16px;width:100%;max-width:100%}
.prompt-input{
  flex:1;padding:12px 16px;
  background:var(--bg3);border:1.5px solid var(--border);
  border-radius:var(--radius-sm);
  font-family:'Outfit',sans-serif;font-size:.9rem;color:var(--text);
  outline:none;transition:border-color .18s var(--ease),box-shadow .18s var(--ease);
  width:100%;max-width:100%;
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
.examples-row{margin-top:4px;width:100%;max-width:100%;overflow-x:hidden}
.examples-label{font-size:.72rem;color:var(--text3);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.tags-wrap{display:flex;flex-wrap:wrap;gap:8px;width:100%;max-width:100%}
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
  width:100%;max-width:100%;
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
  overflow-x:hidden;
  width:100%;
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
}
.history-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 18px;border-bottom:1px solid var(--border);
}
.history-title{font-size:.875rem;font-weight:700}
.history-count{font-size:.7rem;color:var(--text3);font-family:'JetBrains Mono',monospace;background:var(--bg3);padding:2px 8px;border-radius:10px}
.history-list{display:flex;flex-direction:column;max-height:195px;overflow-y:auto}
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

/* Large tablets and small desktops */
@media(max-width:1280px){
  .gen-layout{grid-template-columns:1fr 280px;gap:18px;padding:0 10px}
  .page{padding:24px}
}

/* Tablets */
@media(max-width:1100px){
  .gen-layout{grid-template-columns:1fr;gap:16px;padding:0 8px}
  .gen-layout > div{width:100%;max-width:100%;box-sizing:border-box}
  .gen-layout > div:last-child{position:static !important;max-width:100%}
  .page{padding:20px 18px}
}

/* Mobile Landscape */
@media(max-width:768px){
  .gen-layout{gap:14px;padding:0 6px}
  .prompt-card{padding:18px 20px}
}

/* Mobile Portrait */
@media(max-width:480px){
  .gen-layout{gap:12px;padding:0 4px}
  .prompt-card{padding:14px 16px}
}

/* Extra Small Mobile */
@media(max-width:360px){
  .gen-layout{gap:10px;padding:0 2px}
  .prompt-card{padding:12px 14px}
}

/* Mobile and tablets */
@media(max-width:768px){
  /* Sidebar */
  .sidebar{transform:translateX(-100%);width:var(--sidebar-w);max-width:80vw}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0;width:100vw;max-width:100vw}
  .hamburger{display:grid}
  
  /* Page layout */
  .page{padding:16px;gap:16px;width:100%;max-width:100vw;box-sizing:border-box}
  
  /* Topbar */
  .topbar{padding:0 16px;height:60px;width:100%;max-width:100vw;box-sizing:border-box}
  .topbar-title{font-size:.95rem}
  .topbar-sub{font-size:.7rem}
  .topbar-right{gap:8px}
  .icon-btn{width:34px;height:34px;font-size:.85rem}
  .user-pill{padding:4px 12px 4px 5px;gap:7px}
  .pill-avatar{width:26px;height:26px;font-size:.7rem}
  .pill-name{font-size:.78rem}
  
  /* Page header */
  .page-header{flex-wrap:wrap;gap:10px;width:100%;max-width:100%}
  .page-header-left{flex:1;min-width:200px}
  .page-icon{width:40px;height:40px;font-size:1rem}
  .page-title{font-size:1.1rem}
  .page-sub{font-size:.75rem}
  .notes-btn{font-size:.75rem;padding:6px 12px}
  .gen-count-chip{font-size:.7rem;padding:5px 11px}
  
  /* Prompt card */
  .prompt-card{padding:20px;width:100%;max-width:100%;box-sizing:border-box}
  .prompt-card-title{font-size:.9rem}
  .prompt-card-sub{font-size:.75rem;margin-bottom:16px}
  
  /* Input */
  .input-row{flex-direction:column;gap:10px;width:100%;max-width:100%}
  .prompt-input{font-size:.875rem;padding:11px 14px;width:100%;max-width:100%;box-sizing:border-box}
  .gen-btn{width:100%;justify-content:center;font-size:.875rem;max-width:100%;box-sizing:border-box}
  
  /* Tags */
  .examples-label{font-size:.7rem}
  .tags-wrap{gap:6px;width:100%;max-width:100%}
  .topic-tag{font-size:.75rem;padding:5px 11px;white-space:nowrap}
  
  /* Result card */
  .result-head{padding:16px;flex-wrap:wrap;gap:10px;width:100%;max-width:100%}
  .result-title{font-size:.875rem;width:100%;max-width:100%;word-break:break-word}
  .result-actions{width:100%;justify-content:flex-start;gap:8px;max-width:100%}
  .btn-copy,.btn-fav{font-size:.75rem;padding:6px 12px}
  .result-content{padding:18px;font-size:.8rem;line-height:1.75;max-height:400px;width:100%;max-width:100%;box-sizing:border-box}
  
  /* History & Tips */
  .history-card,.tips-card{margin-bottom:0}
  .history-list{max-height:200px}
}

/* Mobile portrait */
@media(max-width:480px){
  /* Page layout */
  .page{padding:12px;gap:14px;width:100%;max-width:100vw;box-sizing:border-box}
  
  /* Topbar */
  .topbar{padding:0 12px;height:56px;width:100%;max-width:100vw;box-sizing:border-box}
  .topbar-title{font-size:.9rem}
  .topbar-sub{display:none}
  .topbar-right{gap:6px}
  .icon-btn{width:32px;height:32px;font-size:.82rem}
  .notif-pip{width:14px;height:14px;font-size:.6rem;top:6px;right:6px}
  .user-pill{padding:3px 10px 3px 4px;gap:6px}
  .pill-avatar{width:24px;height:24px;font-size:.68rem}
  .pill-name{font-size:.75rem}
  
  /* Page header */
  .page-header{flex-direction:column;align-items:flex-start;gap:10px;width:100%;max-width:100%}
  .page-header-left{width:100%;gap:10px;max-width:100%}
  .page-icon{width:36px;height:36px;font-size:.95rem;border-radius:10px}
  .page-title{font-size:1rem}
  .page-sub{font-size:.72rem}
  .notes-btn{font-size:.72rem;padding:5px 10px;gap:5px}
  .notes-btn i{font-size:.75rem}
  .gen-count-chip{font-size:.68rem;padding:4px 10px;gap:5px}
  
  /* Prompt card */
  .prompt-card{padding:16px;border-radius:12px;width:100%;max-width:100%;box-sizing:border-box}
  .prompt-card::before{width:180px;height:180px;top:-40px;right:-40px}
  .prompt-card-title{font-size:.85rem;gap:6px}
  .prompt-card-title i{font-size:.85rem}
  .prompt-card-sub{font-size:.72rem;margin-bottom:14px}
  
  /* Input */
  .input-row{gap:8px;width:100%;max-width:100%}
  .prompt-input{font-size:.85rem;padding:10px 13px;border-radius:8px;width:100%;max-width:100%;box-sizing:border-box}
  .gen-btn{padding:10px 18px;font-size:.85rem;gap:6px;border-radius:8px;width:100%;max-width:100%;box-sizing:border-box}
  .gen-btn i{font-size:.85rem}
  
  /* Examples */
  .examples-row{margin-top:0;width:100%;max-width:100%;overflow-x:hidden}
  .examples-label{font-size:.68rem;margin-bottom:8px;gap:5px}
  .examples-label i{font-size:.68rem}
  .tags-wrap{gap:5px;width:100%;max-width:100%}
  .topic-tag{font-size:.7rem;padding:5px 9px;gap:4px;border-radius:16px;white-space:nowrap}
  .topic-tag i{font-size:.68rem}
  
  /* Alerts */
  .alert{padding:10px 14px;font-size:.8rem;border-radius:8px;gap:8px}
  .alert i{font-size:.82rem}
  
  /* Result card */
  .result-card{border-radius:12px;width:100%;max-width:100%}
  .result-head{padding:14px;gap:10px;width:100%;max-width:100%}
  .result-title{font-size:.82rem;gap:6px;width:100%;max-width:100%;word-break:break-word}
  .result-title i{font-size:.82rem}
  .result-topic{font-size:.82rem;word-break:break-word}
  .result-actions{gap:6px;width:100%;max-width:100%}
  .btn-copy,.btn-fav{font-size:.72rem;padding:6px 11px;gap:5px;border-radius:8px}
  .btn-copy i,.btn-fav i{font-size:.72rem}
  .result-content{padding:14px;font-size:.75rem;line-height:1.7;max-height:350px;width:100%;max-width:100%;box-sizing:border-box}
  
  /* History */
  .history-card{border-radius:12px}
  .history-head{padding:14px}
  .history-title{font-size:.82rem}
  .history-count{font-size:.68rem;padding:2px 7px}
  .history-list{max-height:180px}
  .history-item{padding:10px 14px;gap:8px}
  .history-icon{width:28px;height:28px;font-size:.72rem;border-radius:7px}
  .history-item-title{font-size:.78rem}
  .history-item-date{font-size:.66rem}
  .history-empty{padding:24px;font-size:.78rem}
  .history-empty i{font-size:1.3rem !important;margin-bottom:6px}
  
  /* Tips */
  .tips-card{padding:14px;border-radius:12px}
  .tips-title{font-size:.78rem;margin-bottom:8px;gap:6px}
  .tips-title i{font-size:.78rem}
  .tip-item{font-size:.73rem;margin-bottom:7px;gap:7px;line-height:1.5}
  .tip-dot{width:4px;height:4px;margin-top:6px}
}

/* Extra small mobile */
@media(max-width:360px){
  /* Page layout */
  .page{padding:10px;gap:12px;width:100%;max-width:100vw;box-sizing:border-box}
  
  /* Topbar */
  .topbar{padding:0 10px;height:54px;width:100%;max-width:100vw;box-sizing:border-box}
  .hamburger{width:32px;height:32px;font-size:.85rem;border-radius:8px}
  .topbar-title{font-size:.85rem}
  .icon-btn{width:30px;height:30px;font-size:.8rem;border-radius:8px}
  .user-pill{padding:3px 8px 3px 3px;gap:5px;border-radius:20px}
  .pill-avatar{width:22px;height:22px;font-size:.65rem}
  .pill-name{font-size:.72rem}
  
  /* Page header */
  .page-header{gap:8px}
  .page-header-left{gap:8px}
  .page-icon{width:34px;height:34px;font-size:.9rem;border-radius:9px}
  .page-title{font-size:.95rem}
  .page-sub{font-size:.7rem}
  .notes-btn{font-size:.7rem;padding:4px 9px;gap:4px;border-radius:16px}
  .notes-btn i{font-size:.72rem}
  .gen-count-chip{font-size:.65rem;padding:3px 9px;gap:4px;border-radius:16px}
  
  /* Prompt card */
  .prompt-card{padding:14px;border-radius:10px;width:100%;max-width:100%;box-sizing:border-box}
  .prompt-card::before{width:150px;height:150px;top:-30px;right:-30px}
  .prompt-card-title{font-size:.82rem;gap:5px}
  .prompt-card-title i{font-size:.82rem}
  .prompt-card-sub{font-size:.7rem;margin-bottom:12px}
  
  /* Input */
  .input-row{gap:8px;width:100%;max-width:100%}
  .prompt-input{font-size:.82rem;padding:9px 12px;border-radius:8px;width:100%;max-width:100%;box-sizing:border-box}
  .gen-btn{padding:9px 16px;font-size:.82rem;gap:5px;border-radius:8px;width:100%;max-width:100%;box-sizing:border-box}
  
  /* Examples */
  .examples-label{font-size:.66rem;margin-bottom:7px}
  .tags-wrap{gap:5px;width:100%;max-width:100%}
  .topic-tag{font-size:.68rem;padding:4px 8px;gap:4px;border-radius:14px;white-space:nowrap}
  .topic-tag i{font-size:.66rem}
  
  /* Alerts */
  .alert{padding:9px 12px;font-size:.78rem;gap:7px;border-radius:8px}
  .alert i{font-size:.8rem}
  
  /* Result card */
  .result-card{border-radius:10px;width:100%;max-width:100%}
  .result-head{padding:12px;gap:8px;width:100%;max-width:100%}
  .result-title{font-size:.8rem;gap:5px;width:100%;max-width:100%;word-break:break-word}
  .result-title i{font-size:.8rem}
  .result-actions{gap:5px;width:100%;max-width:100%}
  .btn-copy,.btn-fav{font-size:.7rem;padding:5px 10px;gap:4px;border-radius:7px}
  .result-content{padding:12px;font-size:.72rem;line-height:1.65;max-height:320px;width:100%;max-width:100%;box-sizing:border-box}
  
  /* History */
  .history-card{border-radius:10px}
  .history-head{padding:12px}
  .history-title{font-size:.8rem}
  .history-count{font-size:.65rem;padding:1px 6px}
  .history-list{max-height:160px}
  .history-item{padding:9px 12px;gap:7px}
  .history-icon{width:26px;height:26px;font-size:.7rem;border-radius:6px}
  .history-item-title{font-size:.75rem}
  .history-item-date{font-size:.64rem}
  .history-empty{padding:20px;font-size:.75rem}
  .history-empty i{font-size:1.2rem !important;margin-bottom:5px}
  
  /* Tips */
  .tips-card{padding:12px;border-radius:10px}
  .tips-title{font-size:.75rem;margin-bottom:7px;gap:5px}
  .tips-title i{font-size:.75rem}
  .tip-item{font-size:.7rem;margin-bottom:6px;gap:6px;line-height:1.45}
  .tip-dot{width:3px;height:3px;margin-top:5px}
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
      <button class="icon-btn" title="AI Assistant" style="background:linear-gradient(135deg,var(--violet),var(--blue));color:#fff;border:none">
        <i class="fas fa-robot"></i>
      </button>
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
      <button class="notes-btn" onclick="openNotesModal()">
        <i class="fas fa-note-sticky"></i> Notes
      </button>
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
      <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:calc(var(--topbar-h) + 26px)">

        <!-- History -->
        <div class="history-card">
          <div class="history-head">
            <div class="history-title">Recent Generations</div>
            <span class="history-count"><?php echo $totalGen; ?></span>
          </div>
          <div class="history-list" id="historyList">
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


<!-- Notes Modal -->
<div id="notesModal" class="notes-modal" style="display:none">
  <div class="notes-modal-overlay" onclick="closeNotesModal()"></div>
  <div class="notes-modal-content">
    <div class="notes-modal-header">
      <h3><i class="fas fa-note-sticky"></i> Paste Your Notes</h3>
      <button class="notes-modal-close" onclick="closeNotesModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="notes-modal-body">
      <textarea id="notesInput" placeholder="Paste your notes here..." rows="12"></textarea>
    </div>
    <div class="notes-modal-footer">
      <button class="notes-cancel-btn" onclick="closeNotesModal()">Cancel</button>
      <button class="notes-submit-btn" onclick="processNotes()">Process Notes</button>
    </div>
  </div>
</div>

<!-- Options Modal -->
<div id="optionsModal" class="notes-modal" style="display:none">
  <div class="notes-modal-overlay" onclick="closeOptionsModal()"></div>
  <div class="notes-modal-content options-modal-content">
    <div class="notes-modal-header">
      <h3><i class="fas fa-wand-magic-sparkles"></i> Choose Processing Option</h3>
      <button class="notes-modal-close" onclick="closeOptionsModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="notes-modal-body">
      <div class="options-grid">
        <button class="option-card" onclick="selectOption('quiz')">
          <i class="fas fa-list-check"></i>
          <h4>Multiple Choice</h4>
          <p>Generate quiz questions from your notes</p>
        </button>
        <button class="option-card" onclick="selectOption('essay')">
          <i class="fas fa-pen-to-square"></i>
          <h4>Essay</h4>
          <p>Create essay questions for deeper understanding</p>
        </button>
        <button class="option-card" onclick="selectOption('summarize')">
          <i class="fas fa-file-lines"></i>
          <h4>Summarize</h4>
          <p>Get a concise summary of your notes</p>
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.notes-modal{position:fixed;top:0;left:0;width:100%;height:100%;z-index:10000;display:flex;align-items:center;justify-content:center}
.notes-modal-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px)}
.notes-modal-content{position:relative;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);width:90%;max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.4);animation:modalSlideUp 0.3s ease;max-height:90vh;display:flex;flex-direction:column;color:var(--text)}
.options-modal-content{max-width:700px}
@keyframes modalSlideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.notes-modal-header{padding:24px 28px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface2)}
.notes-modal-header h3{margin:0;font-size:1.25rem;color:var(--text);display:flex;align-items:center;gap:10px}
.notes-modal-header i{color:var(--cyan)}
.notes-modal-close{background:none;border:none;font-size:1.5rem;color:var(--text3);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-sm);transition:all 0.2s}
.notes-modal-close:hover{background:#f3f4f6;color:#1f2937}
.notes-modal-body{padding:24px 28px;flex:1;overflow-y:auto}
#notesInput{width:100%;border:2px solid #e5e7eb;border-radius:12px;padding:16px;font-size:0.95rem;font-family:'Outfit',sans-serif;resize:vertical;min-height:200px;transition:border-color 0.2s;box-sizing:border-box}
#notesInput:focus{outline:none;border-color:var(--cyan)}
.notes-modal-footer{padding:20px 28px;border-top:1px solid #e5e7eb;display:flex;gap:12px;justify-content:flex-end}
.notes-cancel-btn,.notes-submit-btn{padding:10px 24px;border-radius:10px;font-size:0.9rem;font-weight:600;cursor:pointer;transition:all 0.2s;font-family:'Outfit',sans-serif;border:none}
.notes-cancel-btn{background:#f3f4f6;color:#6b7280}
.notes-cancel-btn:hover{background:#e5e7eb;color:#374151}
.notes-submit-btn{background:var(--cyan);color:#fff}
.notes-submit-btn:hover{background:#0891b2;transform:translateY(-1px);box-shadow:0 4px 12px rgba(6,182,212,0.3)}
.options-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.option-card{background:#fff;border:2px solid #e5e7eb;border-radius:12px;padding:24px 20px;text-align:center;cursor:pointer;transition:all 0.3s;display:flex;flex-direction:column;align-items:center;gap:12px}
.option-card:hover{border-color:var(--cyan);background:var(--cyan-dim);transform:translateY(-4px);box-shadow:0 8px 20px rgba(6,182,212,0.2)}
.option-card i{font-size:2.5rem;color:var(--cyan)}
.option-card h4{margin:0;font-size:1.1rem;color:#1f2937;font-weight:700}
.option-card p{margin:0;font-size:0.85rem;color:#6b7280;line-height:1.4}
.quiz-container,.essay-container,.summary-container{padding:10px 0}
.quiz-question,.essay-question{background:#f9fafb;border-radius:10px;padding:20px;margin-bottom:16px;border-left:4px solid transparent;transition:all 0.3s}
.quiz-question h4,.essay-question h4{margin:0 0 16px 0;color:#1f2937;font-size:1rem;line-height:1.6}
.quiz-options{display:flex;flex-direction:column;gap:10px}
.quiz-option{display:flex;align-items:center;gap:10px;padding:12px;background:#fff;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer;transition:all 0.2s}
.quiz-option:hover{border-color:var(--cyan);background:var(--cyan-dim)}
.quiz-option input{cursor:pointer}
.quiz-option span{flex:1;color:#374151;font-size:0.9rem}
.quiz-answer{margin-top:12px;padding:10px;background:#fef3c7;border-radius:6px;color:#92400e;font-weight:600;font-size:0.85rem}
.essay-points{color:#6b7280;font-size:0.85rem;margin:8px 0 0 0;line-height:1.5}
.summary-container p{color:#374151;line-height:1.8;font-size:0.95rem;margin:0}
@media(max-width:768px){
  .notes-modal-content{width:95%;max-width:none}
  .notes-modal-header,.notes-modal-body,.notes-modal-footer{padding:18px 20px}
  .notes-modal-header h3{font-size:1.1rem}
  #notesInput{min-height:180px;font-size:0.9rem}
  .options-grid{grid-template-columns:1fr;gap:12px}
  .option-card{padding:20px 18px}
  .option-card i{font-size:2rem}
}
@media(max-width:480px){
  .notes-modal-header,.notes-modal-body,.notes-modal-footer{padding:16px 18px}
  .notes-modal-header h3{font-size:1rem}
  #notesInput{min-height:160px;font-size:0.85rem;padding:12px}
  .notes-cancel-btn,.notes-submit-btn{padding:8px 18px;font-size:0.85rem}
  .option-card{padding:18px 16px}
  .option-card i{font-size:1.8rem}
  .option-card h4{font-size:1rem}
  .option-card p{font-size:0.8rem}
}
</style>

<script>
let storedNotes = '';

function openNotesModal() {
  document.getElementById('notesModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeNotesModal() {
  document.getElementById('notesModal').style.display = 'none';
  document.body.style.overflow = '';
}

function processNotes() {
  const notes = document.getElementById('notesInput').value.trim();
  if (!notes) {
    alert('Please paste your notes first');
    return;
  }
  storedNotes = notes;
  closeNotesModal();
  document.getElementById('optionsModal').style.display = 'flex';
}

function closeOptionsModal() {
  document.getElementById('optionsModal').style.display = 'none';
  document.body.style.overflow = '';
  // Don't clear storedNotes here - it will be cleared after sending
}

function selectOption(type) {
  console.log('Selected option:', type);
  console.log('Stored notes:', storedNotes);
  console.log('Stored notes length:', storedNotes.length);
  
  if (!storedNotes) {
    alert('Notes not found. Please try again.');
    return;
  }
  
  // Test alert to verify data before sending
  console.log('About to send - Type:', type, 'Notes length:', storedNotes.length);
  
  // Verify fetch URL
  const fetchUrl = 'test_api.php';
  console.log('Fetch URL:', fetchUrl);
  console.log('Current page:', window.location.href);
  
  closeOptionsModal();
  
  // Show loading
  const loadingHtml = `
    <div class="notes-modal" style="display:flex">
      <div class="notes-modal-overlay"></div>
      <div class="notes-modal-content" style="max-width:400px">
        <div class="notes-modal-body" style="text-align:center;padding:40px">
          <i class="fas fa-spinner fa-spin" style="font-size:3rem;color:var(--cyan);margin-bottom:20px"></i>
          <h3 style="margin:0;color:#1f2937">Generating...</h3>
          <p style="color:#6b7280;margin-top:10px">Please wait while AI processes your notes</p>
        </div>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', loadingHtml);
  
  // Create form data
  const formData = 'notes=' + encodeURIComponent(storedNotes) + '&type=' + encodeURIComponent(type);
  console.log('Sending data length:', formData.length);
  console.log('First 100 chars:', formData.substring(0, 100));
  
  // Send to backend
  fetch('process_notes_ai.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: formData
  })
  .then(res => {
    console.log('Response status:', res.status);
    return res.text();
  })
  .then(text => {
    console.log('Raw response:', text);
    return JSON.parse(text);
  })
  .then(data => {
    document.querySelector('.notes-modal:last-child').remove();
    
    console.log('Response:', data);
    
    if (data.success) {
      if (type === 'quiz') {
        displayQuiz(data.data);
      } else if (type === 'essay') {
        displayEssay(data.data);
      } else if (type === 'summarize') {
        displaySummary(data.data);
      }
    } else {
      alert('Error: ' + data.message);
    }
    
    document.getElementById('notesInput').value = '';
    storedNotes = '';
  })
  .catch(err => {
    console.error('Fetch error:', err);
    document.querySelector('.notes-modal:last-child').remove();
    alert('Error processing notes. Please try again.');
    console.error('Full error:', err);
  });
}

function displayQuiz(questions) {
  window.currentQuiz = questions; // Store for saving
  let html = '<div class="quiz-container">';
  questions.forEach((q, i) => {
    html += `
      <div class="quiz-question">
        <h4>${i + 1}. ${q.question}</h4>
        <div class="quiz-options">
          ${Object.entries(q.options).map(([key, val]) => `
            <label class="quiz-option">
              <input type="radio" name="q${i}" value="${key}">
              <span>${key}. ${val}</span>
            </label>
          `).join('')}
        </div>
        <div class="quiz-answer" style="display:none">Correct Answer: ${q.correct}</div>
      </div>
    `;
  });
  html += `
    <div style="display:flex;gap:12px;margin-top:20px">
      <button class="notes-submit-btn" onclick="checkQuizAnswers()">Check Answers</button>
      <button class="notes-cancel-btn" onclick="saveQuizToFavorites()" style="background:var(--cyan);color:#fff">
        <i class="fas fa-heart"></i> Save to Favorites
      </button>
    </div>
  </div>`;
  
  showResultModal('Multiple Choice Quiz', html);
}

function displayEssay(questions) {
  let html = '<div class="essay-container">';
  questions.forEach((q, i) => {
    html += `
      <div class="essay-question">
        <h4>${i + 1}. ${q.question}</h4>
        ${q.points ? `<p class="essay-points">${q.points}</p>` : ''}
      </div>
    `;
  });
  html += '</div>';
  
  showResultModal('Essay Questions', html);
}

function displaySummary(summary) {
  const html = `<div class="summary-container"><p>${summary.replace(/\n/g, '<br>')}</p></div>`;
  showResultModal('Summary', html);
}

function showResultModal(title, content) {
  const modal = `
    <div class="notes-modal" style="display:flex">
      <div class="notes-modal-overlay" onclick="this.parentElement.remove()"></div>
      <div class="notes-modal-content" style="max-width:800px">
        <div class="notes-modal-header">
          <h3><i class="fas fa-check-circle"></i> ${title}</h3>
          <button class="notes-modal-close" onclick="this.closest('.notes-modal').remove()">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="notes-modal-body" style="max-height:60vh;overflow-y:auto">
          ${content}
        </div>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modal);
}

function checkQuizAnswers() {
  const questions = document.querySelectorAll('.quiz-question');
  let score = 0;
  
  questions.forEach((q, i) => {
    const selected = q.querySelector('input[type="radio"]:checked');
    const answer = q.querySelector('.quiz-answer');
    const correct = answer.textContent.split(': ')[1];
    
    answer.style.display = 'block';
    
    if (selected && selected.value === correct) {
      score++;
      q.style.borderLeft = '4px solid #10b981';
    } else {
      q.style.borderLeft = '4px solid #ef4444';
    }
  });
  
  alert(`You scored ${score} out of ${questions.length}!`);
}

function saveQuizToFavorites() {
  if (!window.currentQuiz) {
    alert('No quiz to save');
    return;
  }
  
  console.log('Saving quiz:', window.currentQuiz);
  
  fetch('save_quiz_favorite.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      questions: window.currentQuiz
    })
  })
  .then(res => {
    console.log('Response status:', res.status);
    return res.text();
  })
  .then(text => {
    console.log('Raw response:', text);
    return JSON.parse(text);
  })
  .then(data => {
    console.log('Parsed data:', data);
    if (data.success) {
      alert('Quiz saved to favorites!');
    } else {
      alert('Error: ' + (data.message || 'Unknown error'));
      console.error('Error details:', data);
    }
  })
  .catch(err => {
    alert('Error saving quiz');
    console.error('Fetch error:', err);
  });
}
</script>

<?php include '../includes/ai_chat.php'; ?>

</body>
</html>