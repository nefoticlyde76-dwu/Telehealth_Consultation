/* ======================================================================
 * Week 7 Day 3 — prescription medication rows (doctor create form)
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
    clone.querySelectorAll('input, textarea').forEach(function (field) {
      if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
        return;
      }
      field.value = '';
      field.name = String(field.name || '').replace(/medications\[\d+]/, 'medications[' + nextIndex + ']');
    });
    rowsHost.appendChild(clone);
    refreshRemoveButtons();
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
    }
  });

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
    if (ready) {
      return;
    }
    event.preventDefault();
    window.alert('Enter at least one medication with name, dosage, and directions before saving the prescription.');
  });

  function reindexRows() {
    rowsHost.querySelectorAll('[data-rx-medication-row]').forEach(function (row, index) {
      row.querySelectorAll('input, textarea').forEach(function (field) {
        if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
          return;
        }
        field.name = String(field.name || '').replace(/medications\[\d+]/, 'medications[' + index + ']');
      });
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

  refreshRemoveButtons();
})();
