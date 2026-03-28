<!-- AI Chat Panel -->
<div class="ai-chat-panel" id="aiChatPanel">
  <div class="ai-chat-header">
    <div class="ai-chat-title">
      <i class="fas fa-robot"></i>
      <span>AI Assistant</span>
    </div>
    <button class="ai-chat-close" id="aiChatClose">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <div class="ai-chat-messages" id="aiChatMessages">
    <div class="ai-message">
      <div class="ai-avatar">
        <i class="fas fa-robot"></i>
      </div>
      <div class="ai-bubble">
        <p>Please Connect to Internet</p>
      </div>
    </div>
  </div>
  <div class="ai-chat-input">
    <textarea id="aiChatInput" placeholder="Ask me anything..." rows="1"></textarea>
    <button id="aiChatSend" class="ai-send-btn">
      <i class="fas fa-paper-plane"></i>
    </button>
  </div>
</div>

<style>
.ai-chat-panel {
  position: fixed;
  right: 20px;
  bottom: 20px;
  width: 380px;
  max-height: 600px;
  background: var(--surface);
  border: 1px solid var(--border-hi);
  border-radius: var(--radius);
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
  z-index: 1000;
  transform: translateY(calc(100% + 40px));
  opacity: 0;
  visibility: hidden;
  transition: all .3s var(--ease);
}
.ai-chat-panel.open {
  transform: translateY(0);
  opacity: 1;
  visibility: visible;
}
.ai-chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 18px;
  background: linear-gradient(135deg, var(--violet), var(--blue));
  border-radius: var(--radius) var(--radius) 0 0;
}
.ai-chat-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .95rem;
  font-weight: 700;
  color: #fff;
}
.ai-chat-close {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  border: none;
  color: #fff;
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: background .2s;
}
.ai-chat-close:hover {
  background: rgba(255,255,255,.25);
}
.ai-chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-height: 300px;
  max-height: 450px;
}
.ai-message, .user-message {
  display: flex;
  gap: 10px;
  animation: slideIn .3s var(--ease);
}
.user-message {
  flex-direction: row-reverse;
}
.ai-avatar, .user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  font-size: .85rem;
}
.ai-avatar {
  background: linear-gradient(135deg, var(--violet), var(--blue));
  color: #fff;
}
.user-avatar {
  background: linear-gradient(135deg, var(--blue), var(--violet));
  color: #fff;
  overflow: hidden;
}
.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ai-bubble, .user-bubble {
  max-width: 75%;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: .85rem;
  line-height: 1.5;
}
.ai-bubble {
  background: var(--bg3);
  color: var(--text);
  border-bottom-left-radius: 4px;
}
.user-bubble {
  background: linear-gradient(135deg, var(--blue), var(--violet));
  color: #fff;
  border-bottom-right-radius: 4px;
}
.ai-bubble p, .user-bubble p {
  margin: 0;
}
.ai-typing {
  display: flex;
  gap: 4px;
  padding: 8px 0;
}
.ai-typing span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--text3);
  animation: typing .8s infinite;
}
.ai-typing span:nth-child(2) { animation-delay: .15s; }
.ai-typing span:nth-child(3) { animation-delay: .3s; }
@keyframes typing {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-8px); }
}
.ai-chat-input {
  display: flex;
  gap: 10px;
  padding: 14px 18px;
  border-top: 1px solid var(--border);
  background: var(--bg3);
  border-radius: 0 0 var(--radius) var(--radius);
}
#aiChatInput {
  flex: 1;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  color: var(--text);
  font-family: 'Outfit', sans-serif;
  font-size: .85rem;
  resize: none;
  max-height: 100px;
  overflow-y: auto;
}
#aiChatInput:focus {
  outline: none;
  border-color: var(--blue);
}
.ai-send-btn {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-sm);
  background: linear-gradient(135deg, var(--violet), var(--blue));
  border: none;
  color: #fff;
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: transform .2s;
  flex-shrink: 0;
}
.ai-send-btn:hover {
  transform: scale(1.05);
}
.ai-send-btn:disabled {
  opacity: .5;
  cursor: not-allowed;
  transform: scale(1);
}
@keyframes slideIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
@media(max-width: 768px) {
  .ai-chat-panel {
    right: 10px;
    bottom: 10px;
    width: calc(100vw - 20px);
    max-width: 380px;
  }
}
</style>

<script>
(function() {
  const panel = document.getElementById('aiChatPanel');
  const closeBtn = document.getElementById('aiChatClose');
  const sendBtn = document.getElementById('aiChatSend');
  const input = document.getElementById('aiChatInput');
  const messages = document.getElementById('aiChatMessages');
  
  // Open chat when AI icon is clicked
  document.addEventListener('click', function(e) {
    if (e.target.closest('.icon-btn[title="AI Assistant"]')) {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) {
        input.focus();
      }
    }
  });
  
  // Close chat
  closeBtn.addEventListener('click', () => {
    panel.classList.remove('open');
  });
  
  // Auto-resize textarea
  input.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
  });
  
  // Send message
  function sendMessage() {
    const text = input.value.trim();
    if (!text) return;
    
    // Add user message
    const userMsg = document.createElement('div');
    userMsg.className = 'user-message';
    userMsg.innerHTML = `
      <div class="user-avatar">
        <?php if (!empty($_SESSION['profile_picture'])): ?>
          <img src="../assets/uploads/profiles/<?= htmlspecialchars($_SESSION['profile_picture']) ?>" alt="You">
        <?php else: ?>
          <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="user-bubble"><p>${escapeHtml(text)}</p></div>
    `;
    messages.appendChild(userMsg);
    messages.scrollTop = messages.scrollHeight;
    
    input.value = '';
    input.style.height = 'auto';
    sendBtn.disabled = true;
    
    // Show typing indicator
    const typing = document.createElement('div');
    typing.className = 'ai-message';
    typing.innerHTML = `
      <div class="ai-avatar"><i class="fas fa-robot"></i></div>
      <div class="ai-bubble"><div class="ai-typing"><span></span><span></span><span></span></div></div>
    `;
    messages.appendChild(typing);
    messages.scrollTop = messages.scrollHeight;
    
    // Send to API
    fetch('ai_chat_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    })
    .then(r => {
      console.log('Response status:', r.status);
      return r.json();
    })
    .then(data => {
      console.log('Response data:', data);
      typing.remove();
      
      const aiMsg = document.createElement('div');
      aiMsg.className = 'ai-message';
      aiMsg.innerHTML = `
        <div class="ai-avatar"><i class="fas fa-robot"></i></div>
        <div class="ai-bubble"><p>${escapeHtml(data.response || data.error || 'Sorry, I encountered an error.')}</p></div>
      `;
      messages.appendChild(aiMsg);
      messages.scrollTop = messages.scrollHeight;
      sendBtn.disabled = false;
    })
    .catch(err => {
      console.error('Fetch error:', err);
      typing.remove();
      const errMsg = document.createElement('div');
      errMsg.className = 'ai-message';
      errMsg.innerHTML = `
        <div class="ai-avatar"><i class="fas fa-robot"></i></div>
        <div class="ai-bubble"><p>Sorry, I'm having trouble connecting. Please try again. Error: ${err.message}</p></div>
      `;
      messages.appendChild(errMsg);
      messages.scrollTop = messages.scrollHeight;
      sendBtn.disabled = false;
    });
  }
  
  sendBtn.addEventListener('click', sendMessage);
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
  
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
})();
</script>
