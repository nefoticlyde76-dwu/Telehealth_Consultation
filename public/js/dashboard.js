document.addEventListener("DOMContentLoaded", () => {
  initializeDashboardDateTime();
  initializeMiniCalendars();
  initializeDashboardCharts();
  initializeAosAnimations();
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

function initializeMiniCalendars() {
  const calendars = document.querySelectorAll("[data-mini-calendar]");

  if (calendars.length === 0) {
    return;
  }

  calendars.forEach((calendar) => {
    const now = getAppNow();
    const year = now.year;
    const month = now.month - 1; // 0-11 for Date constructor
    const today = now.day;
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const prevMonthDays = new Date(year, month, 0).getDate();
    const headers = ["S", "M", "T", "W", "T", "F", "S"];

    calendar.innerHTML = "";

    headers.forEach((label) => {
      const header = document.createElement("div");
      header.className = "rightbar-calendar-day is-header";
      header.textContent = label;
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

      if (dayIndex === today) {
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
  });
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
  if (typeof window.AOS === "undefined") {
    return;
  }

  window.AOS.init({
    once: true,
    duration: 650,
    easing: "ease-out-cubic",
    offset: 80,
  });
}
