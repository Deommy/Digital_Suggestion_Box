<?php
require_once 'config.php';
requireAdmin(); // ✅ admin only

$conn = getDBConnection();
$userName = $_SESSION['full_name'] ?? 'Administrator';
$userEmail = $_SESSION['email'] ?? '';

if ($conn && empty($userEmail)) {
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid > 0) {
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("i", $uid);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $userName = $row['full_name'] ?? $userName;
        $userEmail = $row['email'] ?? $userEmail;
      }
      $stmt->close();
    }
  }
  $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Digital Suggestion Box</title>
  <link rel="stylesheet" href="styles.css" />
  
 <style>

  .date-filter{
  display: flex;
  align-items: center;
  padding: 8px 14px;

  border-radius: 999px; /* 🔥 super rounded (pill) */
  background: #f8fafc;

  border: 1px solid rgba(0,0,0,0.04);
  box-shadow: 0 2px 6px rgba(0,0,0,0.03);

  transition: all 0.2s ease;
}

/* subtle hover lang */
.date-filter:hover{
  background: #f1f5f9;
}

/* INPUT */
#dateFilter{
  border: none;
  outline: none;
  background: transparent;

  font-size: 13px;
  font-weight: 500;
  color: #334155;

  cursor: pointer;
}

/* ICON (calendar sa right) */
#dateFilter::-webkit-calendar-picker-indicator{
  opacity: 0.5;
  cursor: pointer;
  transition: 0.2s;
}

#dateFilter::-webkit-calendar-picker-indicator:hover{
  opacity: 0.8;
}
    .messages-header{
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-right{
      display: flex;
      align-items: center;
      gap: 6px;
    }

    #dateFilter{
      padding: 6px 10px;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 13px;
    }

    #clearDate{
      border: none;
      background: #eee;
      padding: 6px 8px;
      border-radius: 6px;
      cursor: pointer;
    }
  </style>


</head>

<body>

  <!-- Mobile Menu Toggle -->
  <button class="mobile-menu-toggle" id="mobileMenuToggle" type="button">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
      <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
    </svg>
  </button>

  <div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">

      <div class="sidebar-header">
        <div class="sidebar-brand">
          <div class="sidebar-title">Digital Suggestion Box</div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <div class="sidebar-section">
          <div class="sidebar-section-title">Analytics</div>

          <div class="sidebar-item">
            <a href="#" class="sidebar-link" id="anonymousLink">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
              </svg>
              <span>Anonymous</span>
              <span class="badge" id="anonymousCount" style="margin-left:auto;">0</span>
            </a>
          </div>

          <div class="sidebar-item">
            <a href="#" class="sidebar-link" id="nonAnonymousLink">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
              </svg>
              <span>Non-Anonymous</span>
              <span class="badge" id="nonAnonymousCount" style="margin-left:auto;">0</span>
            </a>
          </div>

          <div class="sidebar-item">
            <a href="#" class="sidebar-link" id="allLink">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"/>
              </svg>
              <span>All Messages</span>
            </a>
          </div>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Account</div>

          <div class="sidebar-item">
            <a href="#" class="sidebar-link" id="profileLink">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
              </svg>
              <span>Administrator</span>
            </a>
          </div>

          <div class="sidebar-item">
            <a href="logout.php" class="sidebar-link" id="logoutLink">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
              </svg>
              <span>Logout</span>
            </a>
          </div>
        </div>

      </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <div class="main-body">
        <div class="messages-container">
          <div class="messages-header">

  <div class="header-left">
    <h3 id="messagesTitle">All Messages</h3>
  </div>

  <div class="date-filter">
  <input type="date" id="dateFilter">
</div>

</div>

          <div class="messages-list" id="messagesList"></div>
        </div>
      </div>
    </main>

  </div>

  <!-- PROFILE MODAL -->
  <div id="profileModal"
       style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div id="profileModalCard" style="
background:white;
padding:1.8rem;
border-radius:14px;
width:360px;
max-width:90%;
text-align:center;
transform:scale(.9);
opacity:0;
transition:all .25s ease;
border-top:4px solid var(--primary);
">
      <h3 style="margin-bottom:1rem;">Administrator</h3>
      <p><strong>Name:</strong> <?php echo htmlspecialchars($userName); ?></p>
      <p><strong>Email:</strong> <?php echo htmlspecialchars($userEmail); ?></p>
      <p><strong>Role:</strong> Administrator</p>
      <button class="btn btn-primary" style="margin-top:1rem;"
onclick="
const m=document.getElementById('profileModal');
const c=document.getElementById('profileModalCard');
c.style.transform='scale(.9)';
c.style.opacity='0';
setTimeout(()=>{m.style.display='none';},200);
">
Close
</button>
    </div>
  </div>

  <!-- REPLY MODAL -->
  <div id="replyModal"
style="
display:none;
position:fixed;
inset:0;
background:rgba(0,0,0,0.55);
z-index:1001;
align-items:center;
justify-content:center;
backdrop-filter: blur(4px);
">
    <div id="replyModalCard"
style="
background:white;
padding:2rem;
border-radius:1rem;
width:900px;
max-width:95%;
max-height:85vh;
overflow:hidden;
display:flex;
flex-direction:column;
transform:scale(0.9);
opacity:0;
transition:all .25s ease;
">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
<h3 id="replyTitle">Conversation</h3>

<button onclick="
const m=document.getElementById('replyModal');
const c=document.getElementById('replyModalCard');
c.style.transform='scale(0.9)';
c.style.opacity='0';
setTimeout(()=>{m.style.display='none';},200);
"
style="
background:none;
border:none;
font-size:20px;
cursor:pointer;
color:#555;
">
✕
</button>

</div>

      <div id="originalMessage" style="
flex:1;
background:#f7f8fb;
padding:1rem;
border-radius:12px;
margin-bottom:1rem;
overflow-y:auto;
display:flex;
flex-direction:column;
gap:8px;
"></div>

      <textarea id="replyInput" class="form-input" rows="4" placeholder="Type your reply..." style="width:100%;"></textarea>

      <div style="display:flex; justify-content:flex-end; margin-top:1rem;">
<button class="btn btn-primary" id="sendReplyBtn" type="button">Send Reply</button>
</div>
    </div>
  </div>

  <script>
  // ✅ Use relative path (same folder)
  const API = 'admin-api.php';

  let currentFilter = 'all';            // all | anonymous | nonanonymous
  let currentConversationId = null;     // selected conversation
  let selectedDate = ''; // ✅ ADD THIS

  document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');

    const profileLink = document.getElementById('profileLink');
    const profileModal = document.getElementById('profileModal');

    const replyModal = document.getElementById('replyModal');
    const originalMessage = document.getElementById('originalMessage');
    const replyInput = document.getElementById('replyInput');
    const sendReplyBtn = document.getElementById('sendReplyBtn');

    const anonymousLink = document.getElementById('anonymousLink');
    const nonAnonymousLink = document.getElementById('nonAnonymousLink');
    const allLink = document.getElementById('allLink');
    const messagesTitle = document.getElementById('messagesTitle');
    const messagesList = document.getElementById('messagesList');
    const replyTitle = document.getElementById('replyTitle');
    const dateFilterInput = document.getElementById('dateFilter');

    // Prevent refresh for href="#"
    document.querySelectorAll('a.sidebar-link[href="#"]').forEach(a => {
      a.addEventListener('click', (e) => e.preventDefault());
    });

    // Mobile menu toggle
    if (mobileMenuToggle && sidebar) {
      mobileMenuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('show');
      });
    }

    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 768 && sidebar && mobileMenuToggle) {
        if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
          sidebar.classList.remove('show');
        }
      }
    });

    // Profile modal
    if (profileLink && profileModal) {
      profileLink.addEventListener('click', (e) => {
  e.preventDefault();
  profileModal.style.display = 'flex';

  setTimeout(()=>{
    const card = document.getElementById("profileModalCard");
    if(card){
      card.style.transform="scale(1)";
      card.style.opacity="1";
    }
  },10);
});
      profileModal.addEventListener('click', (e) => {
        if (e.target === profileModal) profileModal.style.display = 'none';
      });
    }

    // Reply modal close by clicking backdrop
    if (replyModal) {
      replyModal.addEventListener('click', (e) => {
        if (e.target === replyModal) replyModal.style.display = 'none';
      });
    }

    // Filters
    if (anonymousLink) {
      anonymousLink.addEventListener('click', () => {
        currentFilter = (currentFilter === 'anonymous') ? 'all' : 'anonymous';
        updateFilterUI();
        loadMessages();
      });
    }

    if (nonAnonymousLink) {
      nonAnonymousLink.addEventListener('click', () => {
        currentFilter = (currentFilter === 'nonanonymous') ? 'all' : 'nonanonymous';
        updateFilterUI();
        loadMessages();
      });
    }

    if (allLink) {
      allLink.addEventListener('click', () => {
        currentFilter = 'all';
        updateFilterUI();
        loadMessages();
      });
    }

    // ✅ DATE FILTER
if(dateFilterInput){
  dateFilterInput.addEventListener('change', () => {
    selectedDate = dateFilterInput.value;
    loadMessages();
  });
}

    function updateFilterUI() {
      if (anonymousLink) anonymousLink.classList.toggle('active', currentFilter === 'anonymous');
      if (nonAnonymousLink) nonAnonymousLink.classList.toggle('active', currentFilter === 'nonanonymous');
      if (allLink) allLink.classList.toggle('active', currentFilter === 'all');

      if (!messagesTitle) return;
      if (currentFilter === 'all') messagesTitle.textContent = 'All Messages';
      else if (currentFilter === 'anonymous') messagesTitle.textContent = 'Anonymous Messages';
      else messagesTitle.textContent = 'Non-Anonymous Messages';
    }

    // ✅ Load analytics counts
    async function loadAnalytics() {
      try {
        const res = await fetch(`${API}?action=getAnalytics`);
        const data = await res.json();

        if (data.success && data.analytics) {
          const a = document.getElementById('anonymousCount');
          const n = document.getElementById('nonAnonymousCount');
          if (a) a.textContent = data.analytics.anonymous ?? 0;
          if (n) n.textContent = data.analytics.nonAnonymous ?? 0;
        }
      } catch (err) {
        console.error('Failed to load analytics:', err);
      }
    }

    // ✅ Load conversations list
    async function loadMessages() {
      if (!messagesList) return;

      try {
        const url = `${API}?action=listConversations
&filter=${encodeURIComponent(currentFilter)}
&date=${encodeURIComponent(selectedDate)}`;
        const res = await fetch(url);
        const text = await res.text();

        let data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          console.error('Admin API did not return JSON. RAW:', text);
          messagesList.innerHTML = `
            <div class="empty-state">
              <h4 class="empty-title">Error loading messages</h4>
              <p class="empty-description">Check Console (F12). The API returned non-JSON output.</p>
            </div>
          `;
          return;
        }

        if (!data.success) {
          console.warn('API returned success:false', data);
          messagesList.innerHTML = `
            <div class="empty-state">
              <h4 class="empty-title">No messages found</h4>
              <p class="empty-description">${escapeHtml(data.message || 'No conversations')}</p>
            </div>
          `;
          return;
        }

        displayMessages(Array.isArray(data.messages) ? data.messages : []);
      } catch (err) {
        console.error('Failed to load messages:', err);
        messagesList.innerHTML = `
          <div class="empty-state">
            <h4 class="empty-title">Error loading messages</h4>
            <p class="empty-description">Network/server error. Check Console.</p>
          </div>
        `;
      }
    }

    // ✅ Open conversation (fetch thread) and show in reply modal
    async function openConversation(conversationId, openModal = true) {
      try {
        const res = await fetch(`${API}?action=getConversation&conversationId=${conversationId}`);
        const data = await res.json();

        if (!data.success) {
          alert(data.message || 'Failed to open conversation');
          return;
        }

        if (replyTitle && data.conversation) {
  replyTitle.textContent = data.conversation.senderName;
}

        currentConversationId = conversationId;

        const thread = Array.isArray(data.thread) ? data.thread : [];
        let html = '';

        thread.forEach(m => {
          const isAdmin = (m.senderRole === 'admin');
          const isDeleted = (Number(m.isDeleted || 0) === 1);

// If deleted, always show muted style
const bubbleBg = isDeleted ? 'rgba(0,0,0,0.06)' : (isAdmin ? 'var(--primary)' : '#ffffff');
const bubbleColor = isDeleted ? 'var(--text-muted)' : (isAdmin ? 'white' : 'var(--text-primary)');

          const align = isAdmin ? 'flex-end' : 'flex-start';

          html += `
<div style="display:flex; flex-direction:column; align-items:${align}; margin:12px 0; width:100%;">

<div style="max-width:420px; text-align:${isAdmin ? 'right' : 'left'};">

<div style="font-size:0.8rem; font-weight:600; margin-bottom:4px;">
${escapeHtml(m.senderName || (isAdmin ? 'Admin' : 'Student'))}
</div>

<div style="
padding:12px 14px;
border-radius:14px;
background:${bubbleBg};
color:${bubbleColor};
box-shadow:0 1px 2px rgba(0,0,0,0.08);
display:inline-block;
margin-bottom:4px;
max-width:100%;
">
${m.isDeleted
? '<span style="color:var(--text-muted); font-style:italic;">This message was removed by the student.</span>'
: escapeHtml(m.message || '')
}
</div>

<div style="font-size:0.7rem; color:var(--text-muted);">
${m.createdAt ? formatTimestamp(m.createdAt) : ''}
</div>

${m.isEdited ? `
<div style="font-size:0.65rem; color:var(--text-muted); font-style:italic;">
edited
</div>
` : ''}

</div>

</div>
`;
        });

        if (originalMessage) {

  originalMessage.innerHTML =
    html || '<div style="color:var(--text-muted);">No messages yet.</div>';

  // auto scroll to newest message
  setTimeout(() => {
    originalMessage.scrollTop = originalMessage.scrollHeight;
  }, 50);

}

        if (openModal && replyModal) {
          replyModal.style.display = 'flex';

setTimeout(()=>{
  const card = document.getElementById("replyModalCard");
  if(card){
    card.style.transform = "scale(1)";
    card.style.opacity = "1";
  }
},10);
          if (replyInput) {
            replyInput.value = '';
            replyInput.focus();
          }
        }
      } catch (err) {
        console.error(err);
        alert('Error opening conversation');
      }
    }

    // ✅ Render conversations list (NO duplicate functions; clean)
    function displayMessages(convos) {
      if (!messagesList) return;

      if (!Array.isArray(convos) || convos.length === 0) {
        messagesList.innerHTML = `
          <div class="empty-state">
            <h4 class="empty-title">No messages found</h4>
            <p class="empty-description">
              ${currentFilter === 'anonymous' ? 'No anonymous messages yet.' :
                currentFilter === 'nonanonymous' ? 'No non-anonymous messages yet.' :
                'Waiting for students to send messages.'}
            </p>
          </div>
        `;
        return;
      }

      let html = '';

      convos.forEach(c => {
        const preview = (c.lastPreview || '').trim() || '(No messages yet)';
        const messageCount = c.messageCount || 0;
        const when = c.createdAt ? formatTimestamp(c.createdAt) : '';
        const subtitle = c.isAnonymous
          ? ''
          : `<div style="font-size:0.8125rem; color:var(--text-muted);">${escapeHtml(c.email || '')}</div>`;

        html += `
          <div class="message convoItem"
               data-conversation-id="${Number(c.conversationId)}"
               style="padding:1.5rem; border-bottom:1px solid var(--border-light); margin:0; cursor:pointer;">
            <div style="display:flex; gap:1rem;">
              <div class="message-avatar">${getInitials(c.senderName || 'User')}</div>

              <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:0.5rem;">
                  <div>
                    <div style="font-weight:600; color:var(--text-primary);">
                      ${escapeHtml(c.senderName || 'Unknown')}
                    </div>
                    ${subtitle}
                  </div>
                  <div style="font-size:0.75rem; color:var(--text-muted);">${when}</div>
                </div>

                <div style="background:var(--bg-tertiary); padding:1rem; border-radius:0.5rem;">
                  <p style="margin:0; color:var(--text-primary);">
                    ${escapeHtml(preview)}
                  </p>
                  <div style="margin-top:0.35rem; font-size:0.75rem; color:var(--text-muted); display:flex; justify-content:space-between;">
<span>Last sender: ${escapeHtml(c.lastSender || '')}</span>
<span>${messageCount} messages</span>
</div>
                </div>
              </div>
            </div>
          </div>
        `;
      });

      messagesList.innerHTML = html;

      messagesList.querySelectorAll('.convoItem').forEach(item => {
        item.addEventListener('click', () => {
          const id = Number(item.dataset.conversationId);
          if (id) openConversation(id, true);
        });
      });
    }

    // ✅ Send reply (POST to admin-api.php action=sendMessage)
    if (sendReplyBtn) {
      sendReplyBtn.addEventListener('click', async () => {
        const reply = replyInput ? replyInput.value.trim() : '';
        if (!reply) return;

        if (!currentConversationId) {
          alert('Walang naka-open na conversation.');
          return;
        }

        sendReplyBtn.disabled = true;
        const oldText = sendReplyBtn.textContent;
        sendReplyBtn.textContent = 'Sending...';

        const fd = new FormData();
        fd.append('action', 'sendMessage');
        fd.append('conversationId', currentConversationId);
        fd.append('reply', reply);

        try {
          const res = await fetch(API, { method: 'POST', body: fd });
          const data = await res.json();

          if (!data.success) {
            alert('Failed to send reply: ' + (data.message || 'Unknown error'));
            return;
          }

          if (replyInput) replyInput.value = '';

          // Refresh modal thread + list + analytics
          await openConversation(currentConversationId, true);
          await loadMessages();
          await loadAnalytics();

        } catch (err) {
          console.error(err);
          alert('An error occurred. Please try again.');
        } finally {
          sendReplyBtn.disabled = false;
          sendReplyBtn.textContent = oldText;
        }
      });
    }

    function getInitials(name) {
      return String(name)
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2) || 'U';
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text ?? '';
      return div.innerHTML;
    }

    function formatTimestamp(timestamp) {
      const d = new Date(timestamp);
      if (isNaN(d.getTime())) return '';
      const time = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
      const date = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      return `${time} • ${date}`;
    }

    // ✅ Initial load
    updateFilterUI();
    loadAnalytics();
    loadMessages();

  });
</script>
</body>
</html>
