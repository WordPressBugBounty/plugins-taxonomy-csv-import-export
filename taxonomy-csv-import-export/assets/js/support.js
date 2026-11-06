// Support button: open the question dialog and send the form through admin-ajax.
(() => {
    'use strict';

    const dialog = document.getElementById('taxocsie-support-dialog');
    const openers = Array.from(document.querySelectorAll('[data-support-open]'));
    if (!dialog || !openers.length) {
        return;
    }

    const labels = window.taxocsieSupport || {};
    const form = dialog.querySelector('[data-support-form]');
    const done = dialog.querySelector('[data-support-done]');
    const doneText = dialog.querySelector('[data-support-done-text]');
    const error = dialog.querySelector('[data-support-error]');
    const counter = dialog.querySelector('[data-support-count]');
    const submit = form.querySelector('[type="submit"]');
    const message = form.elements.message;
    let opener = null;

    // Without <dialog> support, send people to the contact page instead.
    if (typeof dialog.showModal !== 'function') {
        openers.forEach((button) => button.addEventListener('click', () => {
            if (labels.contactUrl) {
                window.open(labels.contactUrl, '_blank', 'noopener');
            }
        }));
        return;
    }

    const updateCount = () => {
        counter.textContent = (labels.count || '%1$d / %2$d')
            .replace('%1$d', message.value.length)
            .replace('%2$d', message.maxLength);
    };

    const setBusy = (busy) => {
        submit.disabled = busy;
        submit.textContent = busy ? (labels.sending || '...') : (labels.send || submit.textContent);
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
    };

    const showError = (text) => {
        error.textContent = `${text} `;
        if (labels.contactUrl) {
            const link = document.createElement('a');
            link.href = labels.contactUrl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = labels.contactLink || labels.contactUrl;
            error.append(link);
        }
        error.hidden = false;
    };

    // After a successful send, start the next question with an empty message.
    const resetAfterSend = () => {
        message.value = '';
        form.hidden = false;
        done.hidden = true;
        error.hidden = true;
        setBusy(false);
        updateCount();
    };

    openers.forEach((button) => button.addEventListener('click', () => {
        opener = button;
        if (!done.hidden) {
            resetAfterSend();
        }
        dialog.showModal();
        (form.elements.email.value ? message : form.elements.email).focus();
    }));

    dialog.querySelectorAll('[data-support-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    // A click on the dialog element itself is a click on the backdrop.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('close', () => {
        if (!done.hidden) {
            resetAfterSend();
        }
        if (opener) {
            opener.focus();
        }
    });

    message.addEventListener('input', updateCount);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) {
            return;
        }
        error.hidden = true;
        setBusy(true);
        try {
            const response = await fetch(labels.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form),
            });
            const result = await response.json().catch(() => null);
            if (!result || !result.success) {
                throw new Error(result && result.data && result.data.message ? result.data.message : labels.error);
            }
            doneText.textContent = result.data.message;
            form.hidden = true;
            done.hidden = false;
            done.focus();
        } catch (err) {
            setBusy(false);
            showError(err.message || labels.error);
        }
    });

    updateCount();
})();
