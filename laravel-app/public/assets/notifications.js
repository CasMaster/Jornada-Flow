document.querySelectorAll('[data-flash]').forEach((message) => {
  const dismiss = () => {
    if (message.classList.contains('is-leaving')) return;
    message.classList.add('is-leaving');
    window.setTimeout(() => message.remove(), 200);
  };

  message.querySelector('[data-flash-close]')?.addEventListener('click', dismiss);

  const timeout = Number(message.dataset.timeout || 0);
  if (timeout > 0) {
    message.style.setProperty('--flash-timeout', `${timeout}ms`);
    window.setTimeout(dismiss, timeout);
  }
});
