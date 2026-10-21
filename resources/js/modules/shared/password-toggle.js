export function bindPasswordToggles(scope = document) {
    const root = scope?.querySelector ? scope : document;

    root.querySelectorAll('[data-password-toggle]').forEach((toggleButton) => {
        if (toggleButton.dataset.passwordToggleBound === 'true') {
            return;
        }

        toggleButton.dataset.passwordToggleBound = 'true';

        const targetId = toggleButton.getAttribute('data-password-toggle');
        const input = root.querySelector(`#${CSS.escape(targetId)}`) ?? document.getElementById(targetId);
        const icon = toggleButton.querySelector('i');

        if (!input || !icon) {
            return;
        }

        const syncPasswordToggle = () => {
            const hidden = input.type === 'password';
            icon.classList.toggle('fa-eye', hidden);
            icon.classList.toggle('fa-eye-slash', !hidden);
            const label = hidden ? 'Show password' : 'Hide password';
            toggleButton.setAttribute('aria-label', label);
            toggleButton.setAttribute('title', label);
        };

        syncPasswordToggle();

        toggleButton.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            syncPasswordToggle();
        });
    });
}
