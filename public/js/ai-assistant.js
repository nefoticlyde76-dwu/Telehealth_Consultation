(function () {
  "use strict";

  const root = document.querySelector("[data-ai-assistant]");
  if (!(root instanceof HTMLElement)) {
    return;
  }

  const panel = root.querySelector("[data-medimate-panel]");
  const openButton = root.querySelector("[data-medimate-open]");
  const closeButton = root.querySelector("[data-medimate-close]");
  const historyButton = root.querySelector("[data-medimate-history]");
  const newChatButton = root.querySelector("[data-medimate-new]");
  const historyPanel = root.querySelector("[data-medimate-history-panel]");
  const historyList = root.querySelector("[data-medimate-history-list]");
  const form = root.querySelector("[data-ai-form]");
  const input = root.querySelector("[data-ai-input]");
  const sendButton = root.querySelector("[data-ai-send]");
  const thread = root.querySelector("[data-ai-thread]");
  const welcome = root.querySelector("[data-ai-welcome]");
  const tokenInput = form instanceof HTMLFormElement ? form.querySelector('input[name="_token"]') : null;
  const endpoint = root.getAttribute("data-chat-endpoint") || "";
  const conversationEndpoint = root.getAttribute("data-conversation-endpoint") || "";
  const conversationsEndpoint = root.getAttribute("data-conversations-endpoint") || "";
  const deleteEndpoint = root.getAttribute("data-delete-endpoint") || "";
  const avatarSrc = root.getAttribute("data-avatar") || "";
  const maxLength = Math.max(32, parseInt(root.getAttribute("data-max-length") || "1500", 10) || 1500);

  if (!(panel instanceof HTMLElement) || !(openButton instanceof HTMLButtonElement) || !(closeButton instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement) || !(input instanceof HTMLTextAreaElement) || !(sendButton instanceof HTMLButtonElement) || !(thread instanceof HTMLElement) || endpoint === "") {
    return;
  }

  let inFlight = false;
  let closeTimer = 0;
  let conversationId = 0;
  let startNew = false;
  let restored = false;
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  loadLatestConversation();

  openButton.addEventListener("click", function () {
    setOpen(true);
  });

  closeButton.addEventListener("click", function () {
    setHistoryOpen(false);
    setOpen(false);
  });

  if (newChatButton instanceof HTMLButtonElement) {
    newChatButton.addEventListener("click", function () {
      beginNewChat();
    });
  }

  if (historyButton instanceof HTMLButtonElement) {
    historyButton.addEventListener("click", function () {
      const nextOpen = historyPanel instanceof HTMLElement ? historyPanel.hidden : true;
      setHistoryOpen(nextOpen);
      if (nextOpen) {
        loadConversationList();
      }
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && root.classList.contains("is-open")) {
      if (historyPanel instanceof HTMLElement && !historyPanel.hidden) {
        setHistoryOpen(false);
        return;
      }
      setOpen(false);
    }
  });

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    submitMessage(input.value);
  });

  input.addEventListener("keydown", function (event) {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      submitMessage(input.value);
    }
  });

  input.addEventListener("input", autosize);

  root.querySelectorAll("[data-ai-suggestion]").forEach(function (button) {
    button.addEventListener("click", function () {
      const text = (button.textContent || "").trim();
      if (text !== "") {
        submitMessage(text);
      }
    });
  });

  function setOpen(open) {
    window.clearTimeout(closeTimer);
    openButton.setAttribute("aria-expanded", open ? "true" : "false");

    if (open) {
      panel.hidden = false;
      window.requestAnimationFrame(function () {
        root.classList.add("is-open");
      });
      window.setTimeout(function () {
        input.focus();
        scrollToLatest();
      }, reduceMotion ? 0 : 40);
      if (!restored) {
        loadLatestConversation();
      }
      return;
    }

    setHistoryOpen(false);
    root.classList.remove("is-open");
    closeTimer = window.setTimeout(function () {
      panel.hidden = true;
      openButton.focus();
    }, reduceMotion ? 0 : 140);
  }

  function beginNewChat() {
    conversationId = 0;
    startNew = true;
    restored = true;
    setHistoryOpen(false);
    clearThreadMessages();
    showWelcome();
    clearError();
    input.focus();
  }

  function submitMessage(raw) {
    if (inFlight) {
      return;
    }

    if (panel.hidden) {
      setOpen(true);
    }

    const message = String(raw || "").trim();
    if (message === "") {
      showError("Please enter a question before sending.");
      input.focus();
      return;
    }
    if (message.length > maxLength) {
      showError("Please keep your question under " + maxLength + " characters.");
      return;
    }

    hideWelcome();
    clearError();
    appendMessage("user", message);
    input.value = "";
    autosize();
    const pending = appendPending();

    setBusy(true);

    const body = new FormData();
    body.append("message", message);
    if (conversationId > 0) {
      body.append("conversation_id", String(conversationId));
    }
    if (startNew) {
      body.append("start_new", "1");
    }
    if (tokenInput instanceof HTMLInputElement) {
      body.append("_token", tokenInput.value);
    }

    const controller = new AbortController();
    const timeoutId = window.setTimeout(function () {
      controller.abort();
    }, 45000);

    fetch(endpoint, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: body,
      signal: controller.signal,
    })
      .then(function (response) {
        return response.text().then(function (text) {
          let payload = null;
          try {
            payload = text ? JSON.parse(text) : null;
          } catch (_err) {
            payload = null;
          }
          return { response: response, payload: payload };
        });
      })
      .then(function (result) {
        window.clearTimeout(timeoutId);
        removePending(pending);

        const payload = result.payload && typeof result.payload === "object" ? result.payload : null;
        applyCsrf(payload);

        if (result.response.redirected || result.response.status === 302 || result.response.status === 401) {
          showError("Please sign in again to continue.");
          return;
        }

        if (payload && payload.success === true && typeof payload.reply === "string" && payload.reply.trim() !== "") {
          const nextId = parseInt(String(payload.conversation_id || "0"), 10) || 0;
          if (nextId > 0) {
            conversationId = nextId;
            startNew = false;
          }
          appendMessage("assistant", payload.reply);
          return;
        }

        const safeMessage = payload && typeof payload.message === "string" && payload.message.trim() !== ""
          ? payload.message
          : "MediMate AI is temporarily unavailable. Please try again shortly.";
        showError(safeMessage);
      })
      .catch(function () {
        window.clearTimeout(timeoutId);
        removePending(pending);
        showError("MediMate AI is temporarily unavailable. Please try again shortly.");
      })
      .finally(function () {
        setBusy(false);
        if (!panel.hidden) {
          input.focus();
        }
      });
  }

  function loadLatestConversation() {
    if (conversationEndpoint === "") {
      restored = true;
      return;
    }

    fetchJson(conversationEndpoint).then(function (payload) {
      restored = true;
      if (!payload || payload.success !== true) {
        return;
      }
      applyConversation(payload);
    }).catch(function () {
      restored = true;
    });
  }

  function loadOwnedConversation(id) {
    if (conversationEndpoint === "" || id <= 0) {
      return;
    }

    fetchJson(conversationEndpoint + (conversationEndpoint.indexOf("?") === -1 ? "?" : "&") + "id=" + encodeURIComponent(String(id)))
      .then(function (payload) {
        if (!payload || payload.success !== true) {
          showError("That conversation could not be opened.");
          return;
        }
        applyConversation(payload);
        setHistoryOpen(false);
        scrollToLatest();
      })
      .catch(function () {
        showError("That conversation could not be opened.");
      });
  }

  function applyConversation(payload) {
    const conversation = payload.conversation && typeof payload.conversation === "object" ? payload.conversation : null;
    const messages = Array.isArray(payload.messages) ? payload.messages : [];
    conversationId = conversation && parseInt(String(conversation.id || "0"), 10) > 0
      ? parseInt(String(conversation.id), 10)
      : 0;
    startNew = false;

    clearThreadMessages();
    clearError();

    if (messages.length === 0) {
      showWelcome();
      return;
    }

    hideWelcome();
    messages.forEach(function (item) {
      if (!item || typeof item !== "object") {
        return;
      }
      const role = item.role === "assistant" ? "assistant" : "user";
      const text = typeof item.message === "string" ? item.message : "";
      const createdAt = typeof item.created_at === "string" ? item.created_at : "";
      if (text.trim() !== "") {
        appendMessage(role, text, createdAt);
      }
    });
  }

  function loadConversationList() {
    if (!(historyList instanceof HTMLElement) || conversationsEndpoint === "") {
      return;
    }

    historyList.innerHTML = "";
    const loading = document.createElement("p");
    loading.className = "medimate-history__empty";
    loading.textContent = "Loading conversations…";
    historyList.appendChild(loading);

    fetchJson(conversationsEndpoint).then(function (payload) {
      if (!(historyList instanceof HTMLElement)) {
        return;
      }
      historyList.innerHTML = "";
      const items = payload && Array.isArray(payload.conversations) ? payload.conversations : [];
      if (items.length === 0) {
        const empty = document.createElement("p");
        empty.className = "medimate-history__empty";
        empty.textContent = "No previous conversations yet.";
        historyList.appendChild(empty);
        return;
      }

      const groups = { today: [], yesterday: [], older: [] };
      items.forEach(function (item) {
        const group = item && item.group === "today" ? "today" : item && item.group === "yesterday" ? "yesterday" : "older";
        groups[group].push(item);
      });

      appendHistoryGroup("Today", groups.today);
      appendHistoryGroup("Yesterday", groups.yesterday);
      appendHistoryGroup("Older", groups.older);
    }).catch(function () {
      if (!(historyList instanceof HTMLElement)) {
        return;
      }
      historyList.innerHTML = "";
      const empty = document.createElement("p");
      empty.className = "medimate-history__empty";
      empty.textContent = "Unable to load conversations.";
      historyList.appendChild(empty);
    });
  }

  function appendHistoryGroup(label, items) {
    if (!(historyList instanceof HTMLElement) || items.length === 0) {
      return;
    }

    const heading = document.createElement("p");
    heading.className = "medimate-history__heading";
    heading.textContent = label;
    historyList.appendChild(heading);

    items.forEach(function (item) {
      const id = parseInt(String(item && item.id ? item.id : "0"), 10) || 0;
      const title = item && typeof item.title === "string" && item.title.trim() !== "" ? item.title : "Health question";
      const row = document.createElement("div");
      row.className = "medimate-history__row";
      if (id === conversationId && conversationId > 0) {
        row.classList.add("is-active");
      }

      const open = document.createElement("button");
      open.type = "button";
      open.className = "medimate-history__item";
      open.textContent = title;
      open.addEventListener("click", function () {
        loadOwnedConversation(id);
      });

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "medimate-history__delete";
      remove.setAttribute("aria-label", "Delete conversation");
      remove.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
      remove.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        deleteConversation(id);
      });

      row.appendChild(open);
      row.appendChild(remove);
      historyList.appendChild(row);
    });
  }

  function deleteConversation(id) {
    if (id <= 0 || deleteEndpoint === "") {
      return;
    }
    if (!window.confirm("Delete this conversation? This cannot be undone.")) {
      return;
    }

    const body = new FormData();
    body.append("conversation_id", String(id));
    if (tokenInput instanceof HTMLInputElement) {
      body.append("_token", tokenInput.value);
    }

    fetch(deleteEndpoint, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: body,
    })
      .then(function (response) {
        return response.text().then(function (text) {
          let payload = null;
          try {
            payload = text ? JSON.parse(text) : null;
          } catch (_err) {
            payload = null;
          }
          return payload;
        });
      })
      .then(function (payload) {
        applyCsrf(payload);
        if (!payload || payload.success !== true) {
          showError(payload && typeof payload.message === "string" ? payload.message : "That conversation could not be deleted.");
          return;
        }
        if (id === conversationId) {
          beginNewChat();
        }
        loadConversationList();
      })
      .catch(function () {
        showError("That conversation could not be deleted.");
      });
  }

  function fetchJson(url) {
    return fetch(url, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    }).then(function (response) {
      return response.text().then(function (text) {
        let payload = null;
        try {
          payload = text ? JSON.parse(text) : null;
        } catch (_err) {
          payload = null;
        }
        applyCsrf(payload);
        return payload;
      });
    });
  }

  function applyCsrf(payload) {
    if (payload && typeof payload.csrf_token === "string" && payload.csrf_token !== "" && tokenInput instanceof HTMLInputElement) {
      tokenInput.value = payload.csrf_token;
    }
  }

  function setHistoryOpen(open) {
    if (!(historyPanel instanceof HTMLElement) || !(historyButton instanceof HTMLButtonElement)) {
      return;
    }
    historyPanel.hidden = !open;
    historyButton.setAttribute("aria-expanded", open ? "true" : "false");
  }

  function appendMessage(role, text, createdAt) {
    const row = buildRow(role, false, createdAt);
    fillBubble(row.querySelector(".medimate-msg__bubble"), text);
    thread.appendChild(row);
    scrollToLatest();
    return row;
  }

  function appendPending() {
    const row = buildRow("assistant", true);
    const bubble = row.querySelector(".medimate-msg__bubble");
    const status = document.createElement("p");
    status.className = "visually-hidden";
    status.textContent = "MediMate AI is thinking";
    const dots = document.createElement("span");
    dots.className = "medimate-dots medimate-typing";
    dots.setAttribute("aria-hidden", "true");
    dots.innerHTML = "<span></span><span></span><span></span>";
    bubble.appendChild(status);
    bubble.appendChild(dots);
    thread.appendChild(row);
    scrollToLatest();
    return row;
  }

  function buildRow(role, pending, createdAt) {
    const isUser = role === "user";
    const row = document.createElement("div");
    row.className = "medimate-msg medimate-message medimate-msg--" + (isUser ? "user medimate-user-message" : "assistant medimate-assistant-message") + (pending ? " medimate-msg--pending" : "");
    if (pending) {
      row.setAttribute("data-ai-pending", "1");
    }

    if (!isUser) {
      row.appendChild(createAvatar());
    }

    const col = document.createElement("div");
    col.className = "medimate-msg__col";

    if (!isUser) {
      const head = document.createElement("div");
      head.className = "medimate-msg__head";
      const label = document.createElement("span");
      label.className = "medimate-msg__label";
      label.textContent = "MediMate AI";
      head.appendChild(label);
      const time = formatMessageTime(createdAt);
      if (time !== "") {
        const timeEl = document.createElement("span");
        timeEl.className = "medimate-msg__time";
        timeEl.textContent = time;
        head.appendChild(timeEl);
      }
      col.appendChild(head);
    }

    const bubble = document.createElement("div");
    bubble.className = "medimate-msg__bubble medimate-message-bubble";
    col.appendChild(bubble);

    if (isUser) {
      const meta = document.createElement("div");
      meta.className = "medimate-msg__meta";
      const time = formatMessageTime(createdAt);
      if (time !== "") {
        const timeEl = document.createElement("span");
        timeEl.className = "medimate-msg__time";
        timeEl.textContent = time;
        meta.appendChild(timeEl);
      }
      const check = document.createElement("i");
      check.className = "bi bi-check2-all medimate-msg__read";
      check.setAttribute("aria-hidden", "true");
      meta.appendChild(check);
      col.appendChild(meta);
    }

    row.appendChild(col);
    return row;
  }

  function createAvatar() {
    const avatar = document.createElement("span");
    avatar.className = "medimate-msg__avatar medimate-avatar";
    avatar.setAttribute("aria-hidden", "true");
    if (avatarSrc !== "") {
      const img = document.createElement("img");
      img.src = avatarSrc;
      img.alt = "";
      img.width = 36;
      img.height = 36;
      avatar.appendChild(img);
    }
    return avatar;
  }

  function formatMessageTime(value) {
    const raw = value == null || String(value).trim() === "" ? new Date().toISOString() : String(value).trim();
    const normalized = raw.indexOf("T") === -1 ? raw.replace(" ", "T") : raw;
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    return date.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
  }

  function stripMarkdown(text) {
    let value = String(text || "").replace(/\r\n?/g, "\n");
    value = value.replace(/```[a-zA-Z0-9]*\n?([\s\S]*?)```/g, "$1");
    value = value.replace(/`([^`\n]+)`/g, "$1");
    value = value.replace(/^\s{0,3}#{1,6}\s+/gm, "");
    value = value.replace(/^\s*[-*+]\s+/gm, "• ");
    value = value.replace(/^\s*>\s+/gm, "");
    value = value.replace(/\[([^\]]+)\]\([^)]+\)/g, "$1");
    value = value.replace(/[#`]/g, "");
    return value.replace(/\n{3,}/g, "\n\n").trim();
  }

  function appendInline(target, text) {
    const parts = String(text || "").split(/(\*\*[^*\n]+?\*\*|__[^_\n]+?__)/g);
    parts.forEach(function (part) {
      if (part === "") {
        return;
      }
      const bold = part.match(/^(?:\*\*|__)(.+?)(?:\*\*|__)$/);
      if (bold) {
        const strong = document.createElement("strong");
        strong.textContent = bold[1];
        target.appendChild(strong);
        return;
      }
      target.appendChild(document.createTextNode(part.replace(/\*/g, "")));
    });
  }

  function fillBubble(bubble, text) {
    if (!(bubble instanceof HTMLElement)) {
      return;
    }

    const cleaned = stripMarkdown(text);
    const paragraphs = cleaned.split(/\n{2,}/);
    if (paragraphs.length === 0 || (paragraphs.length === 1 && paragraphs[0] === "")) {
      const p = document.createElement("p");
      appendInline(p, cleaned);
      bubble.appendChild(p);
      return;
    }

    paragraphs.forEach(function (para) {
      const lines = para.split("\n");
      const listLines = lines.filter(function (line) {
        return /^[•]\s+/.test(line);
      });
      if (listLines.length > 0 && listLines.length === lines.length) {
        const list = document.createElement("ul");
        lines.forEach(function (line) {
          const item = document.createElement("li");
          appendInline(item, line.replace(/^[•]\s+/, ""));
          list.appendChild(item);
        });
        bubble.appendChild(list);
        return;
      }

      const p = document.createElement("p");
      lines.forEach(function (line, index) {
        if (index > 0) {
          p.appendChild(document.createElement("br"));
        }
        appendInline(p, line);
      });
      bubble.appendChild(p);
    });
  }

  function removePending(node) {
    if (node instanceof HTMLElement) {
      node.remove();
    }
  }

  function hideWelcome() {
    if (welcome instanceof HTMLElement) {
      welcome.hidden = true;
    }
  }

  function showWelcome() {
    if (welcome instanceof HTMLElement) {
      welcome.hidden = false;
    }
  }

  function clearThreadMessages() {
    Array.from(thread.children).forEach(function (node) {
      if (node === welcome) {
        return;
      }
      node.remove();
    });
  }

  function showError(message) {
    clearError();
    const alert = document.createElement("div");
    alert.className = "medimate-error";
    alert.setAttribute("data-ai-error", "1");
    alert.setAttribute("role", "alert");
    alert.textContent = message;
    thread.appendChild(alert);
    scrollToLatest();
  }

  function clearError() {
    thread.querySelectorAll("[data-ai-error]").forEach(function (node) {
      node.remove();
    });
  }

  function setBusy(busy) {
    inFlight = busy;
    sendButton.disabled = busy;
    input.disabled = busy;
    root.querySelectorAll("[data-ai-suggestion]").forEach(function (button) {
      if (button instanceof HTMLButtonElement) {
        button.disabled = busy;
      }
    });
    if (newChatButton instanceof HTMLButtonElement) {
      newChatButton.disabled = busy;
    }
  }

  function autosize() {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 96) + "px";
  }

  function scrollToLatest() {
    thread.scrollTop = thread.scrollHeight;
  }
})();
