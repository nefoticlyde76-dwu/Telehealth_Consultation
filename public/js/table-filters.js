(function () {
  "use strict";

  const SEARCH_DELAY_MS = 320;
  const searchTimers = new WeakMap();
  let activeRequest = null;

  document.documentElement.classList.add("js-table-filters");

  document.addEventListener("submit", onFormSubmit);
  document.addEventListener("input", onControlInput);
  document.addEventListener("change", onControlChange);
  document.addEventListener("click", onDelegatedClick);
  window.addEventListener("popstate", onPopState);

  function onFormSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute("data-table-filter-form")) {
      return;
    }
    event.preventDefault();
    applyTableFilter(form);
  }

  function onControlInput(event) {
    const field = event.target;
    if (!(field instanceof HTMLInputElement)) {
      return;
    }
    const form = resolveFilterForm(field);
    if (!form) {
      return;
    }
    if (field.matches("[data-date-preset], select, input[type='date']")) {
      return;
    }
    const isSearch = field.type === "search" || field.classList.contains("ux-table-toolbar__input");
    if (!isSearch && !field.classList.contains("ux-th__control")) {
      return;
    }
    scheduleApply(form, SEARCH_DELAY_MS);
  }

  function onControlChange(event) {
    const field = event.target;
    if (!(field instanceof HTMLElement)) {
      return;
    }
    const form = resolveFilterForm(field);
    if (!form) {
      return;
    }
    if (field.matches("[data-date-preset]")) {
      syncDateRange(field);
      if (field instanceof HTMLSelectElement && field.value === "custom") {
        return;
      }
    }
    if (
      field.matches("select, input[type='date']") ||
      field.classList.contains("ux-th__control") ||
      field.classList.contains("ux-table-toolbar__select")
    ) {
      applyTableFilter(form);
    }
  }

  function onDelegatedClick(event) {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const root = target.closest("[data-table-filter]");
    if (!(root instanceof HTMLElement)) {
      return;
    }
    const link = target.closest("a");
    if (!(link instanceof HTMLAnchorElement)) {
      return;
    }
    if (link.target === "_blank" || link.hasAttribute("download")) {
      return;
    }
    const isClear = link.classList.contains("ux-table-toolbar__clear");
    const isPager = !!link.closest(".ux-pagination, .admin-pagination");
    if (!isClear && !isPager) {
      return;
    }
    const href = link.getAttribute("href") || "";
    if (href === "" || href === "#" || link.classList.contains("disabled")) {
      return;
    }
    event.preventDefault();
    fetchAndSwap(root, link.href, isPager);
  }

  function onPopState(event) {
    if (!event.state || !event.state.tableFilter) {
      return;
    }
    const root = document.querySelector("[data-table-filter]");
    if (!(root instanceof HTMLElement)) {
      return;
    }
    fetchAndSwap(root, window.location.href, true);
  }

  function resolveFilterForm(field) {
    const formId = field.getAttribute("form");
    if (formId) {
      const byId = document.getElementById(formId);
      if (byId instanceof HTMLFormElement && byId.hasAttribute("data-table-filter-form")) {
        return byId;
      }
    }
    const closest = field.closest("form[data-table-filter-form]");
    return closest instanceof HTMLFormElement ? closest : null;
  }

  function findRootForForm(form) {
    const byAttr = document.querySelector('[data-table-filter="' + cssEscape(form.id) + '"]');
    if (byAttr instanceof HTMLElement) {
      return byAttr;
    }
    const closest = form.closest("[data-table-filter]");
    return closest instanceof HTMLElement ? closest : null;
  }

  function scheduleApply(form, delay) {
    const existing = searchTimers.get(form);
    if (existing) {
      window.clearTimeout(existing);
    }
    searchTimers.set(
      form,
      window.setTimeout(() => {
        searchTimers.delete(form);
        applyTableFilter(form);
      }, delay)
    );
  }

  function applyTableFilter(form) {
    const root = findRootForForm(form);
    if (!root) {
      form.submit();
      return;
    }
    fetchAndSwap(root, buildFilterUrl(form), false);
  }

  function buildFilterUrl(form) {
    const action = form.getAttribute("action") || window.location.pathname;
    const params = new URLSearchParams();
    const fields = collectFilterFields(form);

    fields.forEach((field) => {
      const name = field.getAttribute("name") || "";
      if (name === "" || name === "page") {
        return;
      }
      const stringValue = (field.value || "").trim();
      const keepEmpty = field.hasAttribute("data-keep-empty");
      if (stringValue === "" && !keepEmpty) {
        return;
      }
      if (stringValue === "0" && (name === "doctor_id" || name === "user_id" || name === "patient_id")) {
        return;
      }
      params.set(name, stringValue);
    });

    const query = params.toString();
    return query === "" ? action : action + "?" + query;
  }

  function collectFilterFields(form) {
    const fields = [];
    const seen = new Set();
    const add = (field) => {
      if (
        !(field instanceof HTMLInputElement) &&
        !(field instanceof HTMLSelectElement) &&
        !(field instanceof HTMLTextAreaElement)
      ) {
        return;
      }
      if (field.disabled || field.type === "submit" || field.type === "button" || field.type === "reset") {
        return;
      }
      const key = field.name + "\0" + field.id;
      if (seen.has(key)) {
        return;
      }
      seen.add(key);
      fields.push(field);
    };

    Array.from(form.elements).forEach(add);
    if (form.id) {
      document.querySelectorAll('[form="' + cssEscape(form.id) + '"]').forEach(add);
    }
    return fields;
  }

  function syncDateRange(preset) {
    const wrapper = preset.closest(".ux-th, .ux-table-toolbar, .ux-th-date, th");
    const range = wrapper ? wrapper.querySelector("[data-date-range]") : null;
    if (!(range instanceof HTMLElement)) {
      return;
    }
    const isCustom = preset instanceof HTMLSelectElement && preset.value === "custom";
    range.classList.toggle("d-none", !isCustom);
    if (isCustom) {
      range.removeAttribute("hidden");
    } else {
      range.setAttribute("hidden", "");
    }
    range.querySelectorAll("input").forEach((input) => {
      if (input instanceof HTMLInputElement) {
        input.disabled = !isCustom;
      }
    });
  }

  function captureFocus(root) {
    const active = document.activeElement;
    if (!(active instanceof HTMLInputElement) && !(active instanceof HTMLSelectElement)) {
      return null;
    }
    if (!root.contains(active) && active.getAttribute("form") !== root.getAttribute("data-table-filter")) {
      return null;
    }
    return {
      name: active.getAttribute("name") || "",
      id: active.id || "",
      start: typeof active.selectionStart === "number" ? active.selectionStart : null,
      end: typeof active.selectionEnd === "number" ? active.selectionEnd : null,
    };
  }

  function restoreFocus(root, snapshot) {
    if (!snapshot) {
      return;
    }
    let next = snapshot.id ? root.querySelector("#" + cssEscape(snapshot.id)) : null;
    if (!(next instanceof HTMLElement) && snapshot.name) {
      next = root.querySelector('[name="' + cssEscape(snapshot.name) + '"]');
    }
    if (!(next instanceof HTMLInputElement) && !(next instanceof HTMLSelectElement)) {
      return;
    }
    next.focus();
    if (
      next instanceof HTMLInputElement &&
      snapshot.start !== null &&
      snapshot.end !== null &&
      typeof next.setSelectionRange === "function"
    ) {
      try {
        next.setSelectionRange(snapshot.start, snapshot.end);
      } catch (error) {
        // Some input types do not support selection ranges.
      }
    }
  }

  function fetchAndSwap(root, url, replaceState) {
    if (activeRequest) {
      activeRequest.abort();
    }
    const controller = new AbortController();
    activeRequest = controller;
    const formId = root.getAttribute("data-table-filter") || "";
    const focus = captureFocus(root);

    root.classList.add("is-loading");
    root.setAttribute("aria-busy", "true");

    fetch(url, {
      method: "GET",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "text/html",
      },
      credentials: "same-origin",
      signal: controller.signal,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Filter request failed");
        }
        return response.text();
      })
      .then((html) => {
        const doc = new DOMParser().parseFromString(html, "text/html");
        const next = formId
          ? doc.querySelector('[data-table-filter="' + cssEscape(formId) + '"]')
          : doc.querySelector("[data-table-filter]");
        if (!(next instanceof HTMLElement)) {
          window.location.href = url;
          return;
        }
        root.replaceWith(next);
        if (replaceState) {
          history.replaceState({ tableFilter: true }, "", url);
        } else {
          history.pushState({ tableFilter: true }, "", url);
        }
        restoreFocus(next, focus);
        if (typeof window.mbphaAfterTableFilterSwap === "function") {
          window.mbphaAfterTableFilterSwap(next);
        }
      })
      .catch((error) => {
        if (error && error.name === "AbortError") {
          return;
        }
        window.location.href = url;
      })
      .finally(() => {
        if (activeRequest === controller) {
          activeRequest = null;
        }
      });
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === "function") {
      return window.CSS.escape(value);
    }
    return String(value).replace(/[^a-zA-Z0-9_\-]/g, "\\$&");
  }

  if (document.querySelector("[data-table-filter]") && (!history.state || !history.state.tableFilter)) {
    history.replaceState({ tableFilter: true }, "", window.location.href);
  }
})();
