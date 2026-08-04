document.querySelectorAll('select[multiple].click-multi').forEach((select) => {
  select.classList.add('is-enhanced');

  const picker = document.createElement('div');
  picker.className = 'click-multi-picker';
  const selectedArea = document.createElement('div');
  selectedArea.className = 'click-multi-selected';
  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.className = 'click-multi-trigger';
  trigger.setAttribute('aria-expanded', 'false');
  const optionsArea = document.createElement('div');
  optionsArea.className = 'click-multi-options';
  optionsArea.setAttribute('role', 'listbox');
  optionsArea.setAttribute('aria-multiselectable', 'true');
  optionsArea.hidden = true;

  const render = () => {
    selectedArea.replaceChildren();
    const selected = [...select.selectedOptions];
    selected.forEach((option) => {
      const chip = document.createElement('span');
      chip.className = 'click-multi-chip';
      chip.append(document.createTextNode(option.text));
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'click-multi-remove';
      remove.textContent = '×';
      remove.setAttribute('aria-label', `Remover ${option.text}`);
      remove.addEventListener('click', () => {
        option.selected = false;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        render();
      });
      chip.append(remove);
      selectedArea.append(chip);
    });
    trigger.textContent = selected.length ? 'Adicionar outra opção' : (select.dataset.placeholder || 'Selecionar opções');
    [...optionsArea.children].forEach((button, index) => {
      const isSelected = select.options[index].selected;
      button.classList.toggle('selected', isSelected);
      button.setAttribute('aria-selected', String(isSelected));
    });
  };

  [...select.options].forEach((option) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'click-multi-option';
    button.setAttribute('role', 'option');
    button.textContent = option.text;
    button.addEventListener('click', () => {
      option.selected = !option.selected;
      button.setAttribute('aria-selected', String(option.selected));
      select.dispatchEvent(new Event('change', { bubbles: true }));
      render();
    });
    optionsArea.append(button);
  });

  trigger.addEventListener('click', () => {
    const willOpen = optionsArea.hidden;
    document.querySelectorAll('.click-multi-options').forEach((area) => { area.hidden = true; });
    document.querySelectorAll('.click-multi-trigger').forEach((button) => button.setAttribute('aria-expanded', 'false'));
    optionsArea.hidden = !willOpen;
    trigger.setAttribute('aria-expanded', String(willOpen));
  });

  picker.append(selectedArea, trigger, optionsArea);
  select.insertAdjacentElement('afterend', picker);
  render();
});

const positionUserMenu = (details) => {
  const form = details.querySelector('.edit-user-form');
  const summary = details.querySelector('summary');
  if (!form || !summary || !details.open) return;
  const rect = summary.getBoundingClientRect();
  const menuWidth = Math.min(620, window.innerWidth - 24);
  const right = Math.max(12, window.innerWidth - rect.right);
  let top = rect.bottom + 8;
  const estimatedHeight = Math.min(form.scrollHeight || 420, window.innerHeight - 24);
  if (top + estimatedHeight > window.innerHeight - 12) top = Math.max(12, rect.top - estimatedHeight - 8);
  form.style.setProperty('--menu-top', `${top}px`);
  form.style.setProperty('--menu-right', `${Math.min(right, window.innerWidth - menuWidth - 12)}px`);
};

document.querySelectorAll('details.user-menu').forEach((details) => {
  details.addEventListener('toggle', () => {
    if (!details.open) return;
    document.querySelectorAll('details.user-menu[open]').forEach((other) => { if (other !== details) other.open = false; });
    positionUserMenu(details);
  });
});

window.addEventListener('resize', () => document.querySelectorAll('details.user-menu[open]').forEach(positionUserMenu));

document.addEventListener('click', (event) => {
  if (event.target.closest('.click-multi-picker')) return;
  document.querySelectorAll('.click-multi-options').forEach((area) => { area.hidden = true; });
  document.querySelectorAll('.click-multi-trigger').forEach((button) => button.setAttribute('aria-expanded', 'false'));
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  document.querySelectorAll('.click-multi-options').forEach((area) => { area.hidden = true; });
  document.querySelectorAll('.click-multi-trigger').forEach((button) => button.setAttribute('aria-expanded', 'false'));
});
