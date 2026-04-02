const API = 'admin-api.php';

let currentFilter = 'all';
let currentConversationId = null;

document.addEventListener('DOMContentLoaded', () => {

  const messagesList = document.getElementById('messagesList');
  const messagesTitle = document.getElementById('messagesTitle');

  const anonymousLink = document.getElementById('anonymousLink');
  const nonAnonymousLink = document.getElementById('nonAnonymousLink');
  const allLink = document.getElementById('allLink');

  const replyInput = document.getElementById('replyInput');
  const sendReplyBtn = document.getElementById('sendReplyBtn');

  // Prevent refresh
  document.querySelectorAll('a[href="#"]').forEach(a => {
    a.addEventListener('click', e => e.preventDefault());
  });

  // Filters
  anonymousLink.onclick = () => {
    currentFilter = 'anonymous';
    loadMessages();
  };

  nonAnonymousLink.onclick = () => {
    currentFilter = 'nonanonymous';
    loadMessages();
  };

  allLink.onclick = () => {
    currentFilter = 'all';
    loadMessages();
  };

  // Load messages
  async function loadMessages() {
    const res = await fetch(`${API}?action=listConversations&filter=${currentFilter}`);
    const data = await res.json();

    if (!data.success) {
      messagesList.innerHTML = "No messages";
      return;
    }

    let html = '';

    data.messages.forEach(c => {
      html += `
        <div class="convoItem" data-id="${c.conversationId}">
          <strong>${c.senderName}</strong>
          <p>${c.lastPreview}</p>
        </div>
      `;
    });

    messagesList.innerHTML = html;

    document.querySelectorAll('.convoItem').forEach(item => {
      item.onclick = () => openConversation(item.dataset.id);
    });
  }

  // Open conversation
  async function openConversation(id) {
    currentConversationId = id;

    const res = await fetch(`${API}?action=getConversation&conversationId=${id}`);
    const data = await res.json();

    let html = '';
    data.thread.forEach(m => {
      html += `<p><b>${m.senderName}</b>: ${m.message}</p>`;
    });

    document.getElementById('originalMessage').innerHTML = html;

    document.getElementById('replyModal').style.display = 'flex';
  }

  // Send reply
  sendReplyBtn.onclick = async () => {
    const reply = replyInput.value.trim();
    if (!reply) return;

    const fd = new FormData();
    fd.append('action', 'sendMessage');
    fd.append('conversationId', currentConversationId);
    fd.append('reply', reply);

    await fetch(API, { method: 'POST', body: fd });

    replyInput.value = '';
    loadMessages();
  };

  loadMessages();
});

// Modal close
function closeProfile() {
  document.getElementById('profileModal').style.display = 'none';
}

