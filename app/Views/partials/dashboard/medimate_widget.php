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
$conversationEndpoint = Helper::url('/patient/ai-assistant/conversation');
$conversationsEndpoint = Helper::url('/patient/ai-assistant/conversations');
$deleteEndpoint = Helper::url('/patient/ai-assistant/conversation/delete');
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
  class="medimate medimate-widget"
  data-ai-assistant
  data-chat-endpoint="<?= Helper::escape($chatEndpoint) ?>"
  data-conversation-endpoint="<?= Helper::escape($conversationEndpoint) ?>"
  data-conversations-endpoint="<?= Helper::escape($conversationsEndpoint) ?>"
  data-delete-endpoint="<?= Helper::escape($deleteEndpoint) ?>"
  data-avatar="<?= Helper::escape($medimateAvatar) ?>"
  data-max-length="<?= (int) $maxMessageLength ?>"
>
  <button
    type="button"
    class="medimate-fab medimate-launcher"
    data-medimate-open
    aria-label="Open MediMate AI"
    aria-expanded="false"
    aria-controls="medimate-panel"
  >
    <img src="<?= Helper::escape($medimateAvatar) ?>" alt="" width="70" height="70">
    <span class="medimate-fab__online" aria-hidden="true"></span>
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
      <div class="medimate-header__identity medimate-header-identity">
        <span class="medimate-header__icon medimate-header-avatar" aria-hidden="true">
          <img src="<?= Helper::escape($medimateAvatar) ?>" alt="" width="52" height="52">
        </span>
        <div class="medimate-header__copy">
          <h2 id="medimate-title" class="medimate-header__title">MediMate AI</h2>
          <p class="medimate-header__subtitle">Your health information assistant</p>
        </div>
      </div>
      <div class="medimate-header__actions medimate-header-actions">
        <span class="medimate-online medimate-header-status">
          <span class="medimate-online__dot" aria-hidden="true"></span>
          Online
        </span>
        <span class="medimate-header__rule" aria-hidden="true"></span>
        <button
          type="button"
          class="medimate-icon-btn"
          data-medimate-history
          aria-label="Previous conversations"
          aria-expanded="false"
          aria-controls="medimate-history"
        >
          <i class="bi bi-clock-history" aria-hidden="true"></i>
        </button>
        <button
          type="button"
          class="medimate-icon-btn"
          data-medimate-new
          aria-label="New chat"
        >
          <i class="bi bi-plus-lg" aria-hidden="true"></i>
        </button>
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

    <div class="medimate-history" id="medimate-history" data-medimate-history-panel hidden>
      <p class="medimate-history__title">Previous conversations</p>
      <div class="medimate-history__list" data-medimate-history-list></div>
    </div>

    <div class="medimate-thread medimate-conversation" data-ai-thread tabindex="0" aria-live="polite" aria-relevant="additions">
      <div class="medimate-welcome" data-ai-welcome>
        <div class="medimate-msg medimate-msg--assistant medimate-assistant-message">
          <span class="medimate-msg__avatar medimate-avatar" aria-hidden="true">
            <img src="<?= Helper::escape($medimateAvatar) ?>" alt="" width="36" height="36">
          </span>
          <div class="medimate-msg__col">
            <div class="medimate-msg__head">
              <span class="medimate-msg__label">MediMate AI</span>
            </div>
            <div class="medimate-msg__bubble medimate-message-bubble">
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
        <div class="medimate-input-wrapper">
          <span class="medimate-attach" aria-hidden="true">
            <i class="bi bi-paperclip"></i>
          </span>
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
        </div>
        <button type="submit" class="medimate-send" data-ai-send aria-label="Send message">
          <i class="bi bi-send-fill" aria-hidden="true"></i>
          <span>Send</span>
        </button>
      </form>
    </div>
  </section>
</div>
