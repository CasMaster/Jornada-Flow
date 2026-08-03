(() => {
  const saved = localStorage.getItem('hibrido-theme');
  const preferred = window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.dataset.theme = saved || preferred;

  document.addEventListener('DOMContentLoaded', () => {
    const nav = document.querySelector('.topbar nav');
    if (!nav) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'theme-toggle';

    const update = () => {
      const dark = document.documentElement.dataset.theme === 'dark';
      button.innerHTML = `<span aria-hidden="true">${dark ? '☀' : '☾'}</span><b>${dark ? 'Tema claro' : 'Tema escuro'}</b>`;
      button.setAttribute('aria-label', dark ? 'Ativar tema claro' : 'Ativar tema escuro');
      button.setAttribute('title', dark ? 'Ativar tema claro' : 'Ativar tema escuro');
    };

    button.addEventListener('click', () => {
      const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      localStorage.setItem('hibrido-theme', next);
      update();
    });
    nav.prepend(button);
    update();
  });
})();
