/* BARAMA_FINANCE_PLANS_PREMIUM_V139 */
(() => {
    'use strict';

    const workspace = document.querySelector('[data-plans-workspace]');
    const modal = document.querySelector('[data-plan-modal]');
    const form = document.querySelector('[data-plan-form]');
    const presetNode = document.getElementById('finance-plan-presets');

    if (!workspace || !modal || !form || !presetNode) return;

    let presets = {};

    try {
        presets = JSON.parse(presetNode.textContent || '{}');
    } catch (_) {
        presets = {};
    }

    const title = modal.querySelector('[data-plan-title]');
    const subtitle = modal.querySelector('[data-plan-subtitle]');
    const submitLabel = modal.querySelector('[data-plan-submit-label]');
    const notice = modal.querySelector('[data-plan-notice]');
    let lastTrigger = null;
    let slugTouched = false;

    const field = (name) => form.elements.namedItem(name);

    const setValue = (name, value) => {
        const input = field(name);
        if (!input) return;
        input.value = value ?? '';
    };

    const slugify = (value) => String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const resetCreate = () => {
        form.reset();
        form.action = `${window.location.origin}/dashboard/plans`;
        title.textContent = 'Create Plan';
        subtitle.textContent = 'Configure pricing and commercial entitlements.';
        submitLabel.textContent = 'Create inactive plan';
        notice.textContent = 'New plans are created inactive. Review them before activation.';
        slugTouched = false;
    };

    const applyPreset = (key) => {
        if (key === 'custom') {
            resetCreate();
            return;
        }

        const preset = presets[key];
        if (!preset) return;

        resetCreate();

        Object.entries(preset).forEach(([name, value]) => {
            setValue(name, value);
        });

        slugTouched = true;
        subtitle.textContent = `${preset.name} preset · every field remains editable`;
    };

    const applyPlan = (payload, duplicate = false) => {
        resetCreate();

        Object.entries(payload).forEach(([name, value]) => {
            if (field(name)) {
                setValue(name, value);
            }
        });

        if (duplicate) {
            const originalName = payload.name || 'Plan';
            setValue('name', `${originalName} Copy`);
            setValue('slug', `${slugify(originalName)}-copy`);
            title.textContent = 'Duplicate Plan';
            subtitle.textContent = 'Create a new inactive plan from the selected configuration.';
            submitLabel.textContent = 'Create duplicate';
            slugTouched = true;
            return;
        }

        form.action = `${window.location.origin}/dashboard/plans/${payload.id}`;
        title.textContent = 'Edit Plan';
        subtitle.textContent = `Update ${payload.name || 'plan'} pricing and entitlements.`;
        submitLabel.textContent = 'Save plan changes';
        notice.textContent = 'Editing preserves the current activation status and subscriptions.';
        slugTouched = true;
    };

    const show = (trigger) => {
        lastTrigger = trigger;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('finance-plan-modal-open');

        modal
            .querySelector('input[name="name"]')
            ?.focus({preventScroll: true});
    };

    const hide = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('finance-plan-modal-open');
        lastTrigger?.focus({preventScroll: true});
        lastTrigger = null;
    };

    document.addEventListener('click', (event) => {
        const open = event.target.closest('[data-plan-open]');
        if (open) {
            resetCreate();
            show(open);
            return;
        }

        const preset = event.target.closest('[data-plan-preset]');
        if (preset && !preset.disabled) {
            const key = preset.getAttribute('data-plan-preset') || 'custom';
            applyPreset(key);
            show(preset);
            return;
        }

        const edit = event.target.closest('[data-plan-edit]');
        if (edit) {
            try {
                const payload = JSON.parse(
                    edit.getAttribute('data-plan-edit') || '{}'
                );
                applyPlan(payload, false);
                show(edit);
            } catch (_) {
                return;
            }
            return;
        }

        const duplicate = event.target.closest('[data-plan-duplicate]');
        if (duplicate) {
            try {
                const payload = JSON.parse(
                    duplicate.getAttribute('data-plan-duplicate') || '{}'
                );
                applyPlan(payload, true);
                show(duplicate);
            } catch (_) {
                return;
            }
            return;
        }

        if (event.target.closest('[data-plan-close]')) {
            hide();
        }
    });

    field('slug')?.addEventListener('input', () => {
        slugTouched = true;
    });

    field('name')?.addEventListener('input', (event) => {
        if (!slugTouched) {
            setValue('slug', slugify(event.target.value));
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            hide();
        }
    });
})();
