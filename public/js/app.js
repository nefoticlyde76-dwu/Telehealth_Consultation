document.addEventListener("DOMContentLoaded", () => {
  initializeBootstrapValidation();
  initializePasswordStrength();
  initializePasswordConfirmation();
  initializePasswordVisibility();
  initializeDateOfBirthLimit();
  initializeCurrentYear();
  initializeRevealAnimations();
  initializeCounters();
  initializePageTransitions();
  initializeStaggeredLists();
  initializePublicNavDrawer();
  initializeSlotExpiration();
  initializeComplaintImageUpload();
});

function initializeSlotExpiration() {
  const serverNowRaw = document.body instanceof HTMLElement ? document.body.getAttribute("data-server-now") || "" : "";
  const serverNowMs = Date.parse(serverNowRaw);
  if (!Number.isFinite(serverNowMs)) {
    return;
  }

  const startedAt = performance.now();
  const currentServerMs = () => serverNowMs + (performance.now() - startedAt);

  const expireElement = (el) => {
    if (!(el instanceof HTMLElement)) {
      return;
    }

    const endMs = Date.parse(el.getAttribute("data-slot-expires-at") || "");
    if (!Number.isFinite(endMs) || currentServerMs() < endMs) {
      return;
    }

    if (el.matches("form") || el.hasAttribute("data-slot-booking")) {
      el.setAttribute("data-slot-expired", "1");
      const submit = el.querySelector('[type="submit"]');
      if (submit instanceof HTMLButtonElement) {
        submit.disabled = true;
      }
      const note = el.querySelector("[data-slot-expired-note]");
      if (note instanceof HTMLElement) {
        note.classList.remove("d-none");
      }
      return;
    }

    el.remove();
  };

  const tick = () => {
    document.querySelectorAll("[data-slot-expires-at]").forEach((el) => expireElement(el));
  };

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.getAttribute("data-slot-expired") === "1") {
      event.preventDefault();
    }
  });

  tick();
  window.setInterval(tick, 15000);
}

function initializeComplaintImageUpload() {
  const roots = document.querySelectorAll("[data-complaint-image-upload]");
  if (!roots.length) {
    return;
  }

  const allowedTypes = new Set(["image/jpeg", "image/png"]);
  const allowedExtensions = new Set(["jpg", "jpeg", "png"]);

  roots.forEach((root) => {
    if (!(root instanceof HTMLElement)) {
      return;
    }

    const input = root.querySelector("[data-complaint-image-input]");
    const preview = root.querySelector("[data-complaint-image-preview]");
    const thumb = root.querySelector("[data-complaint-image-thumb]");
    const nameEl = root.querySelector("[data-complaint-image-name]");
    const removeBtn = root.querySelector("[data-complaint-image-remove]");
    const errorEl = root.querySelector("[data-complaint-image-error]");
    const form = root.closest("form");
    const maxBytes = Number.parseInt(root.getAttribute("data-max-bytes") || "5242880", 10);
    let objectUrl = "";

    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    const hidePreview = () => {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = "";
      }
      if (preview instanceof HTMLElement) {
        preview.classList.add("d-none");
      }
      if (thumb instanceof HTMLImageElement) {
        thumb.removeAttribute("src");
      }
      if (nameEl instanceof HTMLElement) {
        nameEl.textContent = "";
      }
    };

    const showError = (message) => {
      input.classList.add("is-invalid");
      if (errorEl instanceof HTMLElement && message) {
        errorEl.textContent = message;
      }
    };

    const clearError = () => {
      input.classList.remove("is-invalid");
    };

    const validateFile = (file) => {
      const type = String(file.type || "").toLowerCase();
      const nameParts = String(file.name || "").split(".");
      const extension = nameParts.length > 1 ? String(nameParts.pop()).toLowerCase() : "";
      const typeOk = type === "" || allowedTypes.has(type);
      if (!typeOk || !allowedExtensions.has(extension)) {
        return "Only JPG, JPEG, or PNG complaint images are allowed.";
      }
      if (file.size <= 0) {
        return "The selected complaint image is empty.";
      }
      if (Number.isFinite(maxBytes) && file.size > maxBytes) {
        return "Complaint image must be 5 MB or smaller.";
      }
      return "";
    };

    input.addEventListener("change", () => {
      const file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) {
        hidePreview();
        clearError();
        return;
      }

      const message = validateFile(file);
      if (message !== "") {
        input.value = "";
        hidePreview();
        showError(message);
        return;
      }

      clearError();
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
      objectUrl = URL.createObjectURL(file);
      if (thumb instanceof HTMLImageElement) {
        thumb.src = objectUrl;
      }
      if (nameEl instanceof HTMLElement) {
        nameEl.textContent = file.name;
      }
      if (preview instanceof HTMLElement) {
        preview.classList.remove("d-none");
      }
    });

    if (removeBtn instanceof HTMLElement) {
      removeBtn.addEventListener("click", () => {
        input.value = "";
        hidePreview();
        clearError();
        input.focus();
      });
    }

    if (form instanceof HTMLFormElement) {
      form.addEventListener("submit", (event) => {
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) {
          return;
        }
        const message = validateFile(file);
        if (message !== "") {
          event.preventDefault();
          input.value = "";
          hidePreview();
          showError(message);
        }
      });
    }
  });
}

function initializeBootstrapValidation() {
  const forms = document.querySelectorAll(".needs-validation");

  forms.forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();

        const firstInvalid = form.querySelector(":invalid");
        if (firstInvalid && typeof firstInvalid.focus === "function") {
          firstInvalid.focus();
        }
      } else {
        const submit = form.querySelector("[data-auth-submit]");
        if (submit && !submit.disabled) {
          submit.disabled = true;
          submit.classList.add("is-loading");
          const label = submit.querySelector("[data-auth-submit-label]");
          const loadingLabel = submit.getAttribute("data-loading-label");
          if (label && loadingLabel) {
            label.textContent = loadingLabel;
          }
        }
      }

      form.classList.add("was-validated");
    });
  });
}

function initializePasswordVisibility() {
  const toggles = document.querySelectorAll("[data-password-toggle]");

  toggles.forEach((toggle) => {
    toggle.addEventListener("click", () => {
      const selector = toggle.getAttribute("data-password-toggle");
      const input = selector ? document.querySelector(selector) : toggle.parentElement.querySelector("input");

      if (!input) {
        return;
      }

      const showPassword = input.type === "password";
      input.type = showPassword ? "text" : "password";
      toggle.setAttribute("aria-pressed", showPassword ? "true" : "false");
      toggle.setAttribute("aria-label", showPassword ? "Hide password" : "Show password");

      const icon = toggle.querySelector("i");
      if (icon) {
        icon.classList.toggle("bi-eye", !showPassword);
        icon.classList.toggle("bi-eye-slash", showPassword);
      }
    });
  });
}

function initializeDateOfBirthLimit() {
  const dobInput = document.querySelector("#registerDob");

  if (!dobInput) {
    return;
  }

  const now = new Date();
  const today = [
    String(now.getFullYear()),
    String(now.getMonth() + 1).padStart(2, "0"),
    String(now.getDate()).padStart(2, "0"),
  ].join("-");

  if (!dobInput.getAttribute("max")) {
    dobInput.setAttribute("max", today);
  }

  const validateDob = () => {
    const maxDate = dobInput.getAttribute("max") || today;

    if (dobInput.value !== "" && dobInput.value > maxDate) {
      dobInput.setCustomValidity("Date of birth cannot be in the future.");
      return;
    }

    dobInput.setCustomValidity("");
  };

  dobInput.addEventListener("change", validateDob);
  dobInput.addEventListener("input", validateDob);
  validateDob();
}

function initializePasswordStrength() {
  const passwordInputs = document.querySelectorAll("[data-password-strength]");
  const theme = getComputedStyle(document.documentElement);
  const danger = theme.getPropertyValue("--danger").trim();
  const warning = theme.getPropertyValue("--warning").trim();
  const primary = theme.getPropertyValue("--primary").trim();
  const success = theme.getPropertyValue("--success").trim();

  passwordInputs.forEach((input) => {
    const form = input.closest("form");
    const field = input.closest(".auth-login-field, .col-md-6, .col-12") || input.parentElement;
    const strengthBar = field.querySelector("[data-password-strength-bar]");
    const meter = form ? form.querySelector("[data-password-strength-meter]") : null;
    const requirements = form ? form.querySelector("[data-password-requirements]") : null;

    if (!strengthBar && !meter) {
      return;
    }

    const updateStrength = () => {
      const password = input.value;
      const checks = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /\d/.test(password),
        symbol: /[^A-Za-z0-9]/.test(password),
      };
      const segmentScore =
        (checks.length ? 1 : 0) +
        (checks.upper ? 1 : 0) +
        (checks.lower && checks.number ? 1 : 0) +
        (checks.symbol ? 1 : 0);
      const score = segmentScore * 25;

      if (strengthBar) {
        strengthBar.style.width = `${score}%`;

        if (score <= 25) {
          strengthBar.style.backgroundColor = danger;
        } else if (score <= 50) {
          strengthBar.style.backgroundColor = warning;
        } else if (score <= 75) {
          strengthBar.style.backgroundColor = primary;
        } else {
          strengthBar.style.backgroundColor = success;
        }
      }

      if (meter) {
        meter.dataset.level = String(segmentScore);
        meter.setAttribute("aria-valuenow", String(segmentScore));
        meter.querySelectorAll("span").forEach((segment, index) => {
          segment.classList.toggle("is-filled", index < segmentScore);
        });
      }

      if (requirements) {
        requirements.querySelectorAll("[data-req]").forEach((item) => {
          const key = item.getAttribute("data-req");
          item.classList.toggle("is-met", Boolean(key && checks[key]));
        });
      }

      if (password === "") {
        input.setCustomValidity("");
      } else if (!checks.length || !checks.upper || !checks.lower || !checks.number || !checks.symbol) {
        input.setCustomValidity("Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.");
      } else {
        input.setCustomValidity("");
      }
    };

    input.addEventListener("input", updateStrength);
    updateStrength();
  });
}

function initializePasswordConfirmation() {
  const confirmationInputs = document.querySelectorAll("[data-confirm-password]");

  confirmationInputs.forEach((input) => {
    const passwordSelector = input.getAttribute("data-confirm-password");
    const passwordInput = passwordSelector ? document.querySelector(passwordSelector) : null;

    if (!passwordInput) {
      return;
    }

    const validatePasswordMatch = () => {
      if (input.value === "") {
        input.setCustomValidity("");
        return;
      }

      if (input.value !== passwordInput.value) {
        input.setCustomValidity("Passwords do not match.");
      } else {
        input.setCustomValidity("");
      }
    };

    input.addEventListener("input", validatePasswordMatch);
    passwordInput.addEventListener("input", validatePasswordMatch);
  });
}

function initializeCurrentYear() {
  const yearElements = document.querySelectorAll("[data-current-year]");

  yearElements.forEach((element) => {
    element.textContent = new Date().getFullYear().toString();
  });
}

function initializeRevealAnimations() {
  const revealElements = document.querySelectorAll(".reveal-on-scroll");

  if (revealElements.length === 0) {
    return;
  }

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  revealElements.forEach((element) => {
    const delay = element.getAttribute("data-reveal-delay");
    if (delay) {
      element.style.setProperty("--reveal-delay", `${delay}ms`);
    }
  });

  if (prefersReducedMotion || !("IntersectionObserver" in window)) {
    revealElements.forEach((element) => {
      element.classList.add("is-visible");
    });
    return;
  }

  const observer = new IntersectionObserver((entries, currentObserver) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }

      entry.target.classList.add("is-visible");
      currentObserver.unobserve(entry.target);
    });
  }, {
    threshold: 0.15,
    rootMargin: "0px 0px -5% 0px",
  });

  revealElements.forEach((element) => {
    observer.observe(element);
  });
}

function initializeCounters() {
  const counters = document.querySelectorAll("[data-counter]");

  if (counters.length === 0) {
    return;
  }

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (prefersReducedMotion || !("IntersectionObserver" in window)) {
    counters.forEach((counter) => {
      counter.textContent = counter.getAttribute("data-counter") || counter.textContent;
    });
    return;
  }

  const animateCounter = (counter) => {
    const target = Number.parseInt(counter.getAttribute("data-counter") || "0", 10);

    if (Number.isNaN(target)) {
      return;
    }

    const duration = 1100;
    const startTime = performance.now();

    const step = (currentTime) => {
      const progress = Math.min((currentTime - startTime) / duration, 1);
      const value = Math.round(target * easeOutCubic(progress));
      counter.textContent = value.toString();

      if (progress < 1) {
        window.requestAnimationFrame(step);
      }
    };

    window.requestAnimationFrame(step);
  };

  const observer = new IntersectionObserver((entries, currentObserver) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }

      animateCounter(entry.target);
      currentObserver.unobserve(entry.target);
    });
  }, {
    threshold: 0.4,
  });

  counters.forEach((counter) => {
    observer.observe(counter);
  });
}

function easeOutCubic(value) {
  return 1 - ((1 - value) ** 3);
}

function prefersReducedMotion() {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function shouldSkipViewTransition(urlString) {
  try {
    const path = new URL(urlString, window.location.href).pathname;
    return path.includes("/download-") || path.endsWith("/room");
  } catch (error) {
    return false;
  }
}

function initializePageTransitions() {
  if (prefersReducedMotion()) {
    return;
  }

  window.addEventListener("pageswap", (event) => {
    if (!event.viewTransition) {
      return;
    }
    const destination = event.activation && event.activation.entry
      ? event.activation.entry.url
      : "";
    if (destination && shouldSkipViewTransition(destination)) {
      event.viewTransition.skipTransition();
    }
  });

  window.addEventListener("pagereveal", (event) => {
    if (event.viewTransition && prefersReducedMotion()) {
      event.viewTransition.skipTransition();
    }
  });

  if (document.body.classList.contains("public-layout")) {
    initializePublicPageTransitions();
  }

  window.addEventListener("pageshow", (event) => {
    if (!event.persisted || "startViewTransition" in document) {
      return;
    }
    document.body.classList.remove("ux-page-enter");
    window.requestAnimationFrame(() => {
      document.body.classList.add("ux-page-enter");
    });
  });
}

function initializePublicPageTransitions() {
  try {
    sessionStorage.removeItem("mbpha-ux-pt");
  } catch (error) {
    /* ignore quota / private mode */
  }

  if ("startViewTransition" in document) {
    return;
  }

  if (document.body.classList.contains("auth-login-layout")) {
    document.body.classList.add("ux-page-enter");
    return;
  }

  document.body.classList.add("ux-pt-enter");

  window.addEventListener("pageshow", (event) => {
    if (event.persisted) {
      document.body.classList.remove("ux-pt-enter");
      window.requestAnimationFrame(() => {
        document.body.classList.add("ux-pt-enter");
      });
    }
  });
}

/**
 * Sequential entrance for lists, cards, nav, and table rows.
 * Adapted from CodeFronts staggered-list-animation (MIT). Replay controls
 * from the demo are omitted — this runs once on page load.
 */
function initializeStaggeredLists() {
  if (prefersReducedMotion()) {
    return;
  }

  if (document.body.classList.contains("public-layout")) {
    return;
  }

  if ("startViewTransition" in document) {
    return;
  }

  const groups = [
    { selector: ".dashboard-content .ux-stat:not(.compact)", variant: "ux-stag--scale", delay: 90 },
    { selector: ".dashboard-content article.ux-card", variant: "ux-stag--scale", delay: 90 },
    { selector: ".lp-workflow__card", variant: "ux-stag--scale", delay: 90 },
    { selector: ".dashboard-content .ux-slot-row, .dashboard-content .ux-list__item, .dashboard-content a.ux-queue__item", variant: "ux-stag--left", delay: 80 },
    { selector: ".pp-step", variant: "ux-stag--left", delay: 80 },
    { selector: ".dashboard-content .ux-table tbody tr, .dashboard-content table.table tbody tr", variant: "ux-stag--up", delay: 75 },
  ];

  groups.forEach((group) => {
    const items = [...document.querySelectorAll(group.selector)].filter((element) => {
      if (!(element instanceof HTMLElement)) {
        return false;
      }
      if (element.querySelector("td[colspan]")) {
        return false;
      }
      return true;
    }).slice(0, 14);

    if (items.length === 0) {
      return;
    }

    staggerResetAndPlay(items, group.variant, group.delay);
  });
}

function staggerResetAndPlay(items, variant, delay) {
  items.forEach((element) => {
    element.classList.add("ux-stag-item", variant);
    element.classList.remove("in");
    if (element.parentElement) {
      element.parentElement.classList.add("ux-stag-armed");
    }
  });

  window.requestAnimationFrame(() => {
    items.forEach((element) => {
      void element.offsetWidth;
    });
    staggerPlay(items, delay);
  });
}

function staggerPlay(items, delay) {
  items.forEach((element, index) => {
    element.classList.remove("in");
    window.setTimeout(() => {
      element.classList.add("in");
    }, index * delay);
  });
}

function initializePublicNavDrawer() {
  const drawer = document.getElementById("publicNavigationDrawer");

  if (!drawer) {
    return;
  }

  drawer.querySelectorAll("a[href]").forEach((link) => {
    link.addEventListener("click", (event) => {
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      const href = link.getAttribute("href");

      if (!href || href.charAt(0) === "#") {
        return;
      }

      let destination;

      try {
        destination = new URL(href, window.location.href);
      } catch (error) {
        return;
      }

      const sameDocument =
        destination.origin === window.location.origin &&
        destination.pathname === window.location.pathname &&
        destination.search === window.location.search;

      event.preventDefault();
      event.stopPropagation();

      let didNavigate = false;
      const goToDestination = () => {
        if (didNavigate || sameDocument) {
          return;
        }
        didNavigate = true;
        window.location.assign(destination.href);
      };

      if (typeof bootstrap === "undefined" || !bootstrap.Offcanvas) {
        goToDestination();
        return;
      }

      const instance = bootstrap.Offcanvas.getInstance(drawer) || bootstrap.Offcanvas.getOrCreateInstance(drawer);

      if (!instance || !drawer.classList.contains("show")) {
        goToDestination();
        return;
      }

      drawer.addEventListener("hidden.bs.offcanvas", goToDestination, { once: true });
      instance.hide();
      window.setTimeout(goToDestination, 400);
    });
  });
}

