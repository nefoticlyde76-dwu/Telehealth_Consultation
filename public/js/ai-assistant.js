(function () {
  "use strict";

  const root = document.querySelector("[data-ai-assistant]");
  if (!(root instanceof HTMLElement)) {
    return;
  }

  const panel = root.querySelector("[data-medimate-panel]");
  const openButton = root.querySelector("[data-medimate-open]");
  const closeButton = root.querySelector("[data-medimate-close]");
  const form = root.querySelector("[data-ai-form]");
  const input = root.querySelector("[data-ai-input]");
  const sendButton = root.querySelector("[data-ai-send]");
  const thread = root.querySelector("[data-ai-thread]");
  const welcome = root.querySelector("[data-ai-welcome]");
  const tokenInput = form instanceof HTMLFormElement ? form.querySelector('input[name="_token"]') : null;
  const endpoint = root.getAttribute("data-chat-endpoint") || "";
  const avatarSrc = root.getAttribute("data-avatar") || "";
  const maxLength = Math.max(32, parseInt(root.getAttribute("data-max-length") || "1500", 10) || 1500);

  if (!(panel instanceof HTMLElement) || !(openButton instanceof HTMLButtonElement) || !(closeButton instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement) || !(input instanceof HTMLTextAreaElement) || !(sendButton instanceof HTMLButtonElement) || !(thread instanceof HTMLElement) || endpoint === "") {
    return;
  }

  let inFlight = false;
  let closeTimer = 0;
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  openButton.addEventListener("click", function () {
    setOpen(true);
  });

  closeButton.addEventListener("click", function () {
    setOpen(false);
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && root.classList.contains("is-open")) {
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
      return;
    }

    root.classList.remove("is-open");
    closeTimer = window.setTimeout(function () {
      panel.hidden = true;
      openButton.focus();
    }, reduceMotion ? 0 : 220);
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
        if (payload && typeof payload.csrf_token === "string" && payload.csrf_token !== "" && tokenInput instanceof HTMLInputElement) {
          tokenInput.value = payload.csrf_token;
        }

        if (result.response.redirected || result.response.status === 302 || result.response.status === 401) {
          showError("Please sign in again to continue.");
          return;
        }

        if (payload && payload.success === true && typeof payload.reply === "string" && payload.reply.trim() !== "") {
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

  function appendMessage(role, text) {
    const row = buildRow(role, false);
    fillBubble(row.querySelector(".medimate-msg__bubble"), text);
    thread.appendChild(row);
    scrollToLatest();
    return row;
  }

  function appendPending() {
    const row = buildRow("assistant", true);
    const bubble = row.querySelector(".medimate-msg__bubble");
    const status = document.createElement("p");
    status.textContent = "MediMate AI is thinking";
    const dots = document.createElement("span");
    dots.className = "medimate-dots";
    dots.setAttribute("aria-hidden", "true");
    dots.innerHTML = "<span></span><span></span><span></span>";
    status.appendChild(document.createTextNode(" "));
    status.appendChild(dots);
    bubble.appendChild(status);
    thread.appendChild(row);
    scrollToLatest();
    return row;
  }

  function buildRow(role, pending) {
    const row = document.createElement("div");
    row.className = "medimate-msg medimate-msg--" + (role === "user" ? "user" : "assistant") + (pending ? " medimate-msg--pending" : "");
    if (pending) {
      row.setAttribute("data-ai-pending", "1");
    }

    if (role !== "user") {
      row.appendChild(createAvatar());
    }

    const col = document.createElement("div");
    col.className = "medimate-msg__col";

    if (role !== "user") {
      const label = document.createElement("span");
      label.className = "medimate-msg__label";
      label.textContent = "MediMate AI";
      col.appendChild(label);
    }

    const bubble = document.createElement("div");
    bubble.className = "medimate-msg__bubble";

    col.appendChild(bubble);
    row.appendChild(col);
    return row;
  }

  function createAvatar() {
    const avatar = document.createElement("span");
    avatar.className = "medimate-msg__avatar";
    avatar.setAttribute("aria-hidden", "true");
    if (avatarSrc !== "") {
      const img = document.createElement("img");
      img.src = avatarSrc;
      img.alt = "";
      img.width = 32;
      img.height = 32;
      avatar.appendChild(img);
    }
    return avatar;
  }

  function stripMarkdown(text) {
    let value = String(text || "").replace(/\r\n?/g, "\n");
    value = value.replace(/```[a-zA-Z0-9]*\n?([\s\S]*?)```/g, "$1");
    value = value.replace(/`([^`\n]+)`/g, "$1");
    value = value.replace(/^\s{0,3}#{1,6}\s+/gm, "");
    value = value.replace(/^\s*[-*+]\s+/gm, "");
    value = value.replace(/^\s*>\s+/gm, "");
    value = value.replace(/\[([^\]]+)\]\([^)]+\)/g, "$1");
    value = value.replace(/(\*\*|__)(.+?)\1/g, "$2");
    value = value.replace(/(\*|_)([^*\n]+)\1/g, "$2");
    value = value.replace(/[#*`]/g, "");
    value = value.replace(/\*\*/g, "");
    return value.replace(/\n{3,}/g, "\n\n").trim();
  }

  function fillBubble(bubble, text) {
    if (!(bubble instanceof HTMLElement)) {
      return;
    }

    const paragraphs = stripMarkdown(text).split(/\n{2,}/);
    if (paragraphs.length === 0 || (paragraphs.length === 1 && paragraphs[0] === "")) {
      const p = document.createElement("p");
      p.textContent = stripMarkdown(text);
      bubble.appendChild(p);
      return;
    }

    paragraphs.forEach(function (para) {
      const p = document.createElement("p");
      const lines = para.split("\n");
      lines.forEach(function (line, index) {
        if (index > 0) {
          p.appendChild(document.createElement("br"));
        }
        p.appendChild(document.createTextNode(line));
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
  }

  function autosize() {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 96) + "px";
  }

  function scrollToLatest() {
    thread.scrollTop = thread.scrollHeight;
  }
})();
