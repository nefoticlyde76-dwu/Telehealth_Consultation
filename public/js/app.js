document.addEventListener("DOMContentLoaded", () => {
  initializeBootstrapValidation();
  initializePasswordStrength();
  initializePasswordConfirmation();
  initializeCurrentYear();
  initializeRevealAnimations();
  initializeCounters();
});

function initializeBootstrapValidation() {
  const forms = document.querySelectorAll(".needs-validation");

  forms.forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }

      form.classList.add("was-validated");
    });
  });
}

function initializePasswordStrength() {
  const passwordInputs = document.querySelectorAll("[data-password-strength]");
  const theme = getComputedStyle(document.documentElement);
  const danger = theme.getPropertyValue("--danger").trim();
  const warning = theme.getPropertyValue("--warning").trim();
  const primary = theme.getPropertyValue("--primary").trim();
  const success = theme.getPropertyValue("--success").trim();

  passwordInputs.forEach((input) => {
    const strengthBar = input.parentElement.querySelector("[data-password-strength-bar]");

    if (!strengthBar) {
      return;
    }

    const updateStrength = () => {
      const password = input.value;
      let score = 0;

      if (password.length >= 8) {
        score += 25;
      }

      if (/[A-Z]/.test(password)) {
        score += 25;
      }

      if (/[a-z]/.test(password) && /\d/.test(password)) {
        score += 25;
      }

      if (/[^A-Za-z0-9]/.test(password)) {
        score += 25;
      }

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
