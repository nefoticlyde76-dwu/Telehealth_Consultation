document.addEventListener("DOMContentLoaded", () => {
  initializeBootstrapValidation();
  initializePasswordStrength();
  initializePasswordConfirmation();
  initializeCurrentYear();
  initializeRevealAnimations();
  initializeCounters();
  initializeProfileImageCropper();
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

function initializeProfileImageCropper() {
  const modalElement = document.querySelector("[data-profile-crop-modal]");
  const cropInputs = document.querySelectorAll("[data-profile-crop-input]");

  if (!modalElement || cropInputs.length === 0 || typeof Cropper === "undefined") {
    return;
  }

  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const imageElement = modalElement.querySelector("[data-profile-crop-image]");
  const previewElement = modalElement.querySelector("[data-profile-crop-preview]");
  const confirmButton = modalElement.querySelector("[data-profile-crop-confirm]");
  const zoomInButton = modalElement.querySelector("[data-profile-crop-zoom-in]");
  const zoomOutButton = modalElement.querySelector("[data-profile-crop-zoom-out]");
  let cropper = null;
  let activeInput = null;
  let activeObjectUrl = null;
  let cropConfirmed = false;

  const allowedMimeTypes = ["image/jpeg", "image/png", "image/webp"];
  const maxFileSize = 5 * 1024 * 1024;

  const setFeedback = (input, message, type = "info") => {
    const feedback = input
      .closest("form")
      ?.querySelector("[data-profile-crop-feedback]");

    if (!feedback) {
      return;
    }

    feedback.textContent = message;
    feedback.classList.remove("d-none", "text-danger", "text-success", "text-muted");

    if (type === "error") {
      feedback.classList.add("text-danger");
    } else if (type === "success") {
      feedback.classList.add("text-success");
    } else {
      feedback.classList.add("text-muted");
    }
  };

  const clearActiveObjectUrl = () => {
    if (activeObjectUrl) {
      URL.revokeObjectURL(activeObjectUrl);
      activeObjectUrl = null;
    }
  };

  const destroyCropper = () => {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
  };

  const resetActiveInput = ({ clearValue } = { clearValue: false }) => {
    if (!activeInput) {
      return;
    }

    activeInput.dataset.croppedReady = "false";

    if (clearValue) {
      activeInput.value = "";
    }
  };

  cropInputs.forEach((input) => {
    input.dataset.croppedReady = "false";

    input.addEventListener("change", () => {
      const [file] = input.files || [];

      if (!file) {
        input.dataset.croppedReady = "false";
        return;
      }

      if (!allowedMimeTypes.includes(file.type)) {
        input.value = "";
        input.dataset.croppedReady = "false";
        setFeedback(input, "Only JPG, JPEG, PNG, or WEBP profile pictures are allowed.", "error");
        return;
      }

      if (file.size > maxFileSize) {
        input.value = "";
        input.dataset.croppedReady = "false";
        setFeedback(input, "Profile picture must be 5 MB or smaller.", "error");
        return;
      }

      activeInput = input;
      cropConfirmed = false;
      clearActiveObjectUrl();
      destroyCropper();

      activeObjectUrl = URL.createObjectURL(file);
      imageElement.src = activeObjectUrl;
      imageElement.alt = `Cropping ${input.dataset.profileCropLabel || "profile picture"}`;
      previewElement.innerHTML = "";

      modal.show();
    });
  });

  modalElement.addEventListener("shown.bs.modal", () => {
    if (!activeInput || !imageElement.getAttribute("src")) {
      return;
    }

    cropper = new Cropper(imageElement, {
      aspectRatio: 1,
      viewMode: 1,
      dragMode: "move",
      autoCropArea: 1,
      responsive: true,
      restore: false,
      guides: false,
      center: true,
      highlight: false,
      background: false,
      preview: previewElement,
      cropBoxMovable: false,
      cropBoxResizable: false,
      toggleDragModeOnDblclick: false,
    });
  });

  modalElement.addEventListener("hidden.bs.modal", () => {
    destroyCropper();
    clearActiveObjectUrl();

    if (!cropConfirmed) {
      resetActiveInput({ clearValue: true });

      if (activeInput) {
        setFeedback(activeInput, "Image selection cancelled. Choose a file to crop and save.", "info");
      }
    }
  });

  zoomInButton?.addEventListener("click", () => {
    cropper?.zoom(0.1);
  });

  zoomOutButton?.addEventListener("click", () => {
    cropper?.zoom(-0.1);
  });

  confirmButton?.addEventListener("click", () => {
    if (!cropper || !activeInput) {
      return;
    }

    confirmButton.disabled = true;
    confirmButton.querySelector(".button-label")?.classList.add("d-none");
    confirmButton.querySelector(".spinner-border")?.classList.remove("d-none");

    cropper.getCroppedCanvas({
      width: 300,
      height: 300,
      fillColor: "#ffffff",
      imageSmoothingEnabled: true,
      imageSmoothingQuality: "high",
    }).toBlob((blob) => {
      confirmButton.disabled = false;
      confirmButton.querySelector(".button-label")?.classList.remove("d-none");
      confirmButton.querySelector(".spinner-border")?.classList.add("d-none");

      if (!blob || !activeInput) {
        setFeedback(activeInput, "Unable to generate the cropped profile picture. Please try again.", "error");
        return;
      }

      const croppedFile = new File(
        [blob],
        `cropped_${Date.now()}.jpg`,
        { type: "image/jpeg" },
      );
      const transfer = new DataTransfer();
      transfer.items.add(croppedFile);
      activeInput.files = transfer.files;
      activeInput.dataset.croppedReady = "true";
      cropConfirmed = true;
      setFeedback(activeInput, "Cropped profile picture is ready to upload.", "success");
      modal.hide();
    }, "image/jpeg", 0.9);
  });

  document.querySelectorAll("[data-profile-photo-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      const input = form.querySelector("[data-profile-crop-input]");
      const submitButton = form.querySelector("[data-profile-submit-button]");

      if (input && input.files.length > 0 && input.dataset.croppedReady !== "true") {
        event.preventDefault();
        event.stopPropagation();
        setFeedback(input, "Please crop the selected profile picture before saving.", "error");
        return;
      }

      if (!form.checkValidity()) {
        return;
      }

      submitButton?.setAttribute("disabled", "disabled");
      submitButton?.classList.add("profile-submit-loading");
      submitButton?.querySelector(".button-label")?.classList.add("d-none");
      submitButton?.querySelector(".spinner-border")?.classList.remove("d-none");
    });
  });
}
