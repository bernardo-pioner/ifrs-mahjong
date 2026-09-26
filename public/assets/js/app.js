document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.nav-toggle');
    const backdrop = document.querySelector('.sidebar-backdrop');

    const setOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle?.addEventListener('click', () => {
        setOpen(!document.body.classList.contains('sidebar-open'));
    });

    backdrop?.addEventListener('click', () => setOpen(false));

    document.querySelectorAll('form.form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.classList.add('is-busy');
        });
    });

    const flash = document.querySelector('.flash');
    if (flash) {
        window.setTimeout(() => {
            flash.classList.add('is-hiding');
        }, 4200);
    }
});
