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
    [...optionsArea.children].forEach((button, index) => button.classList.toggle('selected', select.options[index].selected));
  };

  [...select.options].forEach((option) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'click-multi-option';
    button.textContent = option.text;
    button.addEventListener('click', () => {
      option.selected = !option.selected;
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
