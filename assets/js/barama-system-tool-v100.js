/* BARAMA_SYSTEM_TOOL_V100: one-use Change Confirmation per protected POST.
 * No passcodes or grants are stored persistently; existing middleware remains authoritative.
 */
(() => {
  'use strict';
  const root = document.getElementById('barama-system-tool');
  if (!root) return;
  const enabled = root.dataset.passcodeEnabled === '1';
  if (!enabled) return; // Server middleware independently enforces account policy.
  const dialog = document.getElementById('btool-passcode-dialog');
  const input = document.getElementById('btool-passcode');
  const feedback = document.getElementById('btool-passcode-feedback');
  const approve = document.getElementById('btool-passcode-submit');
  const cancel = document.getElementById('btool-passcode-cancel');
  if (!dialog || !input || !feedback || !approve || !cancel) return;

  let submitting = false;
  async function requestGrant(csrf, passcode) {
    const body = new FormData();
    body.set('_token', csrf);
    body.set('passcode', passcode);
    const response = await fetch('/security/change-confirmation', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
    });
    const result = await response.json();
    if (!response.ok || result?.ok !== true || !/^[a-f0-9]{48}$/.test(result.grant || '')) {
      throw new Error(response.status === 422
        ? 'Passcode rejected. Check your code and try again.'
        : 'Change Confirmation was not granted. No operation was started.');
    }
    return result.grant;
  }

  function askPasscode() {
    return new Promise((resolve) => {
      feedback.textContent = '';
      input.value = '';
      dialog.showModal();
      input.focus();
      let done = false;
      function finish(value) {
        if (done) return;
        done = true;
        approve.removeEventListener('click', yes);
        cancel.removeEventListener('click', no);
        input.removeEventListener('keydown', enter);
        dialog.removeEventListener('cancel', esc);
        input.value = '';
        if (dialog.open) dialog.close();
        resolve(value);
      }
      const yes = () => {
        const passcode = input.value.trim();
        if (!/^[0-9]{6}$/.test(passcode)) {
          feedback.textContent = 'Enter the six-digit passcode to proceed.';
          return;
        }
        finish(passcode);
      };
      const no = () => finish(null);
      const esc = (event) => { event.preventDefault(); finish(null); };
      const enter = (event) => {
        if (event.key === 'Enter') { event.preventDefault(); yes(); }
      };
      approve.addEventListener('click', yes);
      cancel.addEventListener('click', no);
      input.addEventListener('keydown', enter);
      dialog.addEventListener('cancel', esc);
    });
  }

  for (const form of root.querySelectorAll('form[data-btool-protected]')) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (submitting) return;
      const csrf = form.querySelector('input[name="_token"]')?.value || '';
      if (!/^[a-f0-9]{64}$/.test(csrf)) {
        feedback.textContent = 'Your session has expired. Refresh the page before retrying.';
        return;
      }
      if (form.matches('[data-btool-restore]')) {
        const typed = form.querySelector('input[name="confirmation"]')?.value.trim();
        if (typed !== 'RESTORE DATABASE') {
          feedback.textContent = 'Type RESTORE DATABASE before authorizing a restore.';
          return;
        }
      }
      const submitter = event.submitter;
      const passcode = await askPasscode();
      if (!passcode) return;
      submitting = true;
      try {
        const grant = await requestGrant(csrf, passcode);
        const old = form.querySelector('input[name="change_confirmation"]');
        if (old) old.remove();
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'change_confirmation';
        hidden.value = grant;
        form.appendChild(hidden);
        // Form.submit() excludes the clicked submit button: preserve importer mode.
        if (submitter?.name && submitter.value) {
          const selected = document.createElement('input');
          selected.type = 'hidden';
          selected.name = submitter.name;
          selected.value = submitter.value;
          form.appendChild(selected);
        }
        HTMLFormElement.prototype.submit.call(form); // Existing CSRF + passcode middleware validates it.
      } catch (error) {
        submitting = false;
        input.value = '';
        dialog.showModal();
        feedback.textContent = error instanceof Error ? error.message : 'Request blocked. No change submitted.';
        input.focus();
      }
    });
  }
})();
