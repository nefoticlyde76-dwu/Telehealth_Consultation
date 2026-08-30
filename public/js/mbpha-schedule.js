document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-mbpha-avail]");
  if (root instanceof HTMLFormElement) {
    initializeMbphaAvailability(root);
  }
});

function initializeMbphaAvailability(form) {
  const cells = Array.from(form.querySelectorAll("[data-avail-cell]"));
  const slotBox = form.querySelector("[data-avail-slots]");
  const dirtyLabel = form.querySelector("[data-avail-dirty]");
  const availableCount = form.querySelector('[data-avail-count="available"]');
  const rangeDay = form.querySelector("[data-avail-range-day]");
  const rangeStart = form.querySelector("[data-avail-range-start]");
  const rangeEnd = form.querySelector("[data-avail-range-end]");
  const copyFrom = form.querySelector("[data-avail-copy-from]");
  const copyTo = form.querySelector("[data-avail-copy-to]");
  const clearDay = form.querySelector("[data-avail-clear-day]");

  let paintActive = false;
  let paintSelect = true;
  let pointerHandled = false;

  const isLocked = (cell) => cell.hasAttribute("data-locked") || cell.disabled;

  const isSelected = (cell) => cell.getAttribute("aria-pressed") === "true";

  const cellLabel = (cell, state) => {
    const day = cell.getAttribute("data-day") || "";
    const start = cell.getAttribute("data-start-label") || "";
    const end = cell.getAttribute("data-end-label") || "";
    return `${day}, ${start} to ${end}, ${state}`;
  };

  const renderCell = (cell, selected) => {
    if (isLocked(cell)) {
      return;
    }

    const saved = cell.getAttribute("data-saved") === "1";
    const dirty = selected !== saved;
    cell.setAttribute("aria-pressed", selected ? "true" : "false");
    cell.setAttribute("data-state", selected ? "available" : "empty");
    cell.classList.toggle("is-available", selected);
    cell.classList.toggle("is-empty", !selected);
    cell.classList.toggle("is-dirty", dirty);
    cell.setAttribute("aria-label", cellLabel(cell, selected ? "available" : "unavailable"));

    if (selected) {
      cell.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i><span>Available</span>';
    } else {
      cell.innerHTML = '<span class="visually-hidden">Unavailable</span>';
    }
  };

  const toggleCell = (cell, forceSelected) => {
    if (isLocked(cell)) {
      return;
    }
    const next = typeof forceSelected === "boolean" ? forceSelected : !isSelected(cell);
    renderCell(cell, next);
    syncDirty();
  };

  const cellsForDate = (date) => cells.filter((cell) => cell.getAttribute("data-date") === date);

  const timeValue = (value) => String(value || "").padEnd(5, "0");

  const applyRange = () => {
    if (!(rangeDay instanceof HTMLSelectElement) || !(rangeStart instanceof HTMLSelectElement) || !(rangeEnd instanceof HTMLSelectElement)) {
      return;
    }

    const date = rangeDay.value;
    const start = timeValue(rangeStart.value);
    const end = timeValue(rangeEnd.value);
    if (!date || !start || !end || end <= start) {
      window.alert("Choose a day and an end time later than the start time.");
      return;
    }

    cellsForDate(date).forEach((cell) => {
      const cellStart = timeValue(cell.getAttribute("data-start"));
      const cellEnd = timeValue(cell.getAttribute("data-end"));
      if (cellStart >= start && cellEnd <= end) {
        toggleCell(cell, true);
      }
    });
  };

  const copyDaySchedule = () => {
    if (!(copyFrom instanceof HTMLSelectElement) || !(copyTo instanceof HTMLSelectElement)) {
      return;
    }

    const fromDate = copyFrom.value;
    const toDate = copyTo.value;
    if (!fromDate || !toDate || fromDate === toDate) {
      window.alert("Choose two different days to copy availability.");
      return;
    }

    const sourceIsOn = (cell) => {
      const state = cell.getAttribute("data-state") || "";
      return isSelected(cell) || state === "available" || state === "booked" || state === "custom";
    };

    const source = new Map(
      cellsForDate(fromDate).map((cell) => [cell.getAttribute("data-start"), sourceIsOn(cell)])
    );

    cellsForDate(toDate).forEach((cell) => {
      const shouldSelect = Boolean(source.get(cell.getAttribute("data-start")));
      toggleCell(cell, shouldSelect);
    });
  };

  const clearOneDay = () => {
    if (!(clearDay instanceof HTMLSelectElement)) {
      return;
    }
    cellsForDate(clearDay.value).forEach((cell) => toggleCell(cell, false));
  };

  const clearWeek = () => {
    cells.forEach((cell) => toggleCell(cell, false));
  };

  const syncDirty = () => {
    let dirty = false;
    let selected = 0;

    cells.forEach((cell) => {
      if (isSelected(cell)) {
        selected += 1;
      }
      if (!isLocked(cell) && isSelected(cell) !== (cell.getAttribute("data-saved") === "1")) {
        dirty = true;
      }
    });

    if (availableCount) {
      availableCount.textContent = String(selected);
    }
    if (dirtyLabel) {
      dirtyLabel.textContent = dirty ? "Unsaved" : "Saved";
    }
  };

  const writeHiddenSlots = () => {
    if (!slotBox) {
      return;
    }

    slotBox.innerHTML = "";
    cells.forEach((cell) => {
      if (!isSelected(cell)) {
        return;
      }
      const date = cell.getAttribute("data-date") || "";
      const start = cell.getAttribute("data-start") || "";
      if (date === "" || start === "") {
        return;
      }
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "slots[]";
      input.value = `${date}|${start}`;
      slotBox.appendChild(input);
    });
  };

  cells.forEach((cell) => {
    cell.addEventListener("click", (event) => {
      event.preventDefault();
      if (pointerHandled) {
        pointerHandled = false;
        return;
      }
      toggleCell(cell);
    });

    cell.addEventListener("pointerdown", (event) => {
      if (event.pointerType === "mouse" && event.button !== 0) {
        return;
      }
      if (isLocked(cell)) {
        return;
      }
      paintActive = true;
      pointerHandled = true;
      paintSelect = !isSelected(cell);
      cell.classList.add("is-paint");
      toggleCell(cell, paintSelect);
    });

    cell.addEventListener("keydown", (event) => {
      const key = event.key;
      if (!["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight"].includes(key)) {
        return;
      }
      event.preventDefault();
      const date = cell.getAttribute("data-date");
      const start = cell.getAttribute("data-start");
      const dates = [...new Set(cells.map((item) => item.getAttribute("data-date")))];
      const times = [...new Set(cells.map((item) => item.getAttribute("data-start")))];
      const dateIndex = dates.indexOf(date);
      const timeIndex = times.indexOf(start);
      let nextDate = dateIndex;
      let nextTime = timeIndex;
      if (key === "ArrowLeft") {
        nextDate -= 1;
      } else if (key === "ArrowRight") {
        nextDate += 1;
      } else if (key === "ArrowUp") {
        nextTime -= 1;
      } else if (key === "ArrowDown") {
        nextTime += 1;
      }
      const next = cells.find(
        (item) =>
          item.getAttribute("data-date") === dates[nextDate] &&
          item.getAttribute("data-start") === times[nextTime]
      );
      if (next) {
        next.focus();
      }
    });
  });

  document.addEventListener("pointermove", (event) => {
    if (!paintActive) {
      return;
    }
    const hovered = document.elementFromPoint(event.clientX, event.clientY);
    const cell = hovered instanceof Element ? hovered.closest("[data-avail-cell]") : null;
    if (cell instanceof HTMLElement && form.contains(cell)) {
      cell.classList.add("is-paint");
      toggleCell(cell, paintSelect);
    }
  });

  document.addEventListener("pointerup", () => {
    paintActive = false;
    window.setTimeout(() => {
      pointerHandled = false;
    }, 0);
    cells.forEach((cell) => cell.classList.remove("is-paint"));
  });

  const applyRangeBtn = form.querySelector("[data-avail-apply-range]");
  if (applyRangeBtn) {
    applyRangeBtn.addEventListener("click", applyRange);
  }
  const copyBtn = form.querySelector("[data-avail-copy-day]");
  if (copyBtn) {
    copyBtn.addEventListener("click", copyDaySchedule);
  }
  const clearOneBtn = form.querySelector("[data-avail-clear-one]");
  if (clearOneBtn) {
    clearOneBtn.addEventListener("click", clearOneDay);
  }
  const clearWeekBtn = form.querySelector("[data-avail-clear-week]");
  if (clearWeekBtn) {
    clearWeekBtn.addEventListener("click", clearWeek);
  }

  form.addEventListener("submit", () => {
    writeHiddenSlots();
  });

  syncDirty();
}
