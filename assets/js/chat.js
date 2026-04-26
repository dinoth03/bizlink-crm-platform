/*BIZLINK CRM CHAT — chat.js*/

/*DATA*/
const ME = { id:'me', name:'Your Name', initials:'YN', role:'user', color:'#000080' };

const CONTACTS = [
  { id:'c1',  name:'Amara Perera',       initials:'AP', role:'customer', color:'#FF8C00', status:'online',  company:'—',                    phone:'+94 77 234 5678', email:'amara@gmail.com',       province:'Western',  joined:'Jan 2024' },
  { id:'c2',  name:'Lanka Tech Hub',     initials:'LT', role:'vendor',   color:'#50C878', status:'online',  company:'Lanka Tech Hub PVT',   phone:'+94 11 456 7890', email:'sales@lankatechhub.lk', province:'Western',  joined:'Mar 2023' },
  { id:'c3',  name:'Suresh Nimal',       initials:'SN', role:'customer', color:'#FF8C00', status:'away',    company:'—',                    phone:'+94 71 345 6789', email:'suresh@gmail.com',      province:'Central',  joined:'Feb 2024' },
  { id:'c4',  name:'Kandy Weaves',       initials:'KW', role:'vendor',   color:'#50C878', status:'offline', company:'Kandy Weaves PVT',     phone:'+94 81 567 8901', email:'hello@kandyweaves.lk',  province:'Central',  joined:'Dec 2022' },
  { id:'c5',  name:'Nuwara Tea Estate',  initials:'NT', role:'vendor',   color:'#50C878', status:'online',  company:'Nuwara Tea Estate Ltd', phone:'+94 52 678 9012', email:'info@nuwaratea.lk',     province:'Central',  joined:'Jun 2023' },
  { id:'c6',  name:'Dilani Bandara',     initials:'DB', role:'customer', color:'#FF8C00', status:'online',  company:'—',                    phone:'+94 76 456 7890', email:'dilani@gmail.com',       province:'Western',  joined:'Nov 2022' },
  { id:'c7',  name:'Support Team',       initials:'ST', role:'admin',    color:'#000080', status:'online',  company:'BizLink Admin',        phone:'+94 11 000 0000', email:'support@bizlink.lk',    province:'Western',  joined:'2022' },
  { id:'c8',  name:'GreenFarm SL',       initials:'GF', role:'vendor',   color:'#50C878', status:'away',    company:'GreenFarm SL PVT',     phone:'+94 41 234 5678', email:'info@greenfarm.lk',     province:'Southern', joined:'Apr 2023' },
  { id:'c9',  name:'Priya Fernando',     initials:'PF', role:'customer', color:'#FF8C00', status:'offline', company:'—',                    phone:'+94 72 890 1234', email:'priya@yahoo.com',        province:'Southern', joined:'Mar 2023' },
  { id:'c10', name:'Siddhalepa Wellness',initials:'SW', role:'vendor',   color:'#50C878', status:'online',  company:'Siddhalepa Wellness',  phone:'+94 11 111 2222', email:'info@siddhalepa.lk',    province:'Western',  joined:'Jan 2023' },
];

const CONVERSATIONS = [
  {
    id:'conv1', contactId:'c2', pinned:true, muted:false, unread:2,
    messages:[
      { id:'m1', from:'c2',  text:'Hello! We wanted to check if you received our updated product catalog.',        time:'10:02 AM', date:'Today',     status:'read' },
      { id:'m2', from:'me',  text:'Yes, we received it! The new electronics range looks really impressive.',         time:'10:05 AM', date:'Today',     status:'read' },
      { id:'m3', from:'c2',  text:'Great! We have 8 new laptop models this quarter. Should I send a detailed spec sheet?', time:'10:07 AM', date:'Today', status:'read' },
      { id:'m4', from:'me',  text:'Please do. Also, can you confirm the bulk pricing for 10+ units?',               time:'10:09 AM', date:'Today',     status:'read' },
      { id:'m5', from:'c2',  text:'Absolutely. For 10+ units we offer 12% off and free island-wide shipping. I\'ll send the price list shortly.', time:'10:12 AM', date:'Today', status:'read' },
      { id:'m6', type:'file', from:'c2', fileName:'PriceList_Q4_2024.pdf', fileSize:'1.2 MB', time:'10:13 AM', date:'Today', status:'read' },
      { id:'m7', from:'me',  text:'Perfect. We\'ll review this and get back to you by end of day.',                 time:'10:15 AM', date:'Today',     status:'delivered' },
      { id:'m8', from:'c2',  text:'Sounds good! Also, we\'d like to explore being a featured vendor on the marketplace.', time:'10:18 AM', date:'Today', status:'read' },
      { id:'m9', from:'c2',  text:'Who should we contact for the featured listing?',                                time:'10:18 AM', date:'Today',     status:'unread' },
    ],
    quickReplies:['I\'ll check and get back','Please send the spec sheet','Let\'s schedule a call'],
  },
  {
    id:'conv2', contactId:'c1', pinned:false, muted:false, unread:1,
    messages:[
      { id:'m1', from:'c1',  text:'Hi! I placed an order yesterday (#BL-9941) but haven\'t received a confirmation email yet.', time:'9:30 AM', date:'Today', status:'read' },
      { id:'m2', from:'me',  text:'Hi Amara! I\'m checking that right now for you.',                               time:'9:32 AM', date:'Today',     status:'read' },
      { id:'m3', from:'me',  text:'I can see your order is confirmed and processing. The email may have gone to spam — could you check there?', time:'9:33 AM', date:'Today', status:'read' },
      { id:'m4', from:'c1',  text:'Oh found it in spam! Thank you so much 😊',                                     time:'9:35 AM', date:'Today',     status:'read' },
      { id:'m5', from:'me',  text:'Happy to help! Your order will ship within 1–2 business days.',                 time:'9:36 AM', date:'Today',     status:'delivered' },
      { id:'m6', from:'c1',  text:'Wonderful. Also, do you have the Galaxy Pro in Silver?',                        time:'9:40 AM', date:'Today',     status:'unread' },
    ],
    quickReplies:['Yes, we have it in stock','Let me check availability','I\'ll confirm shortly'],
  },
  {
    id:'conv3', contactId:'c5', pinned:false, muted:false, unread:0,
    messages:[
      { id:'m1', from:'c5',  text:'Good afternoon! We\'d like to discuss our Q4 tea export listing on BizLink.', time:'2:00 PM', date:'Yesterday', status:'read' },
      { id:'m2', from:'me',  text:'Good afternoon! Of course, I\'d be happy to help you with that.',              time:'2:05 PM', date:'Yesterday', status:'read' },
      { id:'m3', from:'c5',  text:'We have 3 premium grades — BOPF, Pekoe, and OP. Can all three be listed separately?', time:'2:08 PM', date:'Yesterday', status:'read' },
      { id:'m4', from:'me',  text:'Absolutely. Each grade can have its own listing with separate pricing and inventory.', time:'2:10 PM', date:'Yesterday', status:'read' },
      { id:'m5', type:'image', from:'c5', imageLabel:'Tea Grade Samples', time:'2:12 PM', date:'Yesterday', status:'read' },
      { id:'m6', from:'c5',  text:'Here are our grade samples. Beautiful product, right?',                         time:'2:13 PM', date:'Yesterday', status:'read' },
      { id:'m7', from:'me',  text:'They look wonderful! Ceylon tea is a flagship product on BizLink. I\'ll set up your listings today.', time:'2:15 PM', date:'Yesterday', status:'read' },
    ],
    quickReplies:['I\'ll set up the listings','Can you send product photos?','What\'s your pricing?'],
  },
  {
    id:'conv4', contactId:'c7', pinned:false, muted:false, unread:0,
    messages:[
      { id:'m1', type:'system', text:'Support conversation started', time:'8:00 AM', date:'Yesterday' },
      { id:'m2', from:'c7',  text:'Hi Kasun, this is the BizLink support team. We\'ve updated the vendor approval workflow as requested.', time:'8:02 AM', date:'Yesterday', status:'read' },
      { id:'m3', from:'me',  text:'Thank you! Has the 48-hour auto-approval feature been enabled?',                time:'8:05 AM', date:'Yesterday', status:'read' },
      { id:'m4', from:'c7',  text:'Yes, that\'s live now. Vendors who pass initial verification will be auto-approved after 48 hours.', time:'8:10 AM', date:'Yesterday', status:'read' },
      { id:'m5', from:'me',  text:'Perfect. Also, can we add email alerts for flagged accounts?',                  time:'8:12 AM', date:'Yesterday', status:'read' },
      { id:'m6', from:'c7',  text:'That\'s already on our sprint. Should be ready by end of next week.',          time:'8:15 AM', date:'Yesterday', status:'read' },
    ],
    quickReplies:['Sounds great!','What\'s the timeline?','Please send an update'],
  },
  {
    id:'conv5', contactId:'c3', pinned:false, muted:true, unread:0,
    messages:[
      { id:'m1', from:'c3',  text:'Hello, I need help with my return request for order #BL-9840.',                time:'3:20 PM', date:'Mon',       status:'read' },
      { id:'m2', from:'me',  text:'Hi Suresh! I can see your order. What\'s the reason for the return?',         time:'3:25 PM', date:'Mon',       status:'read' },
      { id:'m3', from:'c3',  text:'The item arrived damaged. The packaging was torn.',                             time:'3:27 PM', date:'Mon',       status:'read' },
      { id:'m4', from:'me',  text:'I\'m sorry to hear that. I\'ve initiated a replacement order at no cost. You\'ll receive a confirmation email shortly.', time:'3:30 PM', date:'Mon', status:'read' },
      { id:'m5', from:'c3',  text:'Thank you so much! Great service.',                                            time:'3:32 PM', date:'Mon',       status:'read' },
    ],
    quickReplies:['How can I help further?','Your case has been resolved','Please rate your experience'],
  },
  {
    id:'conv6', contactId:'c6', pinned:false, muted:false, unread:0,
    messages:[
      { id:'m1', from:'c6',  text:'Hi! I wanted to ask about the new loyalty program for customers.',             time:'11:00 AM', date:'Mon',      status:'read' },
      { id:'m2', from:'me',  text:'Hi Dilani! The loyalty program launches next month. Customers earn points on every purchase.', time:'11:05 AM', date:'Mon', status:'read' },
      { id:'m3', from:'c6',  text:'That sounds amazing! Will existing customers be enrolled automatically?',      time:'11:08 AM', date:'Mon',      status:'read' },
      { id:'m4', from:'me',  text:'Yes, all registered customers will be enrolled automatically.',                 time:'11:10 AM', date:'Mon',      status:'read' },
    ],
    quickReplies:['You\'ll be notified via email','Check your account settings','Yes, automatically enrolled'],
  },
];

const EMOJI_LIST = ['😊','😃','😄','😁','🥰','😍','🤩','😎','🤗','👍','✅','🙏','💯','🔥','⭐','💼','📦','🚀','💰','🎉','❤️','👋','🤝','📱','💻','📊','🇱🇰','🍵','🌿','⚡'];

const TEMPLATES = [
  { title:'Order Confirmed',      body:'Your order has been confirmed and will be dispatched within 1–2 business days. Thank you for shopping with BizLink! 🎉' },
  { title:'Welcome to BizLink',   body:'Welcome to BizLink! We\'re excited to have you on board. Feel free to explore our marketplace and reach out if you need any assistance.' },
  { title:'Follow-up',            body:'Just following up on our previous conversation. Please let me know if you have any further questions or need assistance.' },
  { title:'Issue Resolved',       body:'Your issue has been successfully resolved. If you experience any further problems, please don\'t hesitate to contact us.' },
  { title:'Vendor Approved',      body:'Congratulations! Your vendor account has been approved. You can now list products on the BizLink marketplace. Welcome aboard! 🏪' },
  { title:'Delivery Update',      body:'Your order is on its way! Estimated delivery is within 2–4 business days. Track your order via the BizLink app.' },
];

/*STATE*/
let state = {
  activeConvId: null,
  filter: 'all',
  search: '',
  infoPanelOpen: false,
  emojiPickerOpen: false,
  templatePanelOpen: false,
  msgSearchOpen: false,
  moreMenuOpen: false,
  callActive: false,
  callTimer: null,
  callSeconds: 0,
  typingTimeout: null,
  pinned: new Set(['conv1']),
  muted: new Set(['conv5']),
};

let pendingChatRole = null;
let pendingTargetUserId = null;

let ME_USER_ID = null;
let isGuestMode = false;

function renderCurrentUserBadge() {
  const ruName = document.querySelector('.ru-name');
  const ruRole = document.querySelector('.ru-role');
  const ruAvatar = document.querySelector('.ru-avatar');

  if (ruName) ruName.textContent = ME.name;
  if (ruRole) ruRole.textContent = capitalize(ME.role || 'user');
  if (ruAvatar) {
    ruAvatar.textContent = ME.initials || 'YN';
    ruAvatar.style.background = ME.color || '#000080';
  }
}

function applyChatIdentity(user = {}) {
  const fullName = String(user.full_name || user.name || ME.name || 'Your Name').trim() || 'Your Name';
  const role = String(user.role || ME.role || 'user').trim().toLowerCase() || 'user';

  ME.name = fullName;
  ME.initials = getInitials(fullName);
  ME.role = role;
  renderCurrentUserBadge();
}

function enableGuestMode(reason = '') {
  if (!isGuestMode) {
    showToast('Guest chat mode enabled. Sign in to sync messages.', 'info');
  }

  isGuestMode = true;
  ME_USER_ID = null;
  ME.name = 'Guest User';
  ME.initials = 'GU';
  ME.role = 'guest';
  ME.color = '#6b7280';
  renderCurrentUserBadge();

  if (reason) {
    console.info('Guest mode reason:', reason);
  }
}

function getInitials(name) {
  return (name || '')
    .split(' ')
    .filter(Boolean)
    .map((p) => p[0].toUpperCase())
    .join('')
    .slice(0, 2) || 'ME';
}

async function loadChatDataFromApi() {
  try {
    const response = await fetch('../api/chat_data.php');
    const payload = await response.json();

    if (response.status === 401 || response.status === 429) {
      enableGuestMode('auth_api_unavailable');
      return false;
    }

    if (!payload.success) {
      return false;
    }

    isGuestMode = false;
    ME_USER_ID = Number(payload.current_user.id);
    applyChatIdentity(payload.current_user);

    CONTACTS.splice(0, CONTACTS.length, ...(payload.contacts || []));
    CONVERSATIONS.splice(0, CONVERSATIONS.length, ...(payload.conversations || []));

    state.pinned = new Set();
    state.muted = new Set();

    return true;
  } catch (error) {
    console.error('Chat API load failed:', error);
    return false;
  }
}

async function bootstrapChatIdentity() {
  if (typeof authMe !== 'function') {
    renderCurrentUserBadge();
    return false;
  }

  try {
    const identity = await authMe(false);
    if (identity && identity.user) {
      ME_USER_ID = Number(identity.user.user_id || 0) || ME_USER_ID;
      applyChatIdentity(identity.user);
      return true;
    }
  } catch (error) {
    console.error('Failed to load authenticated chat identity:', error);
  }

  renderCurrentUserBadge();
  return false;
}

function conversationDbId(convId) {
  if (!convId) return 0;
  if (convId.startsWith('conv')) {
    return Number(convId.replace('conv', '')) || 0;
  }
  return 0;
}

/*INIT*/
document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const chatRoleParam = urlParams.get('chatRole');
  if (chatRoleParam) {
    const normalizedRole = chatRoleParam.toLowerCase();
    if (['admin', 'vendor'].includes(normalizedRole)) {
      pendingChatRole = normalizedRole;
    }
  }

  const targetUserIdParam = Number(urlParams.get('targetUserId') || 0);
  if (targetUserIdParam > 0) {
    pendingTargetUserId = targetUserIdParam;
  }

  await bootstrapChatIdentity();
  await loadChatDataFromApi();

  renderConvoList();
  renderEmojiPicker();
  renderTemplates();
  renderContactGrid(CONTACTS);
  updateUnreadCount();

  applyRoleFilterFromUrl();

  let handledInitialChatAction = false;

  if (pendingTargetUserId) {
    const targetContact = CONTACTS.find((contact) => Number(contact.userId || 0) === Number(pendingTargetUserId));
    if (targetContact) {
      startNewChat(targetContact.id);
      pendingTargetUserId = null;
      handledInitialChatAction = true;
    } else {
      openNewChat('vendor');
      showToast('Select a vendor to start chatting.', 'info');
      pendingTargetUserId = null;
      handledInitialChatAction = true;
    }
  } else if (pendingChatRole) {
    openNewChat(pendingChatRole);
    handledInitialChatAction = true;
  }

  // Auto-open first conversation
  if (!handledInitialChatAction && CONVERSATIONS.length > 0) {
    setTimeout(() => openConversation(CONVERSATIONS[0].id), 200);
  }

  document.addEventListener('click', handleGlobalClick);
  document.addEventListener('keydown', handleGlobalKey);
});

/*CONVERSATION LIST*/
function renderConvoList(filter = state.filter, search = state.search) {
  const list = document.getElementById('convoList');
  const query = String(search || '').toLowerCase().trim();

  // 1. Filter existing conversations
  let convos = CONVERSATIONS.filter(c => {
    const contact = getContact(c.contactId);
    if (query) {
      const searchText = [
        contact.name,
        contact.company,
        contact.owner_name,
        contact.email
      ].join(' ').toLowerCase();
      if (!searchText.includes(query)) return false;
    }
    if (filter === 'unread') return c.unread > 0;
    if (filter === 'vendors')   return contact.role === 'vendor';
    if (filter === 'customers') return contact.role === 'customer';
    if (filter === 'admin')     return contact.role === 'admin';
    return true;
  });

  // Sort: pinned first
  convos = [...convos].sort((a,b) => {
    const ap = state.pinned.has(a.id) ? 1 : 0;
    const bp = state.pinned.has(b.id) ? 1 : 0;
    return bp - ap;
  });

  let html = convos.map((c, i) => renderConvoItem(c, i)).join('');

  // 2. If searching, also find matching contacts who don't have a conversation yet
  if (query) {
    const matchingContacts = CONTACTS.filter(contact => {
      // Exclude if already in a conversation
      if (CONVERSATIONS.some(c => c.contactId === contact.id)) return false;

      // Filter by role if a filter is active
      if (filter === 'vendors' && contact.role !== 'vendor') return false;
      if (filter === 'customers' && contact.role !== 'customer') return false;
      if (filter === 'admin' && contact.role !== 'admin') return false;
      if (filter === 'unread') return false;

      const searchText = [
        contact.name,
        contact.company,
        contact.owner_name,
        contact.email
      ].join(' ').toLowerCase();
      return searchText.includes(query);
    });

    if (matchingContacts.length > 0) {
      html += `<div style="padding:12px 20px; font-size:0.75rem; color:var(--t3); text-transform:uppercase; letter-spacing:0.05em; border-top:1px solid rgba(255,255,255,0.05); margin-top:8px;">New Contacts</div>`;
      html += matchingContacts.map((c, i) => renderNewContactItem(c, i + convos.length)).join('');
    }
  }

  if (convos.length === 0 && (!query || (query && html === ''))) {
    list.innerHTML = `<div style="padding:40px 20px;text-align:center;color:var(--t3);font-size:.82rem;">No conversations found</div>`;
    return;
  }

  list.innerHTML = html;
}

function renderNewContactItem(contact, i) {
  const delay = i * 0.04;
  return `
    <div class="convo-item potential"
      onclick="startNewChat('${contact.id}')"
      style="animation-delay:${delay}s">
      <div class="ci-avatar">
        <div class="ci-avi" style="background:${contact.color}">${contact.initials}</div>
        <div class="ci-badge badge-${contact.status}"></div>
      </div>
      <div class="ci-content">
        <div class="ci-row1">
          <span class="ci-name">${contact.name}</span>
        </div>
        <div class="ci-row2">
          <span class="ci-preview">Start a new conversation</span>
        </div>
      </div>
    </div>`;
}

function renderConvoItem(conv, i) {
  const contact = getContact(conv.contactId);
  const lastMsg = conv.messages[conv.messages.length - 1];
  const preview = getPreview(lastMsg);
  const isActive = state.activeConvId === conv.id;
  const isPinned = state.pinned.has(conv.id);
  const isMuted  = state.muted.has(conv.id);
  const delay    = i * 0.04;

  return `
    <div class="convo-item${isActive ? ' active' : ''}${conv.unread > 0 ? ' unread' : ''}"
      onclick="openConversation('${conv.id}')"
      style="animation-delay:${delay}s">
      <div class="ci-avatar">
        <div class="ci-avi" style="background:${contact.color}">${contact.initials}</div>
        <div class="ci-badge badge-${contact.status}"></div>
      </div>
      <div class="ci-content">
        <div class="ci-row1">
          <span class="ci-name">${contact.name}</span>
          <span class="ci-time">${lastMsg.time || ''}</span>
        </div>
        <div class="ci-row2">
          <span class="ci-preview">${preview}</span>
          ${conv.unread > 0 ? `<span class="ci-unread-dot">${conv.unread}</span>` : ''}
          ${isPinned ? `<span class="ci-pinned" title="Pinned">📌</span>` : ''}
          ${isMuted  ? `<span class="ci-muted"  title="Muted">🔇</span>` : ''}
        </div>
      </div>
    </div>`;
}

function getPreview(msg) {
  if (!msg) return '';
  if (msg.type === 'file')   return `📎 ${msg.fileName}`;
  if (msg.type === 'image')  return `📷 Photo`;
  if (msg.type === 'system') return `ℹ️ ${msg.text}`;
  const prefix = msg.from === 'me' ? 'You: ' : '';
  const text   = msg.text || '';
  return prefix + (text.length > 46 ? text.slice(0, 46) + '…' : text);
}

/*OPEN CONVERSATION*/
function openConversation(convId) {
  const conv    = CONVERSATIONS.find(c => c.id === convId);
  if (!conv) return;
  const contact = getContact(conv.contactId);

  state.activeConvId = convId;
  conv.unread = 0;

  // Show chat window, hide empty state
  document.getElementById('emptyState').classList.add('hidden');
  document.getElementById('chatWindow').classList.remove('hidden');

  // Update header
  renderChatHeader(contact);

  // Render messages
  renderMessages(conv);

  // Quick replies
  renderQuickReplies(conv.quickReplies || []);

  // Update rail
  renderConvoList();

  // Info panel
  if (state.infoPanelOpen) renderInfoPanel(contact);

  // Scroll to bottom
  scrollBottom(false);

  // Simulate typing after a moment
  simulateTyping(contact, conv);

  updateUnreadCount();
}

function renderChatHeader(contact) {
  // Avatar
  const avi = document.getElementById('chAvatar');
  avi.innerHTML = `
    <div class="ch-avatar" style="background:${contact.color}">${contact.initials}</div>
    <div class="ch-status-ring badge-${contact.status}"></div>`;

  document.getElementById('chName').textContent = contact.name;
  const metaMap = { online:'Online', away:'Away · Last seen recently', offline:'Offline' };
  const roleLabel = contact.role === 'vendor' ? 'Verified Vendor' : contact.role === 'customer' ? 'Customer' : 'Admin Team';
  document.getElementById('chMeta').innerHTML =
    `${roleLabel} &nbsp;·&nbsp; <span class="${contact.status === 'online' ? 'online-txt' : ''}">${metaMap[contact.status]}</span>`;
}

/*RENDER MESSAGES*/
function renderMessages(conv) {
  const inner = document.getElementById('messagesInner');
  let html = '';
  let lastDate = '';

  conv.messages.forEach((msg, i) => {
    // Date separator
    if (msg.date && msg.date !== lastDate) {
      html += `<div class="date-sep"><div class="date-sep-line"></div><span class="date-sep-label">${msg.date}</span><div class="date-sep-line"></div></div>`;
      lastDate = msg.date;
    }

    if (msg.type === 'system') {
      html += `<div class="msg-row system-msg"><div class="system-bubble">ℹ️ ${msg.text}</div></div>`;
      return;
    }

    const isMe    = msg.from === 'me';
    const contact = conv ? getContact(conv.contactId) : null;
    const showAvi = !isMe && (i === 0 || conv.messages[i-1].from !== msg.from);
    const showSenderName = isMe ? (i === 0 || conv.messages[i-1].from !== msg.from) : showAvi;
    const delay   = Math.min(i * 0.03, 0.4);

    const aviHtml = !isMe
      ? `<div class="msg-avatar${showAvi ? '' : ' hidden-avi'}" style="background:${contact?.color || '#50C878'}">${contact?.initials || '?'}</div>`
      : '';

    const senderName = isMe ? ME.name : contact?.name || '';
    const senderHtml = showSenderName ? `<div class="msg-sender-name">${senderName}</div>` : '';

    let bubbleContent = '';
    if (msg.type === 'file') {
      bubbleContent = `<div class="msg-file" onclick="downloadFile('${msg.fileName}')">
        <div class="mf-icon">📄</div>
        <div class="mf-info"><strong>${msg.fileName}</strong><span>${msg.fileSize}</span></div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--t3);flex-shrink:0"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </div>`;
    } else if (msg.type === 'image') {
      bubbleContent = `<div class="msg-image" onclick="viewImage('${msg.imageLabel}')">
        <div class="msg-image-placeholder">🖼️</div>
        <span style="font-size:.72rem;color:var(--t3)">${msg.imageLabel} · Image</span>
      </div>`;
    } else {
      bubbleContent = `
        <div class="reaction-bar">
          ${['❤️','😂','👍','😮','😢','🔥'].map(e => `<span class="rb-emoji" onclick="reactTo('${msg.id}','${e}',event)">${e}</span>`).join('')}
        </div>
        ${msg.text}`;
    }

    const statusIcon = isMe ? getStatusIcon(msg.status) : '';

    html += `
      <div class="msg-row ${isMe ? 'outgoing' : 'incoming'}" style="animation-delay:${delay}s">
        ${aviHtml}
        <div class="msg-bubble-wrap">
          ${senderHtml}
          <div class="msg-bubble" id="msg-${msg.id}">${bubbleContent}</div>
          <div class="msg-meta">
            <span class="msg-time">${msg.time}</span>
            ${statusIcon}
          </div>
        </div>
      </div>`;
  });

  inner.innerHTML = html;
}

function getStatusIcon(status) {
  const icons = {
    sending:   `<span class="msg-status">○</span>`,
    sent:      `<span class="msg-status">✓</span>`,
    delivered: `<span class="msg-status">✓✓</span>`,
    read:      `<span class="msg-status read">✓✓</span>`,
    unread:    `<span class="msg-status">✓</span>`,
  };
  return icons[status] || '';
}

function renderQuickReplies(replies) {
  const el = document.getElementById('quickReplies');
  el.innerHTML = replies.map(r =>
    `<button class="qr-chip" onclick="useQuickReply('${r.replace(/'/g,"\\'")}')">💬 ${r}</button>`
  ).join('');
}

/*SEND MESSAGE*/
function sendMessage() {
  const input = document.getElementById('composeInput');
  const text  = input.innerText.trim();
  if (!text || !state.activeConvId) return;

  const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
  if (!conv) return;

  const msg = {
    id: 'msg_' + Date.now(),
    from: 'me',
    text,
    time: getCurrentTime(),
    date: 'Today',
    status: 'sending',
  };

  conv.messages.push(msg);
  input.innerHTML = '';
  updateSendBtn('');
  updateCharCount('');

  // Append message immediately
  appendMessage(msg, conv);
  scrollBottom(true);
  renderConvoList();

  // Persist to DB when chat API mode is active
  const dbConversationId = conversationDbId(state.activeConvId);
  if (ME_USER_ID && dbConversationId > 0) {
    fetch('../api/chat_send_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conversation_id: dbConversationId,
        sender_id: ME_USER_ID,
        message_content: text
      })
    })
      .then(async (res) => ({ status: res.status, payload: await res.json() }))
      .then(({ status, payload }) => {
        if (status === 401 || status === 429) {
          enableGuestMode('send_message_requires_auth');
          msg.status = 'sent';
          updateMsgStatus(msg.id, 'sent');
          return;
        }

        if (payload.success) {
          msg.status = 'delivered';
          updateMsgStatus(msg.id, 'delivered');
        } else {
          msg.status = 'sent';
          updateMsgStatus(msg.id, 'sent');
        }
      })
      .catch(() => {
        msg.status = 'sent';
        updateMsgStatus(msg.id, 'sent');
      });
    return;
  }

  // Fallback behavior when API mode is not active
  setTimeout(() => { msg.status = 'sent';      updateMsgStatus(msg.id, 'sent'); }, 600);
  setTimeout(() => { msg.status = 'delivered'; updateMsgStatus(msg.id, 'delivered'); }, 1400);
  setTimeout(() => { msg.status = 'read';      updateMsgStatus(msg.id, 'read'); }, 2800);
  if (Math.random() > 0.35) {
    setTimeout(() => simulateReply(conv), 2500 + Math.random() * 3000);
  }
}

function appendMessage(msg, conv) {
  const inner  = document.getElementById('messagesInner');
  const isMe   = msg.from === 'me';
  const contact = getContact(conv.contactId);

  let bubbleContent = '';
  if (msg.type === 'file') {
    bubbleContent = `<div class="msg-file"><div class="mf-icon">📄</div><div class="mf-info"><strong>${msg.fileName}</strong><span>${msg.fileSize}</span></div></div>`;
  } else {
    bubbleContent = `
      <div class="reaction-bar">
        ${['❤️','😂','👍','😮','😢','🔥'].map(e => `<span class="rb-emoji" onclick="reactTo('${msg.id}','${e}',event)">${e}</span>`).join('')}
      </div>
      ${msg.text}`;
  }

  const el = document.createElement('div');
  el.className = `msg-row ${isMe ? 'outgoing' : 'incoming'}`;
  el.id = `row-${msg.id}`;
  el.innerHTML = `
    ${!isMe ? `<div class="msg-avatar" style="background:${contact.color}">${contact.initials}</div>` : ''}
    <div class="msg-bubble-wrap">
      <div class="msg-bubble" id="msg-${msg.id}">${bubbleContent}</div>
      <div class="msg-meta">
        <span class="msg-time">${msg.time}</span>
        ${isMe ? getStatusIcon(msg.status) : ''}
      </div>
    </div>`;
  inner.appendChild(el);
}

function updateMsgStatus(msgId, status) {
  const row = document.getElementById(`row-${msgId}`);
  if (!row) return;
  const meta = row.querySelector('.msg-meta');
  if (!meta) return;
  const statusEl = meta.querySelector('.msg-status');
  if (statusEl) statusEl.remove();
  meta.insertAdjacentHTML('beforeend', getStatusIcon(status));
}

/*SIMULATE TYPING + REPLY*/
function simulateTyping(contact, conv) {
  if (contact.status === 'offline') return;
  if (Math.random() > 0.5) return;

  setTimeout(() => {
    if (state.activeConvId !== conv.id) return;
    showTyping(contact);
    clearTimeout(state.typingTimeout);
    state.typingTimeout = setTimeout(() => hideTyping(), 3000 + Math.random() * 2000);
  }, 1500 + Math.random() * 2000);
}

function simulateReply(conv) {
  const contact = getContact(conv.contactId);
  if (contact.status === 'offline') return;

  const replies = [
    'Thank you for the update! We\'ll look into this right away.',
    'Understood. We\'ll process this as soon as possible.',
    'Great! Let\'s move forward with this.',
    'Could you share more details about that?',
    'I\'ll check with our team and get back to you shortly.',
    'Perfect, that works for us! 🙏',
    'We appreciate the quick response!',
    'Can you send us the invoice for this order?',
  ];

  showTyping(contact);

  setTimeout(() => {
    hideTyping();
    if (state.activeConvId !== conv.id) return;

    const msg = {
      id: 'msg_' + Date.now(),
      from: conv.contactId,
      text: replies[Math.floor(Math.random() * replies.length)],
      time: getCurrentTime(),
      date: 'Today',
      status: 'read',
    };
    conv.messages.push(msg);
    appendMessage(msg, conv);
    scrollBottom(true);
    renderConvoList();
  }, 2000 + Math.random() * 2000);
}

function showTyping(contact) {
  const ti = document.getElementById('typingIndicator');
  const av = document.getElementById('tiAvatar');
  const lb = document.getElementById('tiLabel');
  av.style.background = contact.color;
  av.textContent = contact.initials;
  lb.textContent = `${contact.name} is typing…`;
  ti.classList.remove('hidden');
  scrollBottom(true);
}
function hideTyping() {
  document.getElementById('typingIndicator')?.classList.add('hidden');
}

/*COMPOSE INPUT*/
function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function handleInput(el) {
  const text = el.innerText.trim();
  updateSendBtn(text);
  updateCharCount(text);
  // Close pickers on input
  if (state.emojiPickerOpen) toggleEmojiPicker();
}

function updateSendBtn(text) {
  const btn = document.getElementById('sendBtn');
  btn.disabled = !text;
}

function updateCharCount(text) {
  const el = document.getElementById('charCount');
  const len = text.length;
  if (len > 400) {
    el.textContent = `${len}/500`;
    el.classList.add('warn');
  } else {
    el.textContent = len > 80 ? `${len}` : '';
    el.classList.remove('warn');
  }
}

function useQuickReply(text) {
  const input = document.getElementById('composeInput');
  input.innerText = text;
  updateSendBtn(text);
  input.focus();
  // Cursor to end
  const range = document.createRange();
  range.selectNodeContents(input);
  range.collapse(false);
  window.getSelection().removeAllRanges();
  window.getSelection().addRange(range);
}

/*EMOJI PICKER*/
function renderEmojiPicker() {
  document.getElementById('epGrid').innerHTML = EMOJI_LIST.map(e =>
    `<div class="ep-emoji" onclick="insertEmoji('${e}')">${e}</div>`
  ).join('');
}

function toggleEmojiPicker() {
  state.emojiPickerOpen = !state.emojiPickerOpen;
  document.getElementById('emojiPicker').classList.toggle('hidden', !state.emojiPickerOpen);
  if (state.emojiPickerOpen && state.templatePanelOpen) closeTemplates();
}

function insertEmoji(emoji) {
  const input = document.getElementById('composeInput');
  input.focus();
  document.execCommand('insertText', false, emoji);
  updateSendBtn(input.innerText.trim());
}

/*TEMPLATES*/
function renderTemplates() {
  document.getElementById('tpList').innerHTML = TEMPLATES.map(t => `
    <div class="tp-item" onclick="useTemplate('${t.body.replace(/'/g,"\\'")}')">
      <strong>${t.title}</strong>
      <span>${t.body.slice(0,70)}…</span>
    </div>`).join('');
}

function insertTemplate() {
  state.templatePanelOpen = !state.templatePanelOpen;
  document.getElementById('templatePanel').classList.toggle('hidden', !state.templatePanelOpen);
  if (state.templatePanelOpen && state.emojiPickerOpen) toggleEmojiPicker();
}

function closeTemplates() {
  state.templatePanelOpen = false;
  document.getElementById('templatePanel').classList.add('hidden');
}

function useTemplate(text) {
  const input = document.getElementById('composeInput');
  input.innerText = text;
  updateSendBtn(text);
  closeTemplates();
  input.focus();
}

/*FILE / IMAGE ATTACH*/
function attachFile() {
  if (!state.activeConvId) { showToast('Open a conversation first', 'warn'); return; }
  const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
  const names = ['Invoice_Q4.pdf','Catalog_2024.pdf','PriceList.xlsx','Report.pdf','Contract.pdf'];
  const sizes = ['0.8 MB','1.4 MB','2.1 MB','0.5 MB','1.9 MB'];
  const idx   = Math.floor(Math.random() * names.length);
  const msg = {
    id: 'msg_' + Date.now(), from:'me', type:'file',
    fileName: names[idx], fileSize: sizes[idx],
    time: getCurrentTime(), date:'Today', status:'sent',
  };
  conv.messages.push(msg);
  appendMessage(msg, conv);
  scrollBottom(true);
  renderConvoList();
  showToast(`📎 ${msg.fileName} sent`, 'success');
}

function attachImage() {
  if (!state.activeConvId) { showToast('Open a conversation first', 'warn'); return; }
  const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
  const labels = ['Product Photo','Sample Image','Screenshot','Business Photo'];
  const msg = {
    id: 'msg_' + Date.now(), from:'me', type:'image',
    imageLabel: labels[Math.floor(Math.random() * labels.length)],
    time: getCurrentTime(), date:'Today', status:'sent',
  };
  conv.messages.push(msg);
  appendMessage(msg, conv);
  scrollBottom(true);
  showToast('📷 Image sent', 'success');
}

function downloadFile(name) { showToast(`📥 Downloading ${name}…`, 'info'); }
function viewImage(label)   { showToast(`🖼️ Opening ${label}…`, 'info'); }

/*REACTIONS*/
function reactTo(msgId, emoji, e) {
  e.stopPropagation();
  showToast(`${emoji} Reaction added`, 'info');
}

/*INFO PANEL*/
function toggleInfoPanel() {
  state.infoPanelOpen = !state.infoPanelOpen;
  const panel = document.getElementById('infoPanel');
  panel.classList.toggle('hidden', !state.infoPanelOpen);
  document.getElementById('infoPanelBtn').classList.toggle('active', state.infoPanelOpen);

  if (state.infoPanelOpen && state.activeConvId) {
    const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
    if (conv) renderInfoPanel(getContact(conv.contactId));
  }
}

function renderInfoPanel(contact) {
  const el = document.getElementById('ipBody');
  const statusMap = { online:'🟢 Online', away:'🟡 Away', offline:'⚪ Offline' };
  el.innerHTML = `
    <div class="ip-profile">
      <div class="ip-avatar" style="background:${contact.color}">${contact.initials}</div>
      <div class="ip-name">${contact.name}</div>
      <div class="ip-tag">${contact.role === 'vendor' ? '🏪 Verified Vendor' : contact.role === 'customer' ? '👤 Customer' : '⚙️ Admin'}</div>
      <div class="ip-status-row">${statusMap[contact.status]}</div>
    </div>

    <div class="ip-section">
      <div class="ip-section-title">Contact Details</div>
      <div class="ip-field"><label>Phone</label><span>${contact.phone}</span></div>
      <div class="ip-field"><label>Email</label><a href="mailto:${contact.email}">${contact.email}</a></div>
      <div class="ip-field"><label>Province</label><span>${contact.province}</span></div>
      <div class="ip-field"><label>Member since</label><span>${contact.joined}</span></div>
      ${contact.company !== '—' ? `<div class="ip-field"><label>Company</label><span>${contact.company}</span></div>` : ''}
    </div>

    <div class="ip-section">
      <div class="ip-section-title">Actions</div>
      <div class="ip-actions">
        <button class="ip-action-btn primary" onclick="toggleCallMenu()">📞 Voice Call</button>
        <button class="ip-action-btn" onclick="toggleVideoCall()">📹 Video Call</button>
        <button class="ip-action-btn" onclick="muteConvo()">🔇 Mute Chat</button>
        <button class="ip-action-btn" onclick="pinConvo()">📌 Pin Chat</button>
        <button class="ip-action-btn" onclick="blockContact()" style="color:var(--customer)">🚫 Block</button>
      </div>
    </div>

    <div class="ip-section">
      <div class="ip-section-title">Shared Media (3)</div>
      <div class="ip-media-grid">
        <div class="ip-media-thumb">🖼️</div>
        <div class="ip-media-thumb">📄</div>
        <div class="ip-media-thumb">🖼️</div>
      </div>
    </div>`;
}

/*MORE MENU*/
function toggleMoreMenu(btn) {
  const menu = document.getElementById('moreMenu');
  state.moreMenuOpen = !state.moreMenuOpen;
  menu.classList.toggle('open', state.moreMenuOpen);
}

function clearChat() {
  if (!state.activeConvId) return;
  const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
  conv.messages = [];
  document.getElementById('messagesInner').innerHTML = '';
  closeMoreMenu();
  showToast('Chat cleared', 'info');
}
function muteConvo() {
  if (!state.activeConvId) return;
  if (state.muted.has(state.activeConvId)) {
    state.muted.delete(state.activeConvId);
    showToast('🔔 Notifications enabled', 'info');
  } else {
    state.muted.add(state.activeConvId);
    showToast('🔇 Conversation muted', 'info');
  }
  closeMoreMenu();
  renderConvoList();
}
function pinConvo() {
  if (!state.activeConvId) return;
  if (state.pinned.has(state.activeConvId)) {
    state.pinned.delete(state.activeConvId);
    showToast('📌 Unpinned', 'info');
  } else {
    state.pinned.add(state.activeConvId);
    showToast('📌 Conversation pinned', 'success');
  }
  closeMoreMenu();
  renderConvoList();
}
function exportChat() { closeMoreMenu(); showToast('📥 Exporting chat…', 'info'); }
function blockContact() { closeMoreMenu(); showToast('🚫 Contact blocked', 'warn'); }
function closeMoreMenu() {
  state.moreMenuOpen = false;
  document.getElementById('moreMenu').classList.remove('open');
}

/*MESSAGE SEARCH*/
function toggleMsgSearch() {
  state.msgSearchOpen = !state.msgSearchOpen;
  const bar = document.getElementById('msgSearchBar');
  bar.classList.toggle('hidden', !state.msgSearchOpen);
  if (state.msgSearchOpen) bar.querySelector('input').focus();
  else document.getElementById('msgSearchCount').textContent = '';
}

function searchMessages(val) {
  if (!val) { document.getElementById('msgSearchCount').textContent = ''; return; }
  if (!state.activeConvId) return;
  const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
  const matches = conv.messages.filter(m => m.text && m.text.toLowerCase().includes(val.toLowerCase())).length;
  document.getElementById('msgSearchCount').textContent = matches ? `${matches} result${matches>1?'s':''}` : 'No results';
}

/*FILTER + SEARCH*/
function setFilter(filter, el) {
  state.filter = filter;
  document.querySelectorAll('.fchip').forEach(c => c.classList.remove('active'));
  if (el) {
    el.classList.add('active');
  } else {
    const chip = document.querySelector(`.fchip[data-filter="${filter}"]`);
    if (chip) chip.classList.add('active');
  }
  renderConvoList();
}

function applyRoleFilterFromUrl() {
  const roleParam = new URLSearchParams(window.location.search).get('role');
  if (!roleParam) return;

  const allowedFilters = new Set(['all', 'unread', 'vendors', 'customers', 'admin']);
  const normalizedRole = roleParam.toLowerCase();
  if (!allowedFilters.has(normalizedRole)) return;

  setFilter(normalizedRole);
}

function filterConvos(val) {
  state.search = val;
  document.getElementById('searchClear').classList.toggle('visible', val.length > 0);
  renderConvoList();
}

function clearSearch() {
  state.search = '';
  document.getElementById('searchInput').value = '';
  document.getElementById('searchClear').classList.remove('visible');
  renderConvoList();
}

function filterContacts(val) {
  const filtered = CONTACTS.filter(c =>
    c.name.toLowerCase().includes(val.toLowerCase()) ||
    (c.company || '').toLowerCase().includes(val.toLowerCase())
  );
  renderContactGrid(filtered);
}

function renderContactGrid(contacts) {
  const grid = document.getElementById('contactGrid');
  if (!grid) return;
  if (contacts.length === 0) {
    grid.innerHTML = `<div style="padding:30px;text-align:center;color:var(--t3);font-size:.82rem;">No contacts found</div>`;
    return;
  }
  grid.innerHTML = contacts.map(c => `
    <div class="contact-item" onclick="startNewChat('${c.id}')">
      <div class="citem-avatar" style="background:${c.color}">${c.initials}</div>
      <div class="citem-info">
        <strong>${c.name}</strong>
        <span>${c.company !== '—' ? c.company : c.email}</span>
      </div>
      <span class="citem-role role-${c.role}">${capitalize(c.role)}</span>
    </div>`).join('');
}

/* NEW CHAT MODAL */
function openNewChat(roleFilter = null) {
  document.getElementById('modalBackdrop').classList.remove('hidden');
  document.getElementById('contactSearch').value = '';
  if (roleFilter && ['admin', 'vendor'].includes(String(roleFilter).toLowerCase())) {
    renderContactGrid(CONTACTS.filter((contact) => contact.role === String(roleFilter).toLowerCase()));
    return;
  }
  renderContactGrid(CONTACTS);
}
function closeNewChat(e) {
  if (!e || e.target === document.getElementById('modalBackdrop'))
    document.getElementById('modalBackdrop').classList.add('hidden');
}
function startNewChat(contactId) {
  const contact = getContact(contactId);
  const existing = CONVERSATIONS.find(c => c.contactId === contactId);

  if (existing) {
    closeNewChat();
    openConversation(existing.id);
    return;
  }

  if (!ME_USER_ID || !contact || !contact.userId) {
    closeNewChat();
    const fallbackContact = getContact(contactId);
    const newConv = {
      id: 'conv_' + Date.now(), contactId,
      pinned:false, muted:false, unread:0,
      messages:[{ id:'m1', type:'system', text:`Conversation with ${fallbackContact.name} started`, time:getCurrentTime(), date:'Today' }],
      quickReplies:['Hello! How can I help you?','Thank you for contacting us!'],
    };
    CONVERSATIONS.unshift(newConv);
    state.activeConvId = newConv.id;
    openConversation(newConv.id);
    return;
  }

  fetch('../api/chat_start_conversation.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ target_user_id: contact.userId })
  })
    .then(async (res) => ({ status: res.status, payload: await res.json() }))
    .then(async ({ status, payload }) => {
      if (status === 401 || status === 429) {
        enableGuestMode('start_conversation_requires_auth');
        closeNewChat();
        const fallbackContact = getContact(contactId);
        const newConv = {
          id: 'conv_' + Date.now(), contactId,
          pinned:false, muted:false, unread:0,
          messages:[{ id:'m1', type:'system', text:`Conversation with ${fallbackContact.name} started`, time:getCurrentTime(), date:'Today' }],
          quickReplies:['Hello! How can I help you?','Thank you for contacting us!'],
        };
        CONVERSATIONS.unshift(newConv);
        openConversation(newConv.id);
        return;
      }

      if (!payload || !payload.success) {
        showToast((payload && payload.message) || 'Unable to start conversation.', 'warn');
        return;
      }

      const data = payload.data || {};
      const convId = data.conversation_key || (`conv${data.conversation_id}`);
      await loadChatDataFromApi();
      renderConvoList();
      closeNewChat();
      openConversation(convId);
      pendingChatRole = null;
      showToast(`Chat started with ${contact.name}.`, 'success');
    })
    .catch(() => {
      showToast('Unable to start conversation right now.', 'warn');
    });
}

/* CALL SYSTEM */
function toggleCallMenu() {
  if (!state.activeConvId) return;
  const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
  const contact = getContact(conv.contactId);
  startCall(contact, 'voice');
}
function toggleVideoCall() {
  if (!state.activeConvId) return;
  const conv = CONVERSATIONS.find(c => c.id === state.activeConvId);
  const contact = getContact(conv.contactId);
  startCall(contact, 'video');
}

function startCall(contact, type) {
  const overlay = document.getElementById('callOverlay');
  const avatar  = document.getElementById('callAvatar');
  avatar.style.background = contact.color;
  avatar.textContent = contact.initials;
  document.getElementById('callName').textContent = contact.name;
  document.getElementById('callStatus').textContent = type === 'video' ? '📹 Video calling…' : '📞 Calling…';
  document.getElementById('callTimer').classList.add('hidden');
  overlay.classList.remove('hidden');
  state.callActive = true;
  state.callSeconds = 0;

  // Simulate answer
  setTimeout(() => {
    if (!state.callActive) return;
    document.getElementById('callStatus').textContent = type === 'video' ? '📹 Video call in progress' : '📞 Connected';
    document.getElementById('callTimer').classList.remove('hidden');
    state.callTimer = setInterval(() => {
      state.callSeconds++;
      document.getElementById('callTimer').textContent = formatTime(state.callSeconds);
    }, 1000);
  }, 2500);
}

function endCall() {
  clearInterval(state.callTimer);
  state.callActive = false;
  document.getElementById('callOverlay').classList.add('hidden');
  showToast('📞 Call ended · ' + formatTime(state.callSeconds), 'info');
  state.callSeconds = 0;
}

function toggleMute(btn) { btn.classList.toggle('active'); showToast(btn.classList.contains('active') ? '🔇 Muted' : '🎤 Unmuted', 'info'); }
function toggleSpeaker(btn) { btn.classList.toggle('active'); showToast(btn.classList.contains('active') ? '🔊 Speaker on' : '🔈 Earpiece', 'info'); }

/* MOBILE RAIL */
function openRail()  { document.getElementById('leftRail').classList.add('open'); }
function closeRail() { document.getElementById('leftRail').classList.remove('open'); }

/* SETTINGS */
function toggleSettings() { showToast('⚙️ Settings coming soon', 'info'); }

/*HELPERS*/
function getContact(id) { return CONTACTS.find(c => c.id === id) || { name:'Unknown', initials:'?', color:'#888', role:'customer', status:'offline' }; }
function getCurrentTime() { return new Date().toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', hour12:true }); }
function formatTime(s) { return `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`; }
function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function scrollBottom(smooth) {
  const area = document.getElementById('messagesArea');
  if (!area) return;
  setTimeout(() => area.scrollTo({ top: area.scrollHeight, behavior: smooth ? 'smooth' : 'auto' }), 50);
}
function updateUnreadCount() {
  const total = CONVERSATIONS.reduce((a,c) => a + c.unread, 0);
  document.getElementById('unreadCount').textContent = total;
}

/*TOAST*/
function showToast(msg, type = 'info') {
  const stack = document.getElementById('toastStack');
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = msg;
  stack.appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 250); }, 3000);
}

/*GLOBAL CLICK / KEY HANDLERS*/
function handleGlobalClick(e) {
  // Close emoji picker
  if (state.emojiPickerOpen) {
    const picker = document.getElementById('emojiPicker');
    const btn    = document.getElementById('emojiBtn');
    if (!picker.contains(e.target) && !btn.contains(e.target)) toggleEmojiPicker();
  }
  // Close template panel
  if (state.templatePanelOpen) {
    const panel = document.getElementById('templatePanel');
    if (!panel.contains(e.target)) closeTemplates();
  }
  // Close more menu
  if (state.moreMenuOpen) {
    const menu = document.getElementById('moreMenu');
    if (!menu.contains(e.target)) closeMoreMenu();
  }
  // Close modal on backdrop
  if (!document.getElementById('modalBox')?.contains(e.target)) {
    const bk = document.getElementById('modalBackdrop');
    if (bk && !bk.classList.contains('hidden') && !document.getElementById('modalBox').contains(e.target)) {
      // Let onclick on backdrop handle it
    }
  }
}

function handleGlobalKey(e) {
  if (e.key === 'Escape') {
    closeMoreMenu();
    if (state.emojiPickerOpen) toggleEmojiPicker();
    if (state.templatePanelOpen) closeTemplates();
    if (state.msgSearchOpen) toggleMsgSearch();
    if (state.callActive) endCall();
    document.getElementById('modalBackdrop').classList.add('hidden');
    closeRail();
  }
}

console.log('%c BizLink CRM Chat 💬 ', 'background:#50C878;color:#fff;font-size:14px;padding:6px 14px;border-radius:4px;');