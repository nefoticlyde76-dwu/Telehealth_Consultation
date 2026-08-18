/* ======================================================================
 * Week 7 — Live clinical documentation autosave (doctor room)
 *
 * Saves a Draft progressively while the Daily call remains usable.
 * Completing the consultation is a separate confirmed POST.
 * ====================================================================== */
(function () {
  'use strict';

  const form = document.querySelector('[data-clinical-record-form]');
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  if (form.getAttribute('data-can-edit') !== '1') {
    return;
  }

  const saveButton = document.getElementById('vc-clinical-save-btn');
  const statusEl = document.getElementById('vc-clinical-save-state');
  const savedAtEl = document.getElementById('vc-clinical-saved-at');
  const tokenInput = form.querySelector('input[name="_token"]');
  const recordIdInput = document.getElementById('vc-clinical-record-id');
  const completeButton = document.getElementById('vc-complete-confirm-btn');
  const completeError = document.getElementById('vc-complete-modal-error');

  const DEBOUNCE_MS = 1500;
  const MAX_RETRIES = 4;
  const REQUIRED_FIELDS = [
    { name: 'chief_complaint', label: 'Chief complaint / presenting problem' },
    { name: 'symptoms', label: 'History / symptoms' },
    { name: 'clinical_findings', label: 'Clinical findings / assessment' },
    { name: 'diagnosis', label: 'Diagnosis' },
    { name: 'treatment_plan', label: 'Treatment / medical advice' },
  ];

  let debounceTimer = null;
  let retryTimer = null;
  let retryAttempt = 0;
  let inFlight = false;
  let queued = false;
  let dirty = false;
  let lastSavedSnapshot = serializeForm();
  let saveGeneration = 0;
  let completing = false;

  form.addEventListener('input', scheduleAutosave);
  form.addEventListener('change', scheduleAutosave);
  form.addEventListener('submit', function (event) {
    if (completing) {
      return;
    }
    event.preventDefault();
    saveDraft({ manual: true, retry: false });
  });

  if (completeButton instanceof HTMLButtonElement) {
    completeButton.addEventListener('click', submitCompleteConsultation);
  }

  window.addEventListener('beforeunload', function (event) {
    if (!dirty || completing) {
      return;
    }
    event.preventDefault();
    event.returnValue = '';
  });

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden' && dirty && !completing) {
      saveDraft({ manual: false, retry: false, keepalive: true });
    }
  });

  window.addEventListener('pagehide', function () {
    if (dirty && !completing) {
      saveDraft({ manual: false, retry: false, keepalive: true });
    }
  });

  function serializeForm() {
    const data = new FormData(form);
    data.delete('_token');
    data.delete('confirm');
    const parts = [];
    data.forEach(function (value, key) {
      parts.push(key + '=' + String(value));
    });
    parts.sort();
    return parts.join('&');
  }

  function scheduleAutosave() {
    dirty = serializeForm() !== lastSavedSnapshot;
    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }
    if (retryTimer) {
      window.clearTimeout(retryTimer);
      retryTimer = null;
    }
    debounceTimer = window.setTimeout(function () {
      saveDraft({ manual: false, retry: false });
    }, DEBOUNCE_MS);
  }

  function saveDraft(options) {
    const manual = !!(options && options.manual);
    const isRetry = !!(options && options.retry);
    const keepalive = !!(options && options.keepalive);

    if (inFlight && !keepalive) {
      queued = true;
      return Promise.resolve(false);
    }

    const snapshot = serializeForm();
    if (!manual && !isRetry && snapshot === lastSavedSnapshot && !dirty) {
      return Promise.resolve(true);
    }

    inFlight = !keepalive;
    const generation = ++saveGeneration;
    if (!isRetry) {
      retryAttempt = 0;
    }
    setStatus('Saving...', '');

    if (manual && saveButton instanceof HTMLButtonElement) {
      saveButton.disabled = true;
    }

    const endpoint = form.getAttribute('action') || '';
    const body = new FormData(form);
    body.delete('confirm');

    return fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: keepalive,
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: body,
    })
      .then(function (response) {
        return response.text().then(function (text) {
          let payload = null;
          try {
            payload = text ? JSON.parse(text) : null;
          } catch (_err) {
            payload = null;
          }
          return { response: response, payload: payload };
        });
      })
      .then(function (result) {
        if (generation !== saveGeneration && !keepalive) {
          return false;
        }

        const payload = result.payload && typeof result.payload === 'object' ? result.payload : null;
        if (payload && typeof payload.csrf_token === 'string' && payload.csrf_token !== '' && tokenInput instanceof HTMLInputElement) {
          tokenInput.value = payload.csrf_token;
        }
        if (payload && Number(payload.record_id) > 0 && recordIdInput instanceof HTMLInputElement) {
          recordIdInput.value = String(payload.record_id);
        }

        if (result.response.status === 200 && payload && payload.ok === true) {
          retryAttempt = 0;
          lastSavedSnapshot = snapshot;
          dirty = serializeForm() !== lastSavedSnapshot;
          setStatus('Saved', 'is-saved');
          if (savedAtEl) {
            const stamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            savedAtEl.textContent = 'Last saved ' + stamp;
          }
          return true;
        }

        const status = result.response.status;
        const retryable = status === 0 || status === 419 || status >= 500;
        if (retryable) {
          queueRetry();
          return false;
        }

        setStatus('Unable to save', 'is-error');
        return false;
      })
      .catch(function () {
        if (generation !== saveGeneration && !keepalive) {
          return false;
        }
        queueRetry();
        return false;
      })
      .finally(function () {
        if (generation !== saveGeneration && !keepalive) {
          return;
        }
        inFlight = false;
        if (saveButton instanceof HTMLButtonElement) {
          saveButton.disabled = false;
        }
        if (queued) {
          queued = false;
          saveDraft({ manual: false, retry: false });
        }
      });
  }

  function queueRetry() {
    setStatus('Unable to save — retrying', 'is-error');
    if (retryAttempt >= MAX_RETRIES) {
      return;
    }
    retryAttempt += 1;
    if (retryTimer) {
      window.clearTimeout(retryTimer);
    }
    const delay = Math.min(8000, 1000 * retryAttempt);
    retryTimer = window.setTimeout(function () {
      saveDraft({ manual: false, retry: true });
    }, delay);
  }

  function setStatus(text, modifier) {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = text;
    statusEl.classList.remove('is-saved', 'is-error');
    if (modifier) {
      statusEl.classList.add(modifier);
    }
  }

  function waitUntilIdle() {
    if (!inFlight) {
      return Promise.resolve();
    }
    return new Promise(function (resolve) {
      const timer = window.setInterval(function () {
        if (!inFlight) {
          window.clearInterval(timer);
          resolve();
        }
      }, 50);
    });
  }

  function missingRequiredLabels() {
    const missing = [];
    REQUIRED_FIELDS.forEach(function (field) {
      const input = form.querySelector('[name="' + field.name + '"]');
      if (!(input instanceof HTMLTextAreaElement) && !(input instanceof HTMLInputElement)) {
        missing.push(field.label);
        return;
      }
      if (String(input.value || '').trim() === '') {
        missing.push(field.label);
      }
    });
    return missing;
  }

  function showCompleteError(message) {
    if (!(completeError instanceof HTMLElement)) {
      window.alert(message);
      return;
    }
    completeError.textContent = message;
    completeError.classList.remove('d-none');
  }

  function submitCompleteConsultation() {
    const missing = missingRequiredLabels();
    if (missing.length > 0) {
      showCompleteError('Complete the required clinical fields first: ' + missing.join(', ') + '.');
      return;
    }

    if (completeError instanceof HTMLElement) {
      completeError.classList.add('d-none');
      completeError.textContent = '';
    }

    if (completeButton instanceof HTMLButtonElement) {
      completeButton.disabled = true;
    }

    waitUntilIdle().then(function () {
      return saveDraft({ manual: true, retry: false });
    }).then(function (saved) {
      const completeEndpoint = form.getAttribute('data-complete-endpoint') || '';
      if (!saved || completeEndpoint === '') {
        showCompleteError('The latest draft could not be saved. Please try again.');
        if (completeButton instanceof HTMLButtonElement) {
          completeButton.disabled = false;
        }
        return;
      }

      completing = true;
      dirty = false;
      let confirmInput = form.querySelector('input[name="confirm"]');
      if (!(confirmInput instanceof HTMLInputElement)) {
        confirmInput = document.createElement('input');
        confirmInput.type = 'hidden';
        confirmInput.name = 'confirm';
        form.appendChild(confirmInput);
      }
      confirmInput.value = '1';
      form.setAttribute('action', completeEndpoint);
      form.submit();
    });
  }
})();
