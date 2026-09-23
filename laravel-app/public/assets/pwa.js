(() => {
  if (!('serviceWorker' in navigator)) return;

  const manifest = document.querySelector('link[rel="manifest"]');
  const serviceWorkerUrl = manifest?.dataset.serviceWorker;
  if (serviceWorkerUrl) window.addEventListener('load', () => navigator.serviceWorker.register(serviceWorkerUrl).catch(() => {}));

  const installButton = document.querySelector('[data-pwa-install]');
  if (!installButton || window.matchMedia('(display-mode: standalone)').matches) return;

  let installPrompt = null;
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    installPrompt = event;
    installButton.hidden = false;
  });

  installButton.addEventListener('click', async () => {
    if (!installPrompt) return;
    await installPrompt.prompt();
    installPrompt = null;
    installButton.hidden = true;
  });

  window.addEventListener('appinstalled', () => {
    installPrompt = null;
    installButton.hidden = true;
  });
})();
