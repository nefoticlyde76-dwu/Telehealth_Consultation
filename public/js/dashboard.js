document.addEventListener("DOMContentLoaded", () => {
  initializeDashboardDateTime();
  initializeMiniCalendars();
  initializeDashboardCharts();
  initializeAosAnimations();
});

function initializeDashboardDateTime() {
  const targets = document.querySelectorAll("[data-dashboard-datetime]");

  if (targets.length === 0) {
    return;
  }

  const formatValue = (mode) => {
    const now = new Date();

    if (mode === "date") {
      return now.toLocaleDateString(undefined, { weekday: "short", day: "2-digit", month: "short" });
    }

    return now.toLocaleString(undefined, {
      weekday: "short",
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const render = () => {
    targets.forEach((element) => {
      const mode = element.getAttribute("data-dashboard-datetime") || "full";
      element.textContent = formatValue(mode);
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
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const today = now.getDate();
    const firstDay = new Date(year, month, 1);
    const startDay = firstDay.getDay();
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

    for (let i = 0; i < startDay; i += 1) {
      const day = document.createElement("div");
      day.className = "rightbar-calendar-day is-muted";
      day.textContent = (prevMonthDays - startDay + i + 1).toString();
      calendar.appendChild(day);
    }

    for (let dayIndex = 1; dayIndex <= daysInMonth; dayIndex += 1) {
      const day = document.createElement("div");
      day.className = "rightbar-calendar-day";
      day.textContent = dayIndex.toString();

      if (dayIndex === today) {
        day.classList.add("is-today");
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
