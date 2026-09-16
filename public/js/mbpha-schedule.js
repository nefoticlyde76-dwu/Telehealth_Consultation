document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-mbpha-avail]");
  if (root instanceof HTMLElement) {
    initializeMbphaAvailability(root);
  }
});

function initializeMbphaAvailability(root) {
  const modalEl = document.getElementById("availEditorModal");
  if (!(modalEl instanceof HTMLElement) || !window.bootstrap) {
    return;
  }

  const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
  const form = modalEl.querySelector("[data-avail-form]");
  const deleteForm = modalEl.querySelector("[data-avail-delete-form]");
  const title = modalEl.querySelector("#availEditorModalLabel");
  const help = modalEl.querySelector("[data-avail-modal-help]");
  const bookedNote = modalEl.querySelector("[data-avail-booked-note]");
  const foreignNote = modalEl.querySelector("[data-avail-foreign-note]");
  const dateInput = modalEl.querySelector("#avail-modal-date");
  const startInput = modalEl.querySelector("#avail-modal-start");
  const endInput = modalEl.querySelector("#avail-modal-end");
  const notesInput = modalEl.querySelector("#avail-modal-notes");
  const saveBtn = modalEl.querySelector("[data-avail-save]");
  const deleteBtn = modalEl.querySelector("[data-avail-delete]");
  const fullFormLink = modalEl.querySelector("[data-avail-full-form]");
  const createAction = form instanceof HTMLFormElement ? form.getAttribute("data-create-action") || form.getAttribute("action") || "" : "";
  const editPattern = form instanceof HTMLFormElement ? form.getAttribute("data-edit-action") || "" : "";
  const deletePattern = form instanceof HTMLFormElement ? form.getAttribute("data-delete-action") || "" : "";
  const withId = (pattern, id) => pattern.replace("__ID__", String(id));

  const setDisabled = (disabled) => {
    [dateInput, startInput, endInput, notesInput].forEach((field) => {
      if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
        field.disabled = disabled;
      }
    });
    if (saveBtn instanceof HTMLButtonElement) {
      saveBtn.classList.toggle("d-none", disabled);
      saveBtn.disabled = disabled;
    }
  };

  const openCreate = (values) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    form.action = createAction;
    if (title) {
      title.textContent = "Add Availability";
    }
    if (help) {
      help.textContent = "Enter any start and end time. The schedule will place this block using those times.";
    }
    if (bookedNote) {
      bookedNote.classList.add("d-none");
    }
    if (foreignNote) {
      foreignNote.classList.add("d-none");
    }
    if (dateInput instanceof HTMLInputElement) {
      dateInput.value = values.date || "";
    }
    if (startInput instanceof HTMLInputElement) {
      startInput.value = values.start || "";
    }
    if (endInput instanceof HTMLInputElement) {
      endInput.value = values.end || "";
    }
    if (notesInput instanceof HTMLTextAreaElement) {
      notesInput.value = "";
    }
    if (deleteBtn instanceof HTMLElement) {
      deleteBtn.classList.add("d-none");
    }
    if (deleteForm instanceof HTMLFormElement) {
      deleteForm.action = "#";
    }
    if (fullFormLink instanceof HTMLAnchorElement) {
      const url = new URL(fullFormLink.getAttribute("data-base-href") || createAction, window.location.origin);
      url.searchParams.set("week", form.querySelector('[name="return_week"]')?.value || "");
      if (values.date) {
        url.searchParams.set("date", values.date);
      }
      if (values.start) {
        url.searchParams.set("start", values.start);
      }
      if (values.end) {
        url.searchParams.set("end", values.end);
      }
      fullFormLink.href = url.pathname + url.search;
      fullFormLink.classList.remove("d-none");
    }
    setDisabled(false);
    modal.show();
  };

  const openEdit = (block) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const id = block.getAttribute("data-id") || "";
    const owned = block.getAttribute("data-owned") !== "0";
    const locked = block.getAttribute("data-locked") === "1" || !owned;
    const status = block.getAttribute("data-status") || "Available";
    const doctorName = block.getAttribute("data-doctor-name") || "another doctor";
    const specialization = block.getAttribute("data-specialization") || "";
    form.action = owned ? withId(editPattern, id) : createAction;
    if (title) {
      title.textContent = !owned
        ? "Colleague availability"
        : locked
          ? "View Availability"
          : "Edit Availability";
    }
    if (help) {
      help.textContent = !owned
        ? (specialization !== ""
            ? `${doctorName} · ${specialization}. View only — you cannot change another doctor's slot.`
            : `${doctorName}. View only — you cannot change another doctor's slot.`)
        : locked
          ? "This availability is already on the schedule."
          : "Change the start or end time. The block will move to match.";
    }
    if (bookedNote) {
      bookedNote.classList.toggle("d-none", owned && status !== "Booked");
    }
    if (foreignNote) {
      foreignNote.classList.toggle("d-none", owned);
    }
    if (dateInput instanceof HTMLInputElement) {
      dateInput.value = block.getAttribute("data-date") || "";
    }
    if (startInput instanceof HTMLInputElement) {
      startInput.value = block.getAttribute("data-start") || "";
    }
    if (endInput instanceof HTMLInputElement) {
      endInput.value = block.getAttribute("data-end") || "";
    }
    if (notesInput instanceof HTMLTextAreaElement) {
      notesInput.value = block.getAttribute("data-notes") || "";
    }
    if (deleteBtn instanceof HTMLElement) {
      deleteBtn.classList.toggle("d-none", locked || id === "" || !owned);
    }
    if (deleteForm instanceof HTMLFormElement) {
      deleteForm.action = owned && id !== "" ? withId(deletePattern, id) : "#";
    }
    if (fullFormLink instanceof HTMLAnchorElement) {
      fullFormLink.href = withId(editPattern, id);
      fullFormLink.classList.toggle("d-none", locked || !owned);
    }
    setDisabled(locked);
    modal.show();
  };

  const addBtn = root.querySelector("[data-avail-add]");
  const todayDate = root.getAttribute("data-today") || "";
  const remainingTodayLane = root.querySelector(
    ".mbpha-avail__cal-day.is-today-col [data-avail-lane]:not([disabled]):not([data-ended])"
  );
  const todayLane = root.querySelector(".mbpha-avail__cal-day.is-today-col [data-avail-lane]:not([disabled])");
  const fallbackLane = root.querySelector("[data-avail-lane]:not([disabled])");
  if (addBtn) {
    addBtn.addEventListener("click", () => {
      const source =
        remainingTodayLane instanceof HTMLElement
          ? remainingTodayLane
          : todayLane instanceof HTMLElement
            ? todayLane
            : fallbackLane;
      openCreate({
        date: todayDate || (source instanceof HTMLElement ? source.getAttribute("data-date") || "" : ""),
        start: source instanceof HTMLElement ? source.getAttribute("data-start") || "08:00" : "08:00",
        end: source instanceof HTMLElement ? source.getAttribute("data-end") || "08:30" : "08:30",
      });
    });
  }

  root.querySelectorAll("[data-avail-lane]").forEach((lane) => {
    lane.addEventListener("click", (event) => {
      event.preventDefault();
      if (!(lane instanceof HTMLElement) || lane.disabled) {
        return;
      }
      openCreate({
        date: lane.getAttribute("data-date") || "",
        start: lane.getAttribute("data-start") || "",
        end: lane.getAttribute("data-end") || "",
      });
    });
  });

  root.querySelectorAll("[data-avail-block]").forEach((block) => {
    block.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (block instanceof HTMLElement) {
        openEdit(block);
      }
    });
  });
}
