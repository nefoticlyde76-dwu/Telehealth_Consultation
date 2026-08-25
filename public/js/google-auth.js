/**
 * Shared Google Identity Services helper for patient login and registration.
 *
 * Receives an opaque GIS credential and POSTs it to /auth/google.
 * Does not inspect credential claims, generate passwords, or create a session.
 */
(function () {
  "use strict";

  var initialized = false;
  var requestInProgress = false;
  var loadTimer = null;
  var redirected = false;

  window.mbphaGoogleGisLoaded = function () {
    initializeGoogleSignIn();
  };

  document.addEventListener("DOMContentLoaded", function () {
    loadTimer = window.setTimeout(function () {
      if (!initialized && root()) {
        showError(hostMessage("data-error-unavailable", "Google sign-in is unavailable right now. Please use email and password."));
      }
    }, 8000);
    initializeGoogleSignIn();
  });

  function root() {
    return document.querySelector("[data-google-auth]");
  }

  function hostMessage(attribute, fallback) {
    var host = root();
    if (!host) {
      return fallback;
    }
    var value = (host.getAttribute(attribute) || "").trim();
    return value !== "" ? value : fallback;
  }

  function initializeGoogleSignIn() {
    if (initialized) {
      return;
    }

    var host = root();
    var buttonHost = host ? host.querySelector("[data-google-auth-button]") : null;
    if (!host || !buttonHost) {
      return;
    }

    if (!window.google || !google.accounts || !google.accounts.id) {
      return;
    }

    var clientId = (host.getAttribute("data-client-id") || "").trim();
    if (clientId === "") {
      return;
    }

    initialized = true;
    if (loadTimer !== null) {
      window.clearTimeout(loadTimer);
      loadTimer = null;
    }

    google.accounts.id.initialize({
      client_id: clientId,
      callback: onCredentialResponse,
      ux_mode: "popup",
      auto_select: false,
    });

    var width = Math.floor(host.getBoundingClientRect().width);
    if (width < 240) {
      width = Math.floor((host.parentElement && host.parentElement.getBoundingClientRect().width) || 320);
    }
    width = Math.max(240, Math.min(400, width));

    google.accounts.id.renderButton(buttonHost, {
      type: "standard",
      theme: "outline",
      size: "large",
      text: "continue_with",
      shape: "rectangular",
      logo_alignment: "left",
      width: width,
    });
  }

  function onCredentialResponse(response) {
    var credential = response && typeof response.credential === "string"
      ? response.credential.trim()
      : "";

    if (credential === "") {
      showError(hostMessage("data-error-generic", "Google sign-in could not be completed."));
      return;
    }

    submitCredential(credential);
  }

  function submitCredential(credential) {
    if (requestInProgress || redirected) {
      return;
    }

    requestInProgress = true;
    setBusy(true);

    var host = root();
    var url = host ? (host.getAttribute("data-auth-url") || "").trim() : "";
    var csrf = readCsrfToken();

    if (url === "" || csrf === "") {
      requestInProgress = false;
      setBusy(false);
      showError(
        csrf === ""
          ? "Your session security token is invalid. Please refresh the page and try again."
          : hostMessage("data-error-generic", "Google sign-in could not be completed.")
      );
      return;
    }

    fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        credential: credential,
        _token: csrf,
      }),
    })
      .then(parseJsonResponse)
      .then(handleBackendResponse)
      .catch(function () {
        showError(hostMessage("data-error-generic", "Google sign-in could not be completed."));
      })
      .finally(function () {
        if (!redirected) {
          requestInProgress = false;
          setBusy(false);
        }
      });
  }

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      var data = null;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (ignore) {
        data = null;
      }

      return {
        status: response.status,
        data: data,
      };
    });
  }

  function handleBackendResponse(result) {
    var data = result.data && typeof result.data === "object" ? result.data : {};

    if (result.status === 419) {
      showError("Your session security token is invalid. Please refresh the page and try again.");
      return;
    }

    if (data.success === true && isSafeRedirect(data.redirect)) {
      redirected = true;
      window.location.assign(data.redirect);
      return;
    }

    if (result.status >= 500) {
      showError("Google sign-in is temporarily unavailable. Please try again later.");
      return;
    }

    showError(hostMessage("data-error-generic", "Google sign-in could not be completed."));
  }

  function isSafeRedirect(url) {
    return typeof url === "string" && url.charAt(0) === "/" && url.charAt(1) !== "/";
  }

  function readCsrfToken() {
    var input = document.querySelector('form.auth-login-form input[name="_token"]');
    if (input && typeof input.value === "string") {
      return input.value.trim();
    }

    return "";
  }

  function setBusy(isBusy) {
    var host = root();
    var status = host ? host.querySelector("[data-google-auth-status]") : null;
    if (host) {
      host.classList.toggle("is-busy", isBusy);
      host.setAttribute("aria-busy", isBusy ? "true" : "false");
    }
    if (status) {
      status.textContent = isBusy
        ? hostMessage("data-busy-label", "Signing in…")
        : "";
    }
  }

  function showError(message) {
    var alertBox = document.querySelector("[data-google-auth-alert]");
    if (!alertBox) {
      return;
    }

    alertBox.textContent = message;
    alertBox.classList.remove("d-none");
    if (typeof alertBox.focus === "function") {
      alertBox.setAttribute("tabindex", "-1");
      alertBox.focus();
    }
  }
})();
