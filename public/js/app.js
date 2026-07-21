document.addEventListener("DOMContentLoaded", () => {
  initializeBootstrapValidation();
  initializePasswordStrength();
  initializePasswordConfirmation();
  initializeCurrentYear();
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
        strengthBar.style.backgroundColor = "#ef4444";
      } else if (score <= 50) {
        strengthBar.style.backgroundColor = "#f59e0b";
      } else if (score <= 75) {
        strengthBar.style.backgroundColor = "#2a9d8f";
      } else {
        strengthBar.style.backgroundColor = "#22c55e";
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
