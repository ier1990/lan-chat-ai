/**
 * chat.js — Front-end logic for the AI Chat app.
 *
 * Handles: room switching, message sending, polling for new messages,
 *          new-room/DM creation, and logout.
 */

(function () {
  'use strict';

  const AI        = window.AI || {};
  const csrf      = AI.csrf       || '';
  const pollMs    = AI.pollMs     || 3000;
  const debugMode = !!AI.debugEnabled;

  let currentRoomId   = AI.roomId   || 0;
  let currentRoomType = '';
  let lastMessageId   = 0;
  let pollTimer       = null;
  let isSending       = false;

  /* ── DOM refs ──────────────────────────────────────────────────────── */
  const messagesArea  = () => document.getElementById('messages-area');
  const messageInput  = () => document.getElementById('message-input');
  const messageForm   = () => document.getElementById('message-form');
  const roomIdInput   = () => document.getElementById('room-id-input');

  /* ── Init ──────────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initLastMessageId();
    bindRoomLinks();
    bindComposer();
    bindNewRoom();
    bindLogout();
    scrollToBottom();
    startPolling();
  });

  function initLastMessageId() {
    const rows = document.querySelectorAll('.message-row[data-msg-id]');
    if (rows.length) {
      const ids = Array.from(rows).map(el => parseInt(el.dataset.msgId, 10));
      lastMessageId = Math.max(...ids);
    }
  }

  /* ── Room switching ────────────────────────────────────────────────── */
  function bindRoomLinks() {
    document.getElementById('sidebar').addEventListener('click', function (e) {
      const link = e.target.closest('.js-room-link');
      if (!link) return;
      e.preventDefault();
      const roomId   = parseInt(link.dataset.roomId, 10);
      const roomSlug = link.dataset.roomSlug || '';
      if (roomId === currentRoomId) return;
      switchRoom(roomId, roomSlug);
    });
  }

  function switchRoom(roomId, slug) {
    stopPolling();
    currentRoomId = roomId;
    lastMessageId = 0;
    debugClient('room.switch.start', { roomId, slug, currentRoomId });

    // Update URL without reload.
    history.replaceState({}, '', '/ai/?room=' + encodeURIComponent(slug));

    // Update active state in sidebar.
    document.querySelectorAll('.room-item').forEach(function (el) {
      el.classList.toggle('active', parseInt(el.dataset.roomId, 10) === roomId);
    });

    fetch('/ai/ajax/load_room.php?room_id=' + roomId, fetchOpts('GET'))
      .then(res => res.json())
      .then(function (data) {
        if (!data.ok) { showError(data.error || 'Failed to load room'); return; }
        renderMessages(data.messages, true);
        updateRoomHeader(data.room, data.dm_meta || null);
        if (roomIdInput()) roomIdInput().value = roomId;
        AI.roomId   = roomId;
        AI.roomSlug = slug;
        debugClient('room.switch.done', {
          roomId,
          slug,
          loadedRoomId: data.room && data.room.id,
          loadedRoomType: data.room && data.room.room_type,
          messageCount: data.messages ? data.messages.length : 0,
        });
        initLastMessageId();
        startPolling();
      })
      .catch(err => {
        debugClient('room.switch.error', { roomId, slug, error: err.message });
        showError('Network error: ' + err.message);
      });
  }

  function updateRoomHeader(room, dmMeta) {
    const header = document.querySelector('.room-header');
    if (!header) return;
    currentRoomType = room.room_type || '';
    const icon = room.room_type === 'log' ? '⊙' : room.room_type === 'dm' ? '@' : '#';
    header.querySelector('.room-header-icon').textContent = icon;
    header.querySelector('.room-header-name').textContent = room.name;

    // DM metadata bar.
    const metaBar = document.getElementById('dm-meta-bar');
    if (metaBar) {
      if (dmMeta && room.room_type === 'dm') {
        const parts = [];
        if (dmMeta.persona_name) parts.push('⊕ ' + dmMeta.persona_name);
        if (dmMeta.model)        parts.push(dmMeta.model);
        if (dmMeta.provider)     parts.push(dmMeta.provider);
        metaBar.textContent = parts.join(' · ');
        metaBar.hidden = false;
      } else {
        metaBar.textContent = '';
        metaBar.hidden = true;
      }
    }

    // Update composer placeholder.
    const inp = messageInput();
    if (inp) inp.placeholder = 'Message ' + icon + room.name + '  (Enter to send, Shift+Enter for newline, /help for commands)';
  }

  function hydrateDmMetaFromMessages(messages) {
    if (currentRoomType !== 'dm' || !Array.isArray(messages) || !messages.length) return;
    const metaBar = document.getElementById('dm-meta-bar');
    if (!metaBar) return;

    // Use latest AI reply metadata as authoritative identity for the active DM.
    let latestMeta = null;
    for (let i = messages.length - 1; i >= 0; i -= 1) {
      const msg = messages[i] || {};
      if (msg.message_type !== 'ai_reply' || !msg.meta) continue;
      const m = msg.meta;
      if (m.persona_name || m.model || m.provider) {
        latestMeta = m;
        break;
      }
    }
    if (!latestMeta) return;

    const parts = [];
    if (latestMeta.persona_name) parts.push('⊕ ' + latestMeta.persona_name);
    if (latestMeta.model)        parts.push(latestMeta.model);
    if (latestMeta.provider)     parts.push(latestMeta.provider);
    if (!parts.length) return;

    const next = parts.join(' · ');
    if (metaBar.textContent !== next) {
      metaBar.textContent = next;
      metaBar.hidden = false;
    }
  }

  /* ── Composer ──────────────────────────────────────────────────────── */
  function bindComposer() {
    const form = messageForm();
    if (!form) return;
    const sendBtn = form.querySelector('.send-btn');

    // Auto-grow textarea.
    document.addEventListener('input', function (e) {
      if (e.target.id !== 'message-input') return;
      e.target.style.height = 'auto';
      e.target.style.height = Math.min(e.target.scrollHeight, 128) + 'px';
    });

    // Enter submits; Shift+Enter inserts newline.
    document.addEventListener('keydown', function (e) {
      if (e.target.id !== 'message-input') return;
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submitComposer();
      }
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitComposer();
    });

    if (sendBtn) {
      sendBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitComposer();
      });
    }

    function submitComposer() {
      const inp  = messageInput();
      const text = inp ? inp.value.trim() : '';
      const formRoomId = roomIdInput() ? parseInt(roomIdInput().value, 10) : 0;
      const targetRoomId = formRoomId || currentRoomId;
      if (!text || isSending) return;
      sendMessage(targetRoomId, text);
      inp.value        = '';
      inp.style.height = 'auto';
    }
  }

  function sendMessage(roomId, text) {
    isSending = true;
    postForm('/ai/ajax/send_message.php', {
      csrf: csrf,
      room_id: roomId,
      message: text,
    })
      .then(res => res.json())
      .then(function (data) {
        isSending = false;
        debugClient('send.response', {
          roomId,
          ok: !!data.ok,
          error: data.error || '',
          messageCount: data.messages ? data.messages.length : 0,
        });
        if (!data.ok) { showError(data.error || 'Send failed'); return; }
        if (data.slash) {
          handleSlashResult(data.slash);
          return;
        }
        if (data.messages && data.messages.length) {
          appendMessages(data.messages);
        }
      })
      .catch(function (err) {
        isSending = false;
        debugClient('send.catch', { roomId, error: err.message });
        showError('Send failed: ' + err.message);
      });
  }

  /* ── Polling ───────────────────────────────────────────────────────── */
  function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(poll, pollMs);
  }

  function stopPolling() {
    clearInterval(pollTimer);
    pollTimer = null;
  }

  function poll() {
    if (!currentRoomId) return;
    fetch('/ai/ajax/send_message.php?room_id=' + currentRoomId + '&after_id=' + lastMessageId, fetchOpts('GET'))
      .then(res => res.json())
      .then(function (data) {
        if (data.ok && data.messages && data.messages.length) {
          appendMessages(data.messages);
        }
      })
      .catch(() => {}); // Silently ignore poll errors.
  }

  /* ── Message rendering ─────────────────────────────────────────────── */
  function renderMessages(messages, clear) {
    const area = messagesArea();
    if (!area) return;
    if (clear) area.innerHTML = '';
    if (!messages || !messages.length) {
      if (clear) area.innerHTML = '<div class="empty-state">No messages yet. Say something!</div>';
      return;
    }
    messages.forEach(msg => area.appendChild(buildMessageEl(msg)));
    hydrateDmMetaFromMessages(messages);
    scrollToBottom();
  }

  function appendMessages(messages) {
    const area = messagesArea();
    if (!area) return;
    const empty = area.querySelector('.empty-state');
    if (empty) empty.remove();
    messages.forEach(function (msg) {
      // Skip if already rendered.
      if (area.querySelector('[data-msg-id="' + msg.id + '"]')) return;
      area.appendChild(buildMessageEl(msg));
      if (parseInt(msg.id, 10) > lastMessageId) {
        lastMessageId = parseInt(msg.id, 10);
      }
    });
    hydrateDmMetaFromMessages(messages);
    scrollToBottom();
  }

  function buildMessageEl(msg) {
    const isAi      = msg.sender_type === 'persona';
    const isAiReply = msg.message_type === 'ai_reply';
    const isWebhook = msg.sender_type === 'webhook';
    const isSystem  = msg.sender_type === 'system';

    const rowCls = 'message-row'
      + (isAi      ? ' message-ai'      : '')
      + (isWebhook ? ' message-webhook'  : '')
      + (isSystem  ? ' message-system'   : '');

    const initial = (msg.sender_name || '?').charAt(0).toUpperCase();
    const avCls   = 'avatar avatar-' + (msg.sender_type || 'user');

    // AI badge for both persona-type and any ai_reply message (covers AI users).
    let badge = '';
    if (isAi || isAiReply) badge = '<span class="badge badge-ai">AI</span>';
    if (isWebhook)         badge = '<span class="badge badge-hook">↓</span>';
    if (isSystem)          badge = '<span class="badge badge-sys">SYS</span>';

    const time = msg.created_at
      ? new Date(msg.created_at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      : '';

    const text = escapeHtml(msg.message_text || '').replace(/\n/g, '<br>');

    // AI metadata footer: model · persona · provider · tokens · latency.
    let aiMetaHtml = '';
    if (isAiReply && msg.meta && msg.meta.model) {
      const m = msg.meta;
      const parts = [];
      // Persona name only for AI-user messages (persona-type already has it as sender_name).
      if (m.persona_name && !isAi) parts.push('⊕ ' + escapeHtml(m.persona_name));
      if (m.model)                 parts.push(escapeHtml(m.model));
      if (m.provider)              parts.push(escapeHtml(m.provider));
      const tIn  = parseInt(m.tokens_in,  10) || 0;
      const tOut = parseInt(m.tokens_out, 10) || 0;
      if (tIn || tOut) parts.push(tIn + '↑ ' + tOut + '↓');
      if (m.latency_ms) parts.push(parseInt(m.latency_ms, 10).toLocaleString() + 'ms');
      if (parts.length) aiMetaHtml = '<div class="message-ai-meta">' + parts.join(' · ') + '</div>';
    }

    const div = document.createElement('div');
    div.className    = rowCls;
    div.dataset.msgId = msg.id;
    div.innerHTML = `
      <div class="message-avatar"><span class="${avCls}">${escapeHtml(initial)}</span></div>
      <div class="message-body">
        <div class="message-meta">
          <span class="message-author">${escapeHtml(msg.sender_name || '?')}</span>
          ${badge}
          <span class="message-time" title="${escapeHtml(msg.created_at || '')}">${time}</span>
        </div>
        <div class="message-text">${text}</div>
        ${aiMetaHtml}
      </div>`;
    return div;
  }

  function scrollToBottom() {
    const area = messagesArea();
    if (area) area.scrollTop = area.scrollHeight;
  }

  /* ── New room / DM ─────────────────────────────────────────────────── */
  function bindNewRoom() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.js-new-room');
      if (btn) {
        const name = prompt('Channel name:');
        if (!name) return;
        createRoom({ type: 'channel', name });
      }

      const dmBtn = e.target.closest('.js-new-dm, [data-type="dm"]');
      if (!dmBtn) return;

      // AI/persona buttons carry explicit target data.
      if (dmBtn.dataset.otherType) {
        createRoom({
          type:       'dm',
          other_type: dmBtn.dataset.otherType,
          other_id:   parseInt(dmBtn.dataset.otherId, 10),
        });
        return;
      }

      // Open the user picker modal.
      openDmPicker();
    });
  }

  /* ── DM user picker modal ─────────────────────────────────────────── */
  (function bindDmPicker() {
    const backdrop = document.getElementById('dm-picker-backdrop');
    const closeBtn = document.getElementById('dm-picker-close');
    const search   = document.getElementById('dm-picker-search');
    const list     = document.getElementById('dm-picker-list');
    if (!backdrop) return;

    const allUsers = (window.AI && window.AI.users) ? window.AI.users : [];

    function renderList(filter) {
      const q = (filter || '').toLowerCase();
      const filtered = allUsers.filter(function (u) {
        return !q
          || u.username.toLowerCase().includes(q)
          || (u.display_name || '').toLowerCase().includes(q);
      });

      list.innerHTML = '';
      if (!filtered.length) {
        var li = document.createElement('li');
        li.className = 'dm-picker-empty';
        li.textContent = 'No users found.';
        list.appendChild(li);
        return;
      }
      filtered.forEach(function (u) {
        var li = document.createElement('li');
        li.className = 'dm-picker-item';
        li.dataset.userId = u.id;
        li.innerHTML = '<span class="dm-picker-avatar">' + escapeHtml((u.display_name || u.username)[0].toUpperCase()) + '</span>'
          + '<span class="dm-picker-info">'
          + '<strong>' + escapeHtml(u.display_name || u.username) + '</strong>'
          + '<span class="dm-picker-username">@' + escapeHtml(u.username) + '</span>'
          + '</span>';
        li.addEventListener('click', function () {
          closeDmPicker();
          createRoom({ type: 'dm', other_type: 'user', other_id: parseInt(u.id, 10) });
        });
        list.appendChild(li);
      });
    }

    function closeDmPicker() {
      backdrop.hidden = true;
      search.value = '';
    }

    // Expose open so the click handler above can call it.
    window._openDmPicker = function () {
      renderList('');
      backdrop.hidden = false;
      search.value = '';
      search.focus();
    };

    closeBtn.addEventListener('click', closeDmPicker);
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) closeDmPicker();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !backdrop.hidden) closeDmPicker();
    });
    search.addEventListener('input', function () { renderList(search.value); });
  }());

  function openDmPicker() {
    if (window._openDmPicker) window._openDmPicker();
  }

  function createRoom(payload) {
    payload.csrf = csrf;
    fetch('/ai/ajax/create_room.php', fetchOpts('POST', payload))
      .then(res => res.json())
      .then(function (data) {
        if (!data.ok) { showError(data.error || 'Could not create room'); return; }
        // Reload page to show new room in sidebar.
        window.location.href = '/ai/?room=' + encodeURIComponent(data.room.slug);
      })
      .catch(err => showError('Network error: ' + err.message));
  }

  function handleSlashResult(result) {
    if (!result) return;
    if (result.switch_room && result.switch_room.slug) {
      window.location.href = '/ai/?room=' + encodeURIComponent(result.switch_room.slug);
      return;
    }
    if (result.notice) {
      showClientFlash(result.notice, result.type || 'info');
    }
  }

  /* ── Logout ────────────────────────────────────────────────────────── */
  function bindLogout() {
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.js-logout')) return;
      e.preventDefault();
      fetch('/ai/ajax/logout.php', fetchOpts('POST', { csrf }))
        .then(() => window.location.href = '/ai/')
        .catch(() => window.location.href = '/ai/');
    });
  }

  /* ── Helpers ───────────────────────────────────────────────────────── */
  function fetchOpts(method, body) {
    const opts = { method, headers: {} };
    if (method === 'POST') {
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-Token'] = csrf;
      if (body) opts.body = JSON.stringify(body);
    }
    return opts;
  }

  function postForm(url, payload) {
    const body = new URLSearchParams();
    Object.keys(payload || {}).forEach(function (key) {
      const value = payload[key];
      if (value === undefined || value === null) return;
      body.append(key, String(value));
    });
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
    });
  }

  function debugClient(event, payload) {
    if (!debugMode) return;
    fetch('/ai/ajax/debug_client.php', fetchOpts('POST', {
      csrf,
      event,
      payload: payload || {},
    })).catch(function () {});
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showError(msg) {
    showClientFlash(msg, 'error');
  }

  function showClientFlash(msg, type) {
    console.error('[AI Chat]', msg);

    let box = document.getElementById('client-error-box');
    if (!box) {
      box = document.createElement('div');
      box.id = 'client-error-box';
      const main = document.getElementById('main');
      if (main) {
        main.prepend(box);
      }
    }
    if (box) {
      box.className = 'flash flash-' + (type || 'info');
      box.textContent = msg;
    }
  }

})();
