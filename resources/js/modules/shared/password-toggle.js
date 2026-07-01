export function bindPasswordToggles(scope = document) {
    const root = scope?.querySelector ? scope : document;

    root.querySelectorAll('[data-password-toggle]').forEach((toggleButton) => {
        if (toggleButton.dataset.passwordToggleBound === 'true') {
            return;
        }

        toggleButton.dataset.passwordToggleBound = 'true';

        toggleButton.addEventListener('click', () => {
            const targetId = toggleButton.getAttribute('data-password-toggle');
            const input = root.querySelector(`#${CSS.escape(targetId)}`) ?? document.getElementById(targetId);
            const icon = toggleButton.querySelector('i');

            if (!input || !icon) {
                return;
            }

            const shouldShow = input.type === 'password';

            input.type = shouldShow ? 'text' : 'password';
            icon.classList.toggle('fa-eye', shouldShow);
            icon.classList.toggle('fa-eye-slash', !shouldShow);
        });
    });
}
