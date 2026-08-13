/* ======================================================================
 * Week 6 — TeleHealth consultation room
 * Client-side vanilla JS to bootstrap a Daily.co prebuilt frame.
 *
 * Security model (IMPORTANT):
 *   • This script NEVER holds the Daily API key.
 *   • It reads the authorized {room_url, token} ONLY from the backend
 *     join-token endpoint via a same-origin fetch() call that carries
 *     the existing PHP session cookie.
 *   • Tokens live only in JS memory (window.DailyIframe frame object)
 *     and are cleared when the user navigates away / leaves the call.
 *
 *   The script is data-* configured from the PHP view — there are NO
 *   hardcoded paths, consultation ids, or role assumptions here.
 *
 * Vanilla JS ES6 (no jQuery, no React/Vue).
 * ====================================================================== */
(function () {
  'use strict';

  let SCRIPT = document.currentScript;
  if (!SCRIPT) {
    SCRIPT = document.getElementById('vc-room-wiring');
  }
  if (!SCRIPT) {
    showBootstrapFatal();
    return;
  }

  const CONSULTATION_ID = (SCRIPT.dataset.consultationRoom || '').trim();
  const JOIN_ENDPOINT   = (SCRIPT.dataset.joinEndpoint || '').trim();
  const VIEWER_ROLE     = (SCRIPT.dataset.viewerRole || 'patient').trim();

  if (!JOIN_ENDPOINT || !CONSULTATION_ID) {
    showBootstrapFatal();
    return;
  }

  const ALERT_REGION = document.getElementById('vc-alert-region');
  const FRAME_WRAPPER = document.getElementById('vc-daily-frame-wrapper');
  const PRECALL_STATE = document.getElementById('vc-pre-call-state');
  const PRECALL_CHECKLIST = document.getElementById('vc-precall-checklist');
  const CALL_STATUS_PANEL = document.getElementById('vc-call-status-panel');
  const LEAVE_BTN_TOP = document.getElementById('vc-leave-call-top');

  function showBootstrapFatal() {
    if (document.getElementById('vc-alert-region')) {
      const region = document.getElementById('vc-alert-region');
      region.innerHTML =
        '<div class="alert alert-danger" role="alert">' +
          '<h6 class="alert-heading mb-2">Consultation configuration is missing.</h6>' +
          '<p class="mb-0">Please return to the consultation details page and try again.</p>' +
        '</div>';
    }
    const spinner = document.getElementById('vc-initializing-spinner');
    if (spinner) spinner.classList.add('d-none');
    const msg = document.getElementById('vc-precall-state__message');
    if (msg) {
      msg.textContent = 'The consultation room could not be loaded. Return to the previous page and retry.';
    }
  }

  // Iframe handle from Daily.co
  let callFrame = null;
  let cameraCheckTimer = null;
  let isBootstrapping = false;
  let hasJoined = false;

  // Kicked off immediately (after DOMContentLoaded already since script
  // tag is defer).
  bootstrapRoom();

  function isLikelyLocalhost() {
    try {
      const h = (window.location.hostname || '').toLowerCase();
      if (!h) return false;
      if (h === 'localhost' || h === '127.0.0.1' || h === '::1') return true;
      return /\.localhost$/.test(h);
    } catch (_) {
      return false;
    }
  }

  function buildLocalhostRedirectLink() {
    try {
      const current = window.location;
      const portPart = current.port ? ':' + current.port : '';
      return current.protocol + '//localhost' + portPart + current.pathname + current.search + current.hash;
    } catch (_) {
      return 'http://localhost' + (window.location.pathname || '/');
    }
  }

  function checkMediaCapabilities() {
    const isSecure = !!(window.isSecureContext);
    const hasMD = !!(typeof navigator !== 'undefined' && navigator.mediaDevices && typeof navigator.mediaDevices === 'object');
    const hasGUM = !!(hasMD && typeof navigator.mediaDevices.getUserMedia === 'function');
    if (isSecure && hasGUM) return { ok: true };
    const localhostLink = buildLocalhostRedirectLink();
    const reasons = [];
    if (!isSecure) reasons.push('This page is not running in a secure context (HTTPS or localhost).');
    if (!hasMD) reasons.push('navigator.mediaDevices is not exposed by the browser for this origin.');
    if (hasMD && !hasGUM) reasons.push('getUserMedia() is not available on this origin.');
    const local = isLikelyLocalhost();
    const header = local
      ? 'Camera/microphone unavailable in this browser'
      : 'Open this page on localhost to enable camera & microphone';
    let bodyHtml = '';
    if (!local) {
      bodyHtml +=
        '<p class="mb-2">' +
          'Chrome, Edge, Firefox and Safari only allow camera/microphone access on ' +
          '<strong>HTTPS</strong> or <strong>http://localhost</strong>. Your current address (<code>' +
          escapeHtml(window.location.hostname || '') +
          '</code>) is not on localhost so the browser blocked access before even asking permission.' +
        '</p>' +
        '<p class="mb-2">' +
          'Open the correct local URL to start the video consultation:' +
        '</p>' +
        '<p class="mb-2">' +
          '<a class="btn btn-primary btn-sm" href="' + escapeAttr(localhostLink) + '">' +
            '<i class="bi bi-box-arrow-up-right me-1"></i>' +
            'Open room on localhost' +
          '</a>' +
          '&nbsp;&nbsp;<code class="small align-middle text-break">' + escapeHtml(localhostLink) + '</code>' +
        '</p>';
    } else {
      bodyHtml +=
        '<p class="mb-2">' +
          'Even though this is localhost, secure-context or media APIs are unavailable. This usually means ' +
          'a browser flag, extension, incognito restriction, or antivirus is blocking camera access. ' +
          'Try the steps below.' +
        '</p>';
    }
    bodyHtml +=
      '<hr class="my-2 border-secondary-subtle">' +
      '<h6 class="mb-1 text-body-emphasis">Troubleshooting steps</h6>' +
      '<ol class="mb-0 small ps-3">' +
        '<li>In the browser address bar, click the 🔒/🛈 icon to the left of the URL → open <em>Site settings</em> → set Camera and Microphone to <strong>Allow</strong>, then refresh.</li>' +
        '<li>Close any other app that might be holding the camera (Zoom, Teams, OBS, Discord, Webex, Meet, etc.).</li>' +
        '<li>If you denied permission earlier, the URL bar usually shows a 🚫 camera icon. Click it and re-allow.</li>' +
        '<li>Restart the browser and reopen this room page.</li>' +
      '</ol>';
    if (reasons.length) {
      bodyHtml +=
        '<p class="mt-3 mb-0 small text-muted">Diagnostics: ' +
          reasons.map(escapeHtml).join(' ') +
        '</p>';
    }
    return { ok: false, alertClass: 'danger', heading: header, bodyHtml };
  }

  /* ----------------------------------------------------------------------
   *  1. Fetch room credentials, mount the Daily iframe, wire events.
   * ---------------------------------------------------------------------- */
  async function bootstrapRoom() {
    // ── Prevent duplicate Daily instances (double-click, double-load) ──
    if (isBootstrapping || callFrame) return;
    isBootstrapping = true;

    try {
      // Early-check browser media capability BEFORE contacting Daily. If
      // getUserMedia() isn't exposed (e.g. non-localhost URL), Daily will
      // silently sit on "Checking…" forever because the permission prompt
      // cannot even be shown by the browser.
      const mediaReady = checkMediaCapabilities();
      if (!mediaReady.ok) {
        showAlert(ALERT_REGION, mediaReady.alertClass, mediaReady.heading, mediaReady.bodyHtml);
        hideInitializingSpinner();
        setPrecallMessage(
          'Camera/microphone access was blocked by the browser. Use the link or steps above to open the room on localhost (http://localhost/…).'
        );
        isBootstrapping = false;
        return;
      }

      const credentials = await fetchJoinCredentials();
      if (!credentials) {
        isBootstrapping = false;
        return; // fetchJoinCredentials already rendered alerts
      }

      if (!FRAME_WRAPPER) {
        throw new Error('Missing #vc-daily-frame-wrapper');
      }

      // ── Resolve Daily Prebuilt factory (with poller) ─────────────
      // The CDN <script> tag is synchronous and should load BEFORE this
      // deferred module, but on slow/flaky connections the module may
      // fire before window.DailyIframe is attached.  Poll up to ~6s,
      // then fail with a clear network error.
      const DailyIframe = await waitForDailyFactory(6000);

      if (typeof DailyIframe === 'undefined') {
        showAlert(
          ALERT_REGION,
          'danger',
          'Video service unavailable.',
          'The video consultation library could not be loaded. Please check your network connection and refresh the page.'
        );
        hideInitializingSpinner();
        setPrecallMessage(
          'The video library could not be loaded. Please refresh the page or try again in a moment.'
        );
        isBootstrapping = false;
        return;
      }

      // Remove pre-call loading card.  Now Daily itself will show the
      // prejoin preview (camera/mic picker).
      if (PRECALL_STATE) {
        PRECALL_STATE.style.display = 'none';
      }

      // ─── Mount Daily iframe ───────────────────────────────────────
      // NOTE: We create the frame FIRST with sizing only, then call
      // join() explicitly.  This two-step pattern is the canonical
      // Daily Prebuilt approach that works reliably across all library
      // versions (older versions do NOT auto-join when url is passed
      // to createFrame).
      callFrame = DailyIframe.createFrame(FRAME_WRAPPER, {
        showLeaveButton: true,
        showFullscreenButton: true,
        showParticipantsBar: true,
        showLocalVideo: true,
        iframeStyle: {
          width: '100%',
          height: '100%',
          border: '0',
          minHeight: '540px',
        },
      });

      // Safety net: if Daily is still stuck on "Checking…" after 15s,
      // show a permission/troubleshooting banner so the user doesn't
      // stare at a blank spinner forever.
      if (cameraCheckTimer) clearTimeout(cameraCheckTimer);
      cameraCheckTimer = setTimeout(showCameraStuckHint, 15 * 1000);

      if (PRECALL_CHECKLIST) {
        PRECALL_CHECKLIST.classList.add('d-none');
      }
      if (CALL_STATUS_PANEL) {
        CALL_STATUS_PANEL.classList.remove('d-none');
      }
      if (LEAVE_BTN_TOP) {
        LEAVE_BTN_TOP.classList.remove('d-none');
        LEAVE_BTN_TOP.addEventListener('click', onLeaveClicked, { once: true });
      }

      // ─── Daily prebuilt events ───────────────────────────────────
      wireDailyEvents(callFrame);

      // ─── EXPLICITLY JOIN THE ROOM ────────────────────────────────
      // This is the single most important fix.  Regardless of the
      // Daily library version, an explicit join() call with correct
      // room_url (and token when available) guarantees both users
      // enter the same Daily room.
      const joinPayload = {
        url: credentials.room_url,
      };
      if (credentials.token && String(credentials.token).trim() !== '') {
        joinPayload.token = credentials.token;
      }

      try {
        await callFrame.join(joinPayload);
        hasJoined = true;
      } catch (joinErr) {
        const rawMsg = safeErrorMessage(joinErr);
        if (/already/i.test(rawMsg) || /duplicate/i.test(rawMsg) || hasJoined) {
          // Already-joined is non-fatal — the user may have clicked
          // twice or the library auto-joined.  Swallow the duplicate
          // error silently after recording it.
          hasJoined = true;
        } else {
          throw joinErr;
        }
      }
    } catch (err) {
      hideInitializingSpinner();
      renderFatalAlert(
        'danger',
        'Could not start consultation.',
        safeErrorMessage(err)
      );
    } finally {
      isBootstrapping = false;
    }
  }

  function wireDailyEvents(frame) {
    frame.on('loaded', () => {
      clearRegionAlerts();
      updateCallState('Ready');
    });

    frame.on('joining-meeting', () => {
      updateCallState('Connecting…');
    });

    frame.on('joined-meeting', () => {
      updateCallState('Connected');
      if (cameraCheckTimer) { clearTimeout(cameraCheckTimer); cameraCheckTimer = null; }
    });

    frame.on('participant-joined', updateParticipantCountFromFrame);
    frame.on('participant-updated', updateParticipantCountFromFrame);
    frame.on('participant-left', updateParticipantCountFromFrame);

    frame.on('local-audio-level', (ev) => {
      const muted = !!(ev && typeof ev.audio === 'boolean' && ev.audio === false);
      setMediaStatus('mic', muted ? 'Muted' : 'On');
      if (cameraCheckTimer) { clearTimeout(cameraCheckTimer); cameraCheckTimer = null; }
    });
    frame.on('local-video-level', (ev) => {
      const muted = !!(ev && typeof ev.video === 'boolean' && ev.video === false);
      setMediaStatus('camera', muted ? 'Off' : 'On');
      if (cameraCheckTimer) { clearTimeout(cameraCheckTimer); cameraCheckTimer = null; }
    });
    frame.on('local-user-updated', () => {
      // Daily successfully read the local media track state → the
      // browser DID allow access. Clear the "stuck on Checking" timer.
      if (cameraCheckTimer) { clearTimeout(cameraCheckTimer); cameraCheckTimer = null; }
    });
    frame.on('get-user-media-failed', (ev) => {
      if (cameraCheckTimer) { clearTimeout(cameraCheckTimer); cameraCheckTimer = null; }
      const raw = ev && (ev.errorMsg || ev.error || ev.message);
      handleGetUserMediaFailed(typeof raw === 'string' ? raw : '');
    });
    frame.on('camera-error', (ev) => {
      const msg = ev && typeof ev.errorMsg === 'string' ? ev.errorMsg : '';
      handleCameraError(msg);
    });
    frame.on('microphone-error', (ev) => {
      const msg = ev && typeof ev.errorMsg === 'string' ? ev.errorMsg : '';
      handleMicError(msg);
    });
    frame.on('nonfatal-error', (ev) => {
      const msg = ev && (ev.errorMsg || ev.error || ev.action || '');
      if (!msg) return;
      const s = String(msg);
      if (/camera|microphone|permission|media|device|getUserMedia/i.test(s)) {
        showCameraStuckHint(s);
      }
    });
    frame.on('error', (ev) => {
      const msg = ev && typeof ev.errorMsg === 'string' ? ev.errorMsg : '';
      handleDailyError('error', msg);
    });
    frame.on('network-connection', (ev) => {
      const quality = ev && ev.quality ? String(ev.quality) : '';
      if (quality === '0' || quality === 'poor') {
        showAlert(
          ALERT_REGION,
          'warning',
          'Network connection unstable.',
          'Your video and audio quality may be reduced. Consider switching to a stronger connection.'
        );
      }
    });
    frame.on('cam-error', (ev) => {
      const msg = ev && typeof ev.errorMsg === 'string' ? ev.errorMsg : '';
      handleCameraError(msg);
    });
    frame.on('mic-error', (ev) => {
      const msg = ev && typeof ev.errorMsg === 'string' ? ev.errorMsg : '';
      handleMicError(msg);
    });
    frame.on('left-meeting', () => {
      handleLeftMeeting();
    });
    frame.on('meeting-session-updated', () => {
      updateParticipantCountFromFrame();
    });
    frame.on('available-devices-updated', () => {
      // no-op; place-holder for future device selector UX
    });
    frame.on('active-speaker-change', () => {
      // no-op
    });
  }

  /* ----------------------------------------------------------------------
   *  2. Same-origin credential fetch (carries PHP session cookie).
   * ---------------------------------------------------------------------- */
  async function fetchJoinCredentials() {
    let response;
    try {
      response = await fetch(JOIN_ENDPOINT, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
    } catch (networkErr) {
      showAlert(
        ALERT_REGION,
        'danger',
        'Network problem.',
        'Could not reach the TeleHealth consultation server. Check your connection and refresh.'
      );
      hideInitializingSpinner();
      setPrecallMessage(
        'A network error prevented the room from loading. Please check your internet connection and try again.'
      );
      return null;
    }

    let payload = null;
    const text = await response.text();
    try {
      payload = text ? JSON.parse(text) : null;
    } catch (_) {
      payload = null;
    }

    if (!payload || typeof payload !== 'object') {
      showAlert(
        ALERT_REGION,
        'danger',
        'Video service unavailable.',
        'The server responded with an unexpected format. Please retry in a moment.'
      );
      hideInitializingSpinner();
      return null;
    }

    // HTTP 200 → expected { ok: true, room_url, token }
    if (response.status === 200 && payload.ok === true) {
      if (!isNonEmptyString(payload.room_url) || !isNonEmptyString(payload.token)) {
        showAlert(
          ALERT_REGION,
          'danger',
          'Video service unavailable.',
          'The server did not return valid credentials for this consultation.'
        );
        hideInitializingSpinner();
        return null;
      }
      return { room_url: payload.room_url, token: payload.token };
    }

    // Non-200 → map code → user-safe alert
    hideInitializingSpinner();
    const code = isNonEmptyString(payload.code) ? payload.code : String(response.status);
    const message = isNonEmptyString(payload.message)
      ? payload.message
      : 'Request could not be fulfilled.';

    switch (response.status) {
      case 401:
        setPrecallMessage('You must be signed in to join a consultation.');
        showAlert(
          ALERT_REGION,
          'danger',
          'Not authenticated.',
          message + ' Please sign in and try again.'
        );
        break;
      case 404:
        setPrecallMessage('This consultation could not be located.');
        showAlert(
          ALERT_REGION,
          'warning',
          message || 'Consultation not found.',
          'Return to the consultation list to locate your appointment.'
        );
        break;
      case 403:
        if (code === 'too_early') {
          setPrecallMessage('Consultation access opens shortly before the scheduled time.');
          showAlert(
            ALERT_REGION,
            'info',
            message || 'Consultation has not opened yet.',
            'You can join beginning 10 minutes before the scheduled start time.'
          );
        } else if (code === 'too_late') {
          setPrecallMessage('This consultation has ended.');
          showAlert(
            ALERT_REGION,
            'warning',
            message || 'Consultation has ended.',
            'If this is unexpected, please contact MBPHA administration for support.'
          );
        } else if (code === 'not_approved') {
          setPrecallMessage('This consultation is not available to join right now.');
          showAlert(
            ALERT_REGION,
            'warning',
            message || 'Consultation is not currently available.',
            'Please wait for MBPHA administration to approve this appointment.'
          );
        } else if (code === 'room_missing') {
          setPrecallMessage('Video room is not yet available.');
          showAlert(
            ALERT_REGION,
            'warning',
            message || 'Room not ready.',
            'The room is being prepared. Refresh the page in 30 seconds or contact administration.'
          );
        } else {
          setPrecallMessage('You are not authorized to join this consultation.');
          showAlert(
            ALERT_REGION,
            'danger',
            message || 'Not authorized.',
            'If you believe you should have access, please contact MBPHA administration.'
          );
        }
        break;
      case 500:
      case 502:
      case 503:
      case 504:
        setPrecallMessage('The video service could not be reached.');
        showAlert(
          ALERT_REGION,
          'danger',
          message || 'Video service unavailable.',
          'This is likely a temporary issue. Please refresh the page and try again.'
        );
        break;
      default:
        setPrecallMessage('Could not join the consultation.');
        showAlert(
          ALERT_REGION,
          'danger',
          message || 'Could not join.',
          'Please return to the consultation details page and try again.'
        );
    }

    return null;
  }

  /* ----------------------------------------------------------------------
   *  3. UX helpers: alerts, pre-call messaging, side-panel status, leave.
   * ---------------------------------------------------------------------- */
  function clearRegionAlerts() {
    if (!ALERT_REGION) return;
    ALERT_REGION.innerHTML = '';
  }

  function showAlert(region, variant, title, body) {
    if (!region) return;
    clearRegionAlerts();

    const div = document.createElement('div');
    div.className = 'alert alert-' + (variant || 'info') + ' alert-dismissible fade show';
    div.setAttribute('role', 'alert');

    const heading = document.createElement('div');
    heading.className = 'fw-semibold mb-1';
    heading.textContent = title || 'Attention';

    const content = document.createElement('div');
    content.className = 'small';
    content.textContent = body || '';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'alert');
    closeBtn.setAttribute('aria-label', 'Close');

    div.appendChild(heading);
    if (body) div.appendChild(content);
    div.appendChild(closeBtn);

    region.appendChild(div);
  }

  function renderFatalAlert(variant, title, body) {
    showAlert(ALERT_REGION, variant, title, body);
  }

  function hideInitializingSpinner() {
    const s = document.getElementById('vc-initializing-spinner');
    if (s) s.style.display = 'none';
  }

  function setPrecallMessage(msg) {
    const node = document.getElementById('vc-precall-state__message');
    if (node) node.textContent = msg || '';
  }

  function updateCallState(text) {
    const el = document.getElementById('vc-call-status-state');
    if (!el) return;
    el.textContent = text || 'Unknown';
    el.classList.remove('bg-info-soft', 'bg-teal-soft', 'bg-primary-soft', 'text-info-900', 'text-teal-900', 'text-primary-900');
    if (text === 'Connected' || text === 'In call') {
      el.classList.add('bg-teal-soft', 'text-teal-900');
    } else if (text === 'Ready' || text === 'Connecting…') {
      el.classList.add('bg-info-soft', 'text-info-900');
    } else {
      el.classList.add('bg-primary-soft', 'text-primary-900');
    }
  }

  function updateParticipantCountFromFrame() {
    const countEl = document.getElementById('vc-call-status-participants');
    if (!callFrame || !countEl) return;
    try {
      const participants = callFrame.participants() || {};
      countEl.textContent = String(Object.keys(participants).length);
    } catch (_) {
      countEl.textContent = '—';
    }
  }

  function setMediaStatus(kind, text) {
    const target = document.getElementById(
      kind === 'mic' ? 'vc-call-status-mic' : 'vc-call-status-camera'
    );
    if (!target) return;
    target.textContent = text || '—';
    target.classList.remove('text-muted', 'text-danger', 'text-success');
    if (text === 'On' || text === 'Off') {
      target.classList.add(text === 'On' ? 'text-success' : 'text-muted');
    } else if (text === 'Muted') {
      target.classList.add('text-muted');
    } else {
      target.classList.add('text-muted');
    }
  }

  function handleCameraError(raw) {
    const detail = isNonEmptyString(raw)
      ? ' (' + raw + ')'
      : '';
    showAlert(
      ALERT_REGION,
      'warning',
      'Camera unavailable.',
      'MBPHA TeleHealth could not access your camera. Please allow camera access in your browser settings and refresh' + detail + '.'
    );
    setMediaStatus('camera', 'Permission denied');
  }

  function handleMicError(raw) {
    const detail = isNonEmptyString(raw)
      ? ' (' + raw + ')'
      : '';
    showAlert(
      ALERT_REGION,
      'warning',
      'Microphone unavailable.',
      'MBPHA TeleHealth could not access your microphone. Please allow microphone access in your browser settings and refresh' + detail + '.'
    );
    setMediaStatus('mic', 'Permission denied');
  }

  function handleDailyError(kind, raw) {
    const detail = isNonEmptyString(raw)
      ? ': ' + raw
      : '.';
    showAlert(
      ALERT_REGION,
      'danger',
      'Video service error.',
      'The consultation experienced a problem — please refresh and try again' + detail
    );
  }

  function handleLeftMeeting() {
    clearRegionAlerts();
    showAlert(
      ALERT_REGION,
      'info',
      'Consultation ended.',
      'You have left the consultation. You may safely close this tab or return to your dashboard.'
    );
    if (CALL_STATUS_PANEL) CALL_STATUS_PANEL.classList.add('d-none');
    if (LEAVE_BTN_TOP) LEAVE_BTN_TOP.classList.add('d-none');
    updateCallState('Ended');
  }

  function onLeaveClicked() {
    if (!callFrame) return;
    try {
      callFrame.leave();
    } catch (_) {
      handleLeftMeeting();
    }
  }

  function showCameraStuckHint(rawMsg) {
    if (cameraCheckTimer) { clearTimeout(cameraCheckTimer); cameraCheckTimer = null; }
    const localhostLink = buildLocalhostRedirectLink();
    const local = isLikelyLocalhost();
    let detail = '';
    if (rawMsg) {
      try { detail = ' <span class="text-muted">(' + escapeHtml(String(rawMsg)) + ')</span>'; }
      catch (_) { /* noop */ }
    }
    let bodyHtml =
      '<p class="mb-2">' +
        'Daily is still waiting to access your camera and microphone. ' +
        'If the browser did not show a permission popup, the page may be missing camera rights ' +
        'or another program is holding the camera.' +
        detail +
      '</p>';
    if (!local) {
      bodyHtml +=
        '<p class="mb-2">' +
          'You are currently on <code>' + escapeHtml(window.location.hostname || '') + '</code>. ' +
          'Browsers only allow camera access on <strong>localhost</strong> or <strong>HTTPS</strong>. ' +
          'Reopen the room at:' +
        '</p>' +
        '<p class="mb-3">' +
          '<a class="btn btn-primary btn-sm" href="' + escapeAttr(localhostLink) + '">' +
            '<i class="bi bi-box-arrow-up-right me-1"></i>Open room on localhost' +
          '</a>' +
          '&nbsp;&nbsp;<code class="small align-middle text-break">' + escapeHtml(localhostLink) + '</code>' +
        '</p>';
    }
    bodyHtml +=
      '<h6 class="mb-1 text-body-emphasis">Quick fixes</h6>' +
      '<ol class="mb-0 small ps-3">' +
        '<li>Click the 🔒 or 🛈 icon in the address bar → <em>Site settings</em> → set Camera / Microphone to <strong>Allow</strong>, then refresh.</li>' +
        '<li>If you see a 🚫 (blocked camera) icon in the address bar, click it and choose <strong>Always allow</strong>.</li>' +
        '<li>Close Zoom, Teams, OBS, Discord, Meet, Webex or any other camera-using app, then refresh.</li>' +
        '<li>Windows users: open <em>Settings → Privacy &amp; security → Camera / Microphone</em> and confirm browser access is enabled.</li>' +
        '<li>Restart the browser entirely and reopen the consultation room page.</li>' +
      '</ol>';
    showAlertHtml(ALERT_REGION, 'warning', 'Camera / microphone stuck on "Checking"', bodyHtml);
    setMediaStatus('camera', 'Waiting…');
    setMediaStatus('mic', 'Waiting…');
  }

  function handleGetUserMediaFailed(raw) {
    const code = (raw || '').toLowerCase();
    let heading = 'Could not access camera or microphone';
    let short = 'The browser blocked device access. Use the troubleshooting steps below or reopen the room on localhost.';
    if (/notallowederror|permission|denied|blocked|notallowed/i.test(code)) {
      heading = 'Camera / microphone permission denied';
      short = 'You denied the permission prompt earlier. Allow camera/microphone in site settings and refresh.';
    } else if (/notreadableerror|inuse|busy|track|already/i.test(code)) {
      heading = 'Camera or microphone is already in use';
      short = 'Close Zoom, Teams, OBS, Discord, Meet or any other camera app and refresh this page.';
    } else if (/notfound|devices|enumerate|overconstrained/i.test(code)) {
      heading = 'No camera / microphone detected';
      short = 'Check that a camera and microphone are connected, turned on, and enabled in your OS privacy settings.';
    } else if (/security|secure|context|getusermedia|mediaDevices/i.test(code)) {
      heading = 'Camera blocked by browser security policy';
      short = 'getUserMedia() is only available on HTTPS or http://localhost. Reopen this room using the localhost link below.';
    }
    const localhostLink = buildLocalhostRedirectLink();
    const local = isLikelyLocalhost();
    let bodyHtml = '<p class="mb-2">' + short;
    if (raw) bodyHtml += ' <span class="text-muted">(' + escapeHtml(raw) + ')</span>';
    bodyHtml += '</p>';
    if (!local) {
      bodyHtml +=
        '<p class="mb-3">' +
          '<a class="btn btn-primary btn-sm" href="' + escapeAttr(localhostLink) + '">' +
            '<i class="bi bi-box-arrow-up-right me-1"></i>Open room on localhost' +
          '</a>' +
          '&nbsp;&nbsp;<code class="small align-middle text-break">' + escapeHtml(localhostLink) + '</code>' +
        '</p>';
    }
    bodyHtml +=
      '<h6 class="mb-1 text-body-emphasis">Troubleshooting</h6>' +
      '<ol class="mb-0 small ps-3">' +
        '<li>Address bar → 🔒/🛈 → <em>Site settings</em> → Camera: Allow, Microphone: Allow. Refresh.</li>' +
        '<li>Close Zoom, Teams, OBS, Discord, Meet, Webex.</li>' +
        '<li>Windows: Settings → Privacy &amp; security → Camera / Microphone → desktop apps toggled ON.</li>' +
        '<li>Restart the browser.</li>' +
      '</ol>';
    showAlertHtml(ALERT_REGION, 'danger', heading, bodyHtml);
    if (/permission|denied|blocked|notallowed/i.test(code)) {
      setMediaStatus('camera', 'Permission denied');
      setMediaStatus('mic', 'Permission denied');
    } else {
      setMediaStatus('camera', 'Unavailable');
      setMediaStatus('mic', 'Unavailable');
    }
  }

  /* ----------------------------------------------------------------------
   *  4. Tiny utility helpers
   * ---------------------------------------------------------------------- */
  function isNonEmptyString(x) {
    return typeof x === 'string' && x.trim().length > 0;
  }

  /**
   * Poll for the Daily Prebuilt factory becoming available on the
   * global window object.  The CDN <script> is synchronous, but on
   * slow/flaky networks or with the page-wiring script racing the
   * CDN (e.g. cached vs. fresh load), a short poll smooths the edge
   * case without surfacing "library not loaded" false-negatives to
   * the clinician or patient.
   *
   * @param {number} maxWaitMs Maximum milliseconds to poll.
   * @returns {Promise<typeof window.DailyIframe|undefined>}
   */
  async function waitForDailyFactory(maxWaitMs) {
    const startTime = Date.now();
    const pollIntervalMs = 80;

    function resolveFactory() {
      if (typeof window.DailyIframe !== 'undefined' && typeof window.DailyIframe.createFrame === 'function') {
        return window.DailyIframe;
      }
      if (typeof window.Daily !== 'undefined' && typeof window.Daily.createFrame === 'function') {
        return window.Daily;
      }
      if (typeof window.DailyJs !== 'undefined' && typeof window.DailyJs.createFrame === 'function') {
        return window.DailyJs;
      }
      return undefined;
    }

    // Fast path: already available (the common case).
    const instant = resolveFactory();
    if (instant) return instant;

    // Slow path: poll until maxWaitMs expires or the onerror handler fired.
    return new Promise(function (resolve) {
      var timer = setInterval(function () {
        var resolved = resolveFactory();
        if (resolved) {
          clearInterval(timer);
          resolve(resolved);
          return;
        }
        if (window.__dailyJsLoadFailed) {
          clearInterval(timer);
          resolve(undefined);
          return;
        }
        if (Date.now() - startTime >= maxWaitMs) {
          clearInterval(timer);
          resolve(undefined);
        }
      }, pollIntervalMs);
    });
  }

  function safeErrorMessage(err) {
    if (!err) return 'Unexpected error.';
    if (typeof err === 'string') return err || 'Unexpected error.';
    if (typeof err.message === 'string' && err.message.trim() !== '') {
      return err.message;
    }
    return 'Unexpected error. Please refresh and try again.';
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  function escapeAttr(str) {
    return escapeHtml(str);
  }

  function showAlertHtml(region, variant, title, bodyHtml) {
    if (!region) return;
    const div = document.createElement('div');
    div.className = 'alert alert-' + String(variant || 'primary') + ' alert-dismissible fade show';
    div.setAttribute('role', 'alert');

    const heading = document.createElement('div');
    heading.className = 'fw-semibold mb-1';
    heading.textContent = title || 'Attention';

    const content = document.createElement('div');
    content.className = 'small';
    content.innerHTML = bodyHtml || '';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'alert');
    closeBtn.setAttribute('aria-label', 'Close');

    div.appendChild(heading);
    if (bodyHtml) div.appendChild(content);
    div.appendChild(closeBtn);

    region.appendChild(div);
  }
})();
