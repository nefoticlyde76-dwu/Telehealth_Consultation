/* ======================================================================
 * Prescription medication rows (doctor create form)
 * ====================================================================== */
(function () {
  'use strict';

  const form = document.getElementById('rx-create-form');
  const rowsHost = document.getElementById('rx-medication-rows');
  const addButton = document.getElementById('rx-add-medication');
  if (!(form instanceof HTMLFormElement) || !(rowsHost instanceof HTMLElement) || !(addButton instanceof HTMLButtonElement)) {
    return;
  }

  const MAX_ROWS = 12;
  const reviewTargets = [
    document.getElementById('rx-review-summary'),
    document.getElementById('rx-modal-review'),
  ];

  addButton.addEventListener('click', function () {
    const rows = rowsHost.querySelectorAll('[data-rx-medication-row]');
    if (rows.length >= MAX_ROWS) {
      return;
    }
    const template = rows[0];
    if (!(template instanceof HTMLElement)) {
      return;
    }
    const clone = template.cloneNode(true);
    if (!(clone instanceof HTMLElement)) {
      return;
    }
    const nextIndex = rows.length;
    resetRowFields(clone);
    rebindRow(clone, nextIndex);
    rowsHost.appendChild(clone);
    refreshRemoveButtons();
    refreshAddButton();
    updateReview();
    const firstField = clone.querySelector('input, textarea');
    if (firstField instanceof HTMLElement) {
      firstField.focus();
    }
  });

  rowsHost.addEventListener('click', function (event) {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.closest('[data-rx-remove-row]')) {
      return;
    }
    const rows = rowsHost.querySelectorAll('[data-rx-medication-row]');
    if (rows.length <= 1) {
      return;
    }
    const row = target.closest('[data-rx-medication-row]');
    if (row instanceof HTMLElement) {
      row.remove();
      reindexRows();
      refreshRemoveButtons();
      refreshAddButton();
      updateReview();
    }
  });

  rowsHost.addEventListener('input', updateReview);

  const finalizeBtn = document.getElementById('rx-finalize-btn');
  if (finalizeBtn instanceof HTMLElement) {
    finalizeBtn.addEventListener('click', updateReview);
  }

  form.addEventListener('submit', function (event) {
    const firstName = form.querySelector('input[name="medications[0][medication_name]"]');
    const firstDosage = form.querySelector('input[name="medications[0][dosage]"]');
    const firstFrequency = form.querySelector('input[name="medications[0][frequency]"]');
    const ready = firstName instanceof HTMLInputElement
      && firstDosage instanceof HTMLInputElement
      && firstFrequency instanceof HTMLInputElement
      && firstName.value.trim() !== ''
      && firstDosage.value.trim() !== ''
      && firstFrequency.value.trim() !== '';
    markRequired(firstName);
    markRequired(firstDosage);
    markRequired(firstFrequency);
    if (ready) {
      return;
    }
    event.preventDefault();
    window.alert('Enter at least one medication with name, dosage, and directions before saving the prescription.');
  });

  function resetRowFields(row) {
    row.querySelectorAll('input, textarea').forEach(function (field) {
      if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
        return;
      }
      field.value = '';
      field.classList.remove('is-invalid');
    });
  }

  function rebindRow(row, index) {
    row.querySelectorAll('input, textarea').forEach(function (field) {
      if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
        return;
      }
      field.name = String(field.name || '').replace(/medications\[\d+]/, 'medications[' + index + ']');
      if (field.id) {
        field.id = String(field.id).replace(/-\d+$/, '-' + index);
      }
    });
    row.querySelectorAll('label[for]').forEach(function (label) {
      if (!(label instanceof HTMLLabelElement)) {
        return;
      }
      label.htmlFor = String(label.htmlFor || '').replace(/-\d+$/, '-' + index);
    });
  }

  function reindexRows() {
    rowsHost.querySelectorAll('[data-rx-medication-row]').forEach(function (row, index) {
      if (row instanceof HTMLElement) {
        rebindRow(row, index);
      }
    });
  }

  function refreshRemoveButtons() {
    const rows = rowsHost.querySelectorAll('[data-rx-medication-row]');
    rows.forEach(function (row) {
      const button = row.querySelector('[data-rx-remove-row]');
      if (!(button instanceof HTMLElement)) {
        return;
      }
      if (rows.length > 1) {
        button.classList.remove('d-none');
      } else {
        button.classList.add('d-none');
      }
    });
  }

  function refreshAddButton() {
    const rows = rowsHost.querySelectorAll('[data-rx-medication-row]');
    addButton.hidden = rows.length >= MAX_ROWS;
  }

  function markRequired(field) {
    if (!(field instanceof HTMLInputElement)) {
      return;
    }
    if (field.value.trim() === '') {
      field.classList.add('is-invalid');
    } else {
      field.classList.remove('is-invalid');
    }
  }

  function fieldValue(row, namePart) {
    const field = row.querySelector('[name*="[' + namePart + ']"]');
    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
      return field.value.trim();
    }
    return '';
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function collectMedications() {
    const items = [];
    rowsHost.querySelectorAll('[data-rx-medication-row]').forEach(function (row) {
      if (!(row instanceof HTMLElement)) {
        return;
      }
      const item = {
        name: fieldValue(row, 'medication_name'),
        dosage: fieldValue(row, 'dosage'),
        frequency: fieldValue(row, 'frequency'),
        duration: fieldValue(row, 'duration'),
        quantity: fieldValue(row, 'quantity'),
        notes: fieldValue(row, 'additional_notes'),
      };
      if (item.name || item.dosage || item.frequency) {
        items.push(item);
      }
    });
    return items;
  }

  function renderReview(target, items) {
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const patientName = String(form.getAttribute('data-rx-patient-name') || 'Patient');
    if (items.length === 0) {
      target.innerHTML = '<p class="rx-review__empty">Enter a medication to see a concise summary here.</p>';
      return;
    }

    const rows = items.map(function (item) {
      const notes = item.notes !== ''
        ? '<div class="rx-review__notes"><dt>Instructions</dt><dd>' + escapeHtml(item.notes) + '</dd></div>'
        : '';
      return (
        '<article class="rx-review__item">' +
          '<h4>' + escapeHtml(item.name || 'Medication') + '</h4>' +
          '<dl>' +
            '<div><dt>Dosage</dt><dd>' + escapeHtml(item.dosage || '—') + '</dd></div>' +
            '<div><dt>Frequency</dt><dd>' + escapeHtml(item.frequency || '—') + '</dd></div>' +
            '<div><dt>Duration</dt><dd>' + escapeHtml(item.duration || '—') + '</dd></div>' +
            '<div><dt>Quantity</dt><dd>' + escapeHtml(item.quantity || '—') + '</dd></div>' +
            notes +
          '</dl>' +
        '</article>'
      );
    }).join('');

    target.innerHTML = '<p class="rx-review__patient">Patient <strong>' + escapeHtml(patientName) + '</strong></p>' + rows;
  }

  function updateReview() {
    const items = collectMedications();
    reviewTargets.forEach(function (target) {
      renderReview(target, items);
    });
  }

  refreshRemoveButtons();
  refreshAddButton();
  updateReview();
})();
