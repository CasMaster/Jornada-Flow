(() => {
  if (!('serviceWorker' in navigator)) return;

  const brandName = document.body.dataset.brandName || 'Aplicativo';

  const manifest = document.querySelector('link[rel="manifest"]');
  const serviceWorkerUrl = manifest?.dataset.serviceWorker;
  if (serviceWorkerUrl) window.addEventListener('load', () => navigator.serviceWorker.register(serviceWorkerUrl).catch(() => {}));

  const installButton = document.querySelector('[data-pwa-install]');
  if (!installButton || window.matchMedia('(display-mode: standalone)').matches) return;

  const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  if (isIOS) {
    installButton.textContent = 'Como instalar';
    installButton.hidden = false;
    installButton.addEventListener('click', showIOSInstructions);
    return;
  }

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

  function showIOSInstructions() {
    let dialog = document.querySelector('[data-ios-install-dialog]');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.className = 'pwa-install-dialog';
      dialog.dataset.iosInstallDialog = '';
      dialog.setAttribute('aria-labelledby', 'pwa-install-title');

      const title = document.createElement('h2');
      title.id = 'pwa-install-title';
      title.textContent = `Instalar o ${brandName} no iPhone`;
      const instructions = document.createElement('p');
      instructions.textContent = 'Abra esta página no Safari, toque no botão Compartilhar e escolha Adicionar à Tela de Início.';
      const note = document.createElement('small');
      note.textContent = 'Se a opção não estiver visível, role a lista de ações ou toque em Editar Ações.';
      const close = document.createElement('button');
      close.type = 'button';
      close.textContent = 'Entendi';
      close.addEventListener('click', () => dialog.close());

      dialog.append(title, instructions, note, close);
      document.body.append(dialog);
    }
    dialog.showModal();
  }
})();
