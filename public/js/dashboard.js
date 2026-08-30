document.addEventListener("DOMContentLoaded", () => {
  initializeDashboardDateTime();
  initializeMiniCalendars();
  initializeMbphaCalendars();
  initializeDashboardCharts();
  initializeAosAnimations();
  initializeConsultationQueueWorkspace();
  initializeDesktopSidebarToggle();
  initializeListFilterLoading();
  initializeNotificationToasts();
  initializeUxConfirmModal();
  initializePermanentDeleteModal();
  initializeUserBulkSelection();
  initializeNotificationBulkSelection();
});

/**
 * Return the server's configured application timezone (typically
 * Pacific/Port_Moresby for MBPHA).  Rendered on <body> by the dashboard
 * layout so dashboard widgets compute "now" on the same wall-clock the
 * PHP backend uses, regardless of the visitor's browser/OS timezone.
 */
function getAppTimezone() {
  const fromBody =
    document.body && typeof document.body.getAttribute === "function"
      ? (document.body.getAttribute("data-app-timezone") || "").trim()
      : "";
  return fromBody !== "" ? fromBody : "Pacific/Port_Moresby";
}

/**
 * Return the current wall-clock expressed in the application timezone as
 * structured parts (year/month/day/hour/minute/second/weekdayShort +
 * epoch ms).  Avoids `new Date().getHours()` etc. which would reflect
 * the visitor's browser timezone instead of MBPHA PNG local time.
 */
function getAppNow() {
  const tz = getAppTimezone();
  const now = new Date();
  const fmt = new Intl.DateTimeFormat("en-NZ", {
    timeZone: tz,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
    weekday: "short",
  });
  const parts = fmt.formatToParts(now).reduce((acc, part) => {
    acc[part.type] = part.value;
    return acc;
  }, {});
  const year = Number(parts.year);
  const month = Number(parts.month); // 1-12
  const day = Number(parts.day);
  const hour = Number(parts.hour) % 24;
  const minute = Number(parts.minute);
  const second = Number(parts.second);
  const weekdayShort = (parts.weekday || "").slice(0, 3);
  return {
    tz,
    year,
    month,
    day,
    hour,
    minute,
    second,
    weekdayShort,
    epoch: now.getTime(),
  };
}

function pad2(n) {
  return n < 10 ? "0" + n.toString() : n.toString();
}

const SHORT_MONTHS = [
  "Jan",
  "Feb",
  "Mar",
  "Apr",
  "May",
  "Jun",
  "Jul",
  "Aug",
  "Sep",
  "Oct",
  "Nov",
  "Dec",
];

function formatAppNow(mode, n) {
  const weekday =
    n.weekdayShort ||
    ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"][
      new Date(n.year, n.month - 1, n.day).getDay()
    ] ||
    "";
  const datePart = `${weekday}, ${pad2(n.day)} ${SHORT_MONTHS[n.month - 1]}`;
  if (mode === "date") {
    return datePart;
  }
  const hour = n.hour;
  const hour12 = ((hour + 11) % 12) + 1;
  const ampm = hour >= 12 ? "PM" : "AM";
  return `${datePart} ${n.year} ${pad2(hour12)}:${pad2(n.minute)} ${ampm}`;
}

function initializeDashboardDateTime() {
  const targets = document.querySelectorAll("[data-dashboard-datetime]");

  if (targets.length === 0) {
    return;
  }

  const render = () => {
    const now = getAppNow();
    targets.forEach((element) => {
      const mode = element.getAttribute("data-dashboard-datetime") || "full";
      element.textContent = formatAppNow(mode, now);
      if (typeof element.setAttribute === "function") {
        element.setAttribute("title", `Application timezone: ${now.tz}`);
      }
    });
  };

  render();
  window.setInterval(render, 60 * 1000);
}

const FULL_MONTHS = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
];

function calendarDateKey(year, month, day) {
  return `${year}-${pad2(month + 1)}-${pad2(day)}`;
}

function parseCalendarEvents(root) {
  const raw = root.getAttribute("data-calendar-events") || "[]";

  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    return [];
  }
}

function eventsByDate(events) {
  return events.reduce((map, event) => {
    const date = typeof event.date === "string" ? event.date : "";

    if (date === "") {
      return map;
    }

    if (!map[date]) {
      map[date] = [];
    }

    map[date].push(event);
    return map;
  }, {});
}

function initializeMbphaCalendars() {
  const widgets = document.querySelectorAll("[data-mbpha-calendar]");

  if (widgets.length === 0) {
    return;
  }

  widgets.forEach((root) => {
    const grid = root.querySelector("[data-calendar-grid]");
    const list = root.querySelector("[data-calendar-events-list]");
    const monthEl = root.querySelector("[data-calendar-month]");
    const yearEl = root.querySelector("[data-calendar-year]");
    const prev = root.querySelector("[data-calendar-prev]");
    const next = root.querySelector("[data-calendar-next]");

    if (!grid || !list || !monthEl || !yearEl) {
      return;
    }

    const now = getAppNow();
    const today = { y: now.year, m: now.month - 1, d: now.day };
    const view = { y: today.y, m: today.m };
    const selected = { y: today.y, m: today.m, d: today.d };
    const grouped = eventsByDate(parseCalendarEvents(root));

    const renderEvents = () => {
      const key = calendarDateKey(selected.y, selected.m, selected.d);
      const dayEvents = grouped[key] || [];
      list.replaceChildren();

      if (dayEvents.length === 0) {
        const empty = document.createElement("p");
        empty.className = "mbpha-cal__ev-none";
        empty.textContent = "No appointments scheduled for this day";
        list.appendChild(empty);
        return;
      }

      dayEvents.forEach((event) => {
        const hasUrl = typeof event.url === "string" && event.url !== "";
        const item = document.createElement(hasUrl ? "a" : "div");
        item.className = "mbpha-cal__ev";
        item.style.borderLeftColor = typeof event.color === "string" && event.color !== "" ? event.color : "var(--medical-blue)";

        if (hasUrl) {
          item.href = event.url;
        }

        const time = document.createElement("span");
        time.className = "mbpha-cal__ev-time";
        time.textContent = event.time || "—";

        const body = document.createElement("span");
        body.className = "mbpha-cal__ev-body";

        const name = document.createElement("span");
        name.className = "mbpha-cal__ev-name";
        name.textContent = event.name || "Consultation";

        const status = document.createElement("span");
        status.className = "mbpha-cal__ev-status";
        status.textContent = event.status || "";

        body.appendChild(name);
        if (status.textContent !== "") {
          body.appendChild(status);
        }

        item.appendChild(time);
        item.appendChild(body);
        list.appendChild(item);
      });
    };

    const render = () => {
      monthEl.textContent = FULL_MONTHS[view.m];
      yearEl.textContent = String(view.y);
      grid.replaceChildren();

      const firstWeekday = new Date(view.y, view.m, 1).getDay();
      const daysInMonth = new Date(view.y, view.m + 1, 0).getDate();
      const prevMonthDays = new Date(view.y, view.m, 0).getDate();

      for (let i = 0; i < firstWeekday; i += 1) {
        const cell = document.createElement("button");
        cell.type = "button";
        cell.className = "mbpha-cal__cell is-other";
        cell.disabled = true;
        cell.tabIndex = -1;
        cell.textContent = String(prevMonthDays - firstWeekday + 1 + i);
        grid.appendChild(cell);
      }

      for (let day = 1; day <= daysInMonth; day += 1) {
        const cell = document.createElement("button");
        cell.type = "button";
        cell.className = "mbpha-cal__cell";
        cell.textContent = String(day);
        cell.setAttribute("aria-label", `${FULL_MONTHS[view.m]} ${day}, ${view.y}`);

        if (view.y === today.y && view.m === today.m && day === today.d) {
          cell.classList.add("is-today");
          cell.setAttribute("aria-current", "date");
        }

        if (view.y === selected.y && view.m === selected.m && day === selected.d) {
          cell.classList.add("is-sel");
          cell.setAttribute("aria-pressed", "true");
        } else {
          cell.setAttribute("aria-pressed", "false");
        }

        const key = calendarDateKey(view.y, view.m, day);
        const dayEvents = grouped[key];

        if (dayEvents && dayEvents.length > 0) {
          const dot = document.createElement("span");
          dot.className = "mbpha-cal__dot";
          dot.setAttribute("aria-hidden", "true");
          if (dayEvents[0].color) {
            dot.style.background = dayEvents[0].color;
          }
          cell.appendChild(dot);
        }

        cell.addEventListener("click", () => {
          selected.y = view.y;
          selected.m = view.m;
          selected.d = day;
          render();
        });

        grid.appendChild(cell);
      }

      const trailing = (7 - ((firstWeekday + daysInMonth) % 7)) % 7;

      for (let i = 0; i < trailing; i += 1) {
        const cell = document.createElement("button");
        cell.type = "button";
        cell.className = "mbpha-cal__cell is-other";
        cell.disabled = true;
        cell.tabIndex = -1;
        cell.textContent = String(i + 1);
        grid.appendChild(cell);
      }

      renderEvents();
    };

    if (prev) {
      prev.addEventListener("click", () => {
        if (view.m === 0) {
          view.m = 11;
          view.y -= 1;
        } else {
          view.m -= 1;
        }
        render();
      });
    }

    if (next) {
      next.addEventListener("click", () => {
        if (view.m === 11) {
          view.m = 0;
          view.y += 1;
        } else {
          view.m += 1;
        }
        render();
      });
    }

    render();
  });
}

function initializeMiniCalendars() {
  const calendars = document.querySelectorAll("[data-mini-calendar]");

  if (calendars.length === 0) {
    return;
  }

  calendars.forEach((calendar) => {
    const section = calendar.closest(".rightbar-section") || calendar.parentElement;
    const label = section ? section.querySelector("[data-calendar-month-label]") : null;
    const prev = section ? section.querySelector("[data-calendar-prev]") : null;
    const next = section ? section.querySelector("[data-calendar-next]") : null;
    const now = getAppNow();
    let year = now.year;
    let month = now.month - 1;

    const render = () => {
      renderMiniCalendarGrid(calendar, year, month, now);
      if (label) {
        label.textContent = `${SHORT_MONTHS[month]} ${year}`;
      }
    };

    if (prev) {
      prev.addEventListener("click", () => {
        month -= 1;
        if (month < 0) {
          month = 11;
          year -= 1;
        }
        render();
      });
    }

    if (next) {
      next.addEventListener("click", () => {
        month += 1;
        if (month > 11) {
          month = 0;
          year += 1;
        }
        render();
      });
    }

    render();
  });
}

function renderMiniCalendarGrid(calendar, year, month, now) {
  const today = now.day;
  const isCurrentMonth = year === now.year && month === now.month - 1;
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const prevMonthDays = new Date(year, month, 0).getDate();
  const headers = ["S", "M", "T", "W", "T", "F", "S"];

  calendar.innerHTML = "";

  headers.forEach((headerLabel) => {
    const header = document.createElement("div");
    header.className = "rightbar-calendar-day is-header";
    header.textContent = headerLabel;
    calendar.appendChild(header);
  });

  for (let i = 0; i < firstDay; i += 1) {
    const day = document.createElement("div");
    day.className = "rightbar-calendar-day is-muted";
    day.textContent = (prevMonthDays - firstDay + i + 1).toString();
    calendar.appendChild(day);
  }

  for (let dayIndex = 1; dayIndex <= daysInMonth; dayIndex += 1) {
    const day = document.createElement("div");
    day.className = "rightbar-calendar-day";
    day.textContent = dayIndex.toString();

    if (isCurrentMonth && dayIndex === today) {
      day.classList.add("is-today");
      day.setAttribute("title", `Today (${now.tz})`);
    }

    calendar.appendChild(day);
  }

  const cells = 7 * 6;
  const currentCells = calendar.children.length;
  const remaining = Math.max(0, cells - currentCells);

  for (let i = 1; i <= remaining; i += 1) {
    const day = document.createElement("div");
    day.className = "rightbar-calendar-day is-muted";
    day.textContent = i.toString();
    calendar.appendChild(day);
  }
}

function initializeDashboardCharts() {
  const chartNodes = document.querySelectorAll("[data-chart]");

  if (chartNodes.length === 0 || typeof window.Chart === "undefined") {
    return;
  }

  chartNodes.forEach((canvas) => {
    const rawConfig = canvas.getAttribute("data-chart") || "";

    if (rawConfig.trim() === "") {
      return;
    }

    let config = null;

    try {
      config = JSON.parse(rawConfig);
    } catch (error) {
      return;
    }

    const context = canvas.getContext("2d");

    if (!context) {
      return;
    }

    if (config && typeof config === "object") {
      const chartType = String(config.type || "").toLowerCase();
      if (chartType === "line" || chartType === "bar") {
        config.options = config.options || {};
        config.options.scales = config.options.scales || {};
        const yScale = config.options.scales.y || {};
        yScale.grid = Object.assign({ color: "#E2E6E7" }, yScale.grid || {});
        yScale.ticks = Object.assign({ color: "#70838A", precision: 0 }, yScale.ticks || {});
        config.options.scales.y = yScale;
        if (config.options.scales.x) {
          config.options.scales.x.ticks = Object.assign(
            { color: "#70838A" },
            config.options.scales.x.ticks || {}
          );
        }
      }
    }

    const shell = canvas.closest("[data-chart-shell]");

    window.requestAnimationFrame(() => {
      const chart = new window.Chart(context, config);
      void chart;

      if (shell) {
        shell.classList.add("is-loaded");
      }
    });
  });
}

function initializeAosAnimations() {
  // Staggered list entrance lives in app.js (initializeStaggeredLists).
}

/**
 * Duplicate-submit protection for Approve / Reject / Approve & Next /
 * Reject & Next on the administrator consultation review workspace.
 *
 * This is a UX guard only. Approval and rejection still POST to the
 * existing backend actions; business logic is not duplicated here.
 */
function initializeConsultationQueueWorkspace() {
  const workspace = document.querySelector("[data-consultation-queue-workspace]");
  if (!(workspace instanceof HTMLElement)) {
    return;
  }

  const forms = workspace.querySelectorAll("form[data-queue-decision]");
  const buttons = [];
  let submitting = false;

  forms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const button = form.querySelector('button[type="submit"]');
    if (button instanceof HTMLButtonElement) {
      if (!button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
      }
      buttons.push(button);
    }

    form.addEventListener("submit", (event) => {
      if (submitting) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      submitting = true;
      lockQueueDecisionButtons(buttons, form);
    });
  });

  window.addEventListener("pageshow", () => {
    submitting = false;
    unlockQueueDecisionButtons(buttons);
  });

  if (window.matchMedia("(max-width: 991.98px)").matches) {
    const detail = workspace.querySelector(".ux-review-workspace__detail .ux-queue-detail");
    if (detail instanceof HTMLElement) {
      detail.scrollIntoView({ block: "start", behavior: "auto" });
    }
  }
}

function lockQueueDecisionButtons(buttons, activeForm) {
  const action = ((activeForm instanceof HTMLFormElement ? activeForm.getAttribute("action") : "") || "").toLowerCase();
  const isReject = action.includes("/reject");
  const loadingLabel = isReject ? "Rejecting…" : "Approving…";

  buttons.forEach((button) => {
    button.disabled = true;
    button.setAttribute("aria-disabled", "true");
    button.classList.add("is-loading");
    if (activeForm instanceof HTMLFormElement && button.form === activeForm) {
      const icon = isReject ? "bi-x-circle" : "bi-check2-circle";
      button.innerHTML = '<i class="bi ' + icon + ' me-1"></i>' + loadingLabel;
    }
  });
}

function unlockQueueDecisionButtons(buttons) {
  buttons.forEach((button) => {
    button.disabled = false;
    button.removeAttribute("aria-disabled");
    button.classList.remove("is-loading");
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
    }
  });
}

function initializeDesktopSidebarToggle() {
  const toggles = document.querySelectorAll("[data-desktop-sidebar-toggle]");
  const shell = document.querySelector(".dashboard-shell");
  const storageKey = "mbpha-dashboard-sidebar-collapsed";

  if (toggles.length === 0 || !shell) {
    return;
  }

  const prefersReducedMotion = () =>
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  let animationTimer = 0;

  const endSidebarAnimation = () => {
    window.clearTimeout(animationTimer);
    shell.classList.remove("dashboard-shell--sidebar-animating");
  };

  const applyCollapsed = (collapsed, animate) => {
    window.clearTimeout(animationTimer);
    if (animate && !prefersReducedMotion()) {
      shell.classList.add("dashboard-shell--sidebar-animating");
      animationTimer = window.setTimeout(endSidebarAnimation, 360);
    } else {
      shell.classList.remove("dashboard-shell--sidebar-animating");
    }
    shell.classList.toggle("dashboard-shell--sidebar-collapsed", collapsed);
    if (collapsed) {
      shell.setAttribute("data-collapsed", "");
    } else {
      shell.removeAttribute("data-collapsed");
    }
    toggles.forEach((toggle) => {
      toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
      toggle.setAttribute(
        "aria-label",
        collapsed ? "Expand dashboard navigation" : "Collapse dashboard navigation"
      );
    });
  };

  try {
    applyCollapsed(window.localStorage.getItem(storageKey) === "1", false);
  } catch (error) {
    applyCollapsed(false, false);
  }

  window.requestAnimationFrame(() => {
    shell.classList.add("dashboard-shell--sidebar-ready");
  });

  shell.addEventListener("transitionend", (event) => {
    const target = event.target;
    const isSidebar = target instanceof HTMLElement && target.classList.contains("dashboard-sidebar");
    if (
      event.propertyName === "flex-basis" ||
      event.propertyName === "--dash-sidebar-track" ||
      (isSidebar && (event.propertyName === "width" || event.propertyName === "flex-basis"))
    ) {
      endSidebarAnimation();
    }
  });

  toggles.forEach((toggle) => {
    toggle.addEventListener("click", () => {
      const collapsed = !shell.classList.contains("dashboard-shell--sidebar-collapsed");
      applyCollapsed(collapsed, true);
      try {
        window.localStorage.setItem(storageKey, collapsed ? "1" : "0");
      } catch (error) {
        // Ignore storage failures in private browsing.
      }
    });
  });
}

function initializeListFilterLoading() {
  const forms = document.querySelectorAll("form[data-list-filter]");
  forms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    form.addEventListener("submit", () => {
      const shell = form.closest(".ux-filter");
      if (shell) {
        shell.classList.add("is-loading");
        shell.setAttribute("aria-busy", "true");
      }
      const button = form.querySelector('button[type="submit"]');
      if (button instanceof HTMLButtonElement) {
        button.disabled = true;
        button.setAttribute("aria-disabled", "true");
        if (!button.dataset.originalHtml) {
          button.dataset.originalHtml = button.innerHTML;
        }
        button.innerHTML = '<span class="ux-loading__spinner" aria-hidden="true"></span> Loading…';
      }
    });
  });
}

function initializeNotificationToasts() {
  const stack = document.querySelector("[data-notification-toasts]");
  if (!(stack instanceof HTMLElement)) {
    return;
  }

  const storageKey = "mbpha-notification-toasts-dismissed";
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  let dismissed = [];
  try {
    const stored = JSON.parse(window.sessionStorage.getItem(storageKey) || "[]");
    if (Array.isArray(stored)) {
      dismissed = stored.map((id) => String(id));
    }
  } catch (error) {
    dismissed = [];
  }

  const persist = (id) => {
    if (id === "" || dismissed.includes(id)) {
      return;
    }
    dismissed.push(id);
    try {
      window.sessionStorage.setItem(storageKey, JSON.stringify(dismissed.slice(-40)));
    } catch (error) {
      // Ignore private-browsing storage failures.
    }
  };

  const removeToast = (toast) => {
    if (!(toast instanceof HTMLElement) || toast.classList.contains("is-leaving")) {
      return;
    }
    persist(String(toast.getAttribute("data-toast-id") || ""));
    toast.classList.add("is-leaving");
    window.setTimeout(() => {
      toast.remove();
      if (!stack.querySelector("[data-notification-toast]")) {
        stack.remove();
      }
    }, 280);
  };

  const toasts = Array.from(stack.querySelectorAll("[data-notification-toast]"));
  let visibleIndex = 0;
  toasts.forEach((toast) => {
    const id = String(toast.getAttribute("data-toast-id") || "");
    if (id !== "" && dismissed.includes(id)) {
      toast.remove();
      return;
    }

    toast.style.setProperty("--d", (0.2 + visibleIndex * 0.9).toFixed(1) + "s");
    visibleIndex += 1;

    const bar = toast.querySelector(".notification-toast__bar");
    if (bar instanceof HTMLElement && !reducedMotion) {
      bar.addEventListener("animationend", (event) => {
        if (event.animationName === "notification-toast-drain") {
          removeToast(toast);
        }
      });
    }
  });

  if (!stack.querySelector("[data-notification-toast]")) {
    stack.remove();
  }
}

function initializeUxConfirmModal() {
  const modalEl = document.getElementById("uxConfirmModal");
  if (!(modalEl instanceof HTMLElement)) {
    return;
  }

  const titleEl = document.getElementById("uxConfirmModalLabel");
  const bodyEl = document.getElementById("uxConfirmModalBody");
  const hintEl = document.getElementById("uxConfirmModalHint");
  const submitEl = document.getElementById("uxConfirmModalSubmit");
  if (!(submitEl instanceof HTMLButtonElement)) {
    return;
  }

  let pendingForm = null;
  const modal = window.bootstrap && typeof window.bootstrap.Modal === "function"
    ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
    : null;

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (form.id === "permanentDeleteForm") {
      return;
    }
    if (form.dataset.confirmSkip === "1") {
      delete form.dataset.confirmSkip;
      return;
    }

    const title = (form.getAttribute("data-confirm-title") || "").trim();
    if (title === "") {
      return;
    }

    event.preventDefault();
    pendingForm = form;

    if (titleEl) {
      titleEl.textContent = title;
    }
    if (bodyEl) {
      bodyEl.textContent = form.getAttribute("data-confirm-body") || "Please confirm this action.";
    }
    if (hintEl) {
      hintEl.textContent = form.getAttribute("data-confirm-hint") || "You can cancel if you are not sure.";
    }

    const tone = (form.getAttribute("data-confirm-tone") || "danger").trim();
    submitEl.textContent = form.getAttribute("data-confirm-action") || "Confirm";
    submitEl.className = tone === "primary" ? "btn btn-primary" : "btn btn-danger";

    if (modal) {
      modal.show();
    } else if (window.confirm(title + "\n\n" + (bodyEl ? bodyEl.textContent : ""))) {
      form.dataset.confirmSkip = "1";
      form.submit();
    }
  });

  submitEl.addEventListener("click", () => {
    if (!(pendingForm instanceof HTMLFormElement)) {
      return;
    }
    const form = pendingForm;
    pendingForm = null;
    form.dataset.confirmSkip = "1";
    if (modal) {
      modal.hide();
    }
    form.submit();
  });

  modalEl.addEventListener("hidden.bs.modal", () => {
    pendingForm = null;
  });
}

function initializePermanentDeleteModal() {
  const modalEl = document.getElementById("permanentDeleteModal");
  const form = document.getElementById("permanentDeleteForm");
  const phraseInput = document.getElementById("confirmation_phrase");
  const submit = document.getElementById("permanentDeleteSubmit");
  const nameEl = document.getElementById("permanentDeleteName");
  const titleEl = document.getElementById("permanentDeleteModalLabel");
  const idFields = document.getElementById("permanentDeleteIdFields");
  if (
    !(modalEl instanceof HTMLElement) ||
    !(form instanceof HTMLFormElement) ||
    !(phraseInput instanceof HTMLInputElement) ||
    !(submit instanceof HTMLButtonElement)
  ) {
    return;
  }

  const expected = (phraseInput.getAttribute("data-confirm-phrase") || "DELETE USER").trim();
  const bulkUrl = form.getAttribute("data-bulk-url") || "";

  const clearIdFields = () => {
    if (idFields instanceof HTMLElement) {
      idFields.replaceChildren();
    }
  };

  const syncSubmit = () => {
    submit.disabled = phraseInput.value.trim() !== expected;
  };

  phraseInput.addEventListener("input", syncSubmit);
  phraseInput.addEventListener("keyup", syncSubmit);
  syncSubmit();

  modalEl.addEventListener("show.bs.modal", (event) => {
    const trigger = event.relatedTarget;
    clearIdFields();

    if (trigger instanceof HTMLElement && trigger.getAttribute("data-bulk-delete") === "1") {
      const selected = Array.from(document.querySelectorAll(".user-select-box:checked"));
      if (selected.length === 0) {
        event.preventDefault();
        return;
      }

      const url = trigger.getAttribute("data-bulk-url") || bulkUrl;
      if (url !== "") {
        form.setAttribute("action", url);
      }
      if (titleEl) {
        titleEl.textContent = "Permanently delete selected users?";
      }
      if (nameEl) {
        nameEl.textContent =
          selected.length === 1 ? "1 selected account" : selected.length + " selected accounts";
      }
      selected.forEach((input) => {
        if (!(input instanceof HTMLInputElement) || !(idFields instanceof HTMLElement)) {
          return;
        }
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = "user_ids[]";
        hidden.value = input.value;
        idFields.appendChild(hidden);
      });
    } else if (trigger instanceof HTMLElement) {
      const url = trigger.getAttribute("data-delete-url") || "";
      const name = trigger.getAttribute("data-delete-name") || "this user";
      if (url !== "") {
        form.setAttribute("action", url);
      }
      if (titleEl) {
        titleEl.textContent = "Permanently delete this user?";
      }
      if (nameEl) {
        nameEl.textContent = name;
      }
    }

    phraseInput.value = "";
    const password = form.querySelector("#admin_password");
    if (password instanceof HTMLInputElement) {
      password.value = "";
    }
    syncSubmit();
  });
}

function initializeUserBulkSelection() {
  const boxes = Array.from(document.querySelectorAll(".user-select-box"));
  const selectAll = document.getElementById("userSelectAll");
  const bulkButton = document.getElementById("userBulkDeleteButton");
  const countEl = document.getElementById("userBulkCount");
  if (boxes.length === 0 || !(bulkButton instanceof HTMLButtonElement)) {
    return;
  }

  const sync = () => {
    const selected = boxes.filter((box) => box instanceof HTMLInputElement && box.checked);
    bulkButton.disabled = selected.length === 0;
    if (countEl) {
      countEl.textContent = selected.length === 1 ? "1 selected" : selected.length + " selected";
    }
    if (selectAll instanceof HTMLInputElement) {
      const enabled = boxes.filter((box) => box instanceof HTMLInputElement && !box.disabled);
      selectAll.checked = enabled.length > 0 && enabled.every((box) => box.checked);
      selectAll.indeterminate = selected.length > 0 && !selectAll.checked;
    }
  };

  boxes.forEach((box) => box.addEventListener("change", sync));
  if (selectAll instanceof HTMLInputElement) {
    selectAll.addEventListener("change", () => {
      boxes.forEach((box) => {
        if (box instanceof HTMLInputElement && !box.disabled) {
          box.checked = selectAll.checked;
        }
      });
      sync();
    });
  }
  sync();
}

function initializeNotificationBulkSelection() {
  const bulkForm = document.getElementById("notificationBulkForm");
  if (!(bulkForm instanceof HTMLFormElement)) {
    return;
  }

  const submit = bulkForm.querySelector("[data-bulk-submit]");
  if (!(submit instanceof HTMLButtonElement)) {
    return;
  }

  const sync = () => {
    const selected = bulkForm.querySelectorAll('input[name="notification_ids[]"]:checked');
    submit.disabled = selected.length === 0;
  };

  bulkForm.addEventListener("change", sync);
  sync();
}
