<?php

use App\Core\Csrf;
use App\Helpers\Helper;
use App\Services\PatientAiAssistantService;

if (($dashboardRole ?? '') !== 'patient') {
    return;
}

$currentPath = Helper::currentPath();
if (str_contains($currentPath, '/room')) {
    return;
}

$csrfToken = Csrf::generate();
$chatEndpoint = Helper::url('/patient/ai-assistant/chat');
$suggestedQuestions = PatientAiAssistantService::suggestedQuestions();
$maxMessageLength = PatientAiAssistantService::MAX_MESSAGE_LENGTH;
$medimateAvatar = Helper::asset('images/MediMate.png');

$suggestionIcons = [
    'What is hypertension?' => 'bi-heart-pulse',
    'What is diabetes?' => 'bi-droplet',
    'How can I prepare for my consultation?' => 'bi-clipboard',
    'How do I book a consultation?' => 'bi-calendar2',
];
?>

<div
  class="medimate"
  data-ai-assistant
  data-chat-endpoint="<?= Helper::escape($chatEndpoint) ?>"
  data-avatar="<?= Helper::escape($medimateAvatar) ?>"
  data-max-length="<?= (int) $maxMessageLength ?>"
>
  <button
    type="button"
    class="medimate-fab"
    data-medimate-open
    aria-label="Open MediMate AI"
    aria-expanded="false"
    aria-controls="medimate-panel"
  >
    <img src="<?= Helper::escape($medimateAvatar) ?>" alt="" width="70" height="70">
  </button>

  <section
    id="medimate-panel"
    class="medimate-panel"
    data-medimate-panel
    hidden
    role="dialog"
    aria-labelledby="medimate-title"
    aria-modal="false"
  >
    <header class="medimate-header">
      <div class="medimate-header__identity">
        <span class="medimate-header__icon" aria-hidden="true">
          <img src="<?= Helper::escape($medimateAvatar) ?>" alt="" width="40" height="40">
        </span>
        <div class="medimate-header__copy">
          <h2 id="medimate-title" class="medimate-header__title">MediMate AI</h2>
          <p class="medimate-header__subtitle">Your health information assistant</p>
        </div>
      </div>
      <div class="medimate-header__actions">
        <span class="medimate-online">
          <span class="medimate-online__dot" aria-hidden="true"></span>
          Online
        </span>
        <span class="medimate-header__rule" aria-hidden="true"></span>
        <button
          type="button"
          class="medimate-close"
          data-medimate-close
          aria-label="Close MediMate AI"
        >
          <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
      </div>
    </header>

    <div class="medimate-thread" data-ai-thread tabindex="0" aria-live="polite" aria-relevant="additions">
      <div class="medimate-welcome" data-ai-welcome>
        <div class="medimate-msg medimate-msg--assistant">
          <span class="medimate-msg__avatar" aria-hidden="true">
            <img src="<?= Helper::escape($medimateAvatar) ?>" alt="" width="32" height="32">
          </span>
          <div class="medimate-msg__col">
            <span class="medimate-msg__label">MediMate AI</span>
            <div class="medimate-msg__bubble">
              <p>Hello! I’m MediMate AI. I can provide general health information and help you understand topics before or after your consultation.</p>
            </div>
          </div>
        </div>
        <?php if ($suggestedQuestions !== []): ?>
          <div class="medimate-suggestions" role="list">
            <?php foreach ($suggestedQuestions as $suggestion): ?>
              <?php
              $suggestion = trim((string) $suggestion);
              if ($suggestion === '') {
                  continue;
              }
              $chipIcon = $suggestionIcons[$suggestion] ?? 'bi-chat-dots';
              ?>
              <button type="button" class="medimate-chip" data-ai-suggestion role="listitem">
                <i class="bi <?= Helper::escape($chipIcon) ?>" aria-hidden="true"></i>
                <span><?= Helper::escape($suggestion) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="medimate-dock">
      <form class="medimate-composer" data-ai-form novalidate>
        <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
        <label class="visually-hidden" for="medimate-input">Type your question</label>
        <textarea
          id="medimate-input"
          class="medimate-input"
          name="message"
          rows="1"
          maxlength="<?= (int) $maxMessageLength ?>"
          placeholder="Type your question..."
          data-ai-input
          autocomplete="off"
        ></textarea>
        <button type="submit" class="medimate-send" data-ai-send aria-label="Send message">
          <i class="bi bi-send-fill" aria-hidden="true"></i>
          <span>Send</span>
        </button>
      </form>
      <p class="medimate-disclaimer">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        AI-generated information is for general educational purposes and does not replace advice from a qualified healthcare professional.
      </p>
    </div>
  </section>
</div>
