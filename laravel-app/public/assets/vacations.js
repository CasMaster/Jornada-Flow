document.addEventListener('DOMContentLoaded', () => {
  const startsOn = document.querySelector('#vacation-starts-on')
  const endsOn = document.querySelector('#vacation-ends-on')
  const startTrigger = document.querySelector('#vacation-start-trigger')
  const endTrigger = document.querySelector('#vacation-end-trigger')
  const entitlement = document.querySelector('#vacation-entitlement')
  const availability = document.querySelector('#vacation-availability')
  const calendar = document.querySelector('#vacation-calendar')

  if (!startsOn || !endsOn || !startTrigger || !endTrigger || !calendar) return

  const title = calendar.querySelector('[data-calendar-title]')
  const days = calendar.querySelector('[data-calendar-days]')
  const instruction = calendar.querySelector('[data-calendar-instruction]')
  const previousMonth = calendar.querySelector('[data-calendar-prev]')
  const nextMonth = calendar.querySelector('[data-calendar-next]')
  const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' })
  const dateFormatter = new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' })
  let minimum = startsOn.dataset.today
  let maximum = ''
  let selecting = 'start'
  let viewedMonth = null

  const fromIso = (value) => new Date(`${value}T00:00:00Z`)
  const toIso = (date) => date.toISOString().slice(0, 10)
  const formatDate = (value) => value ? dateFormatter.format(fromIso(value)) : ''
  const selectedBalance = () => entitlement?.tagName === 'SELECT' ? entitlement.selectedOptions[0] : entitlement
  const inWindow = (value) => value >= minimum && (!maximum || value <= maximum)

  const updateTriggers = () => {
    startTrigger.querySelector('span').textContent = startsOn.value ? formatDate(startsOn.value) : 'Escolher data'
    endTrigger.querySelector('span').textContent = endsOn.value ? formatDate(endsOn.value) : 'Escolher data'
    startTrigger.classList.toggle('has-value', Boolean(startsOn.value))
    endTrigger.classList.toggle('has-value', Boolean(endsOn.value))
  }

  const renderCalendar = () => {
    if (!viewedMonth) viewedMonth = fromIso(startsOn.value || minimum)
    const year = viewedMonth.getUTCFullYear()
    const month = viewedMonth.getUTCMonth()
    const first = new Date(Date.UTC(year, month, 1))
    const gridStart = new Date(first)
    gridStart.setUTCDate(1 - first.getUTCDay())
    title.textContent = monthFormatter.format(first)
    const previousMonthEnd = toIso(new Date(Date.UTC(year, month, 0)))
    const nextMonthStart = toIso(new Date(Date.UTC(year, month + 1, 1)))
    previousMonth.disabled = previousMonthEnd < minimum
    nextMonth.disabled = Boolean(maximum && nextMonthStart > maximum)
    days.replaceChildren()

    for (let index = 0; index < 42; index++) {
      const date = new Date(gridStart)
      date.setUTCDate(gridStart.getUTCDate() + index)
      const value = toIso(date)
      const button = document.createElement('button')
      button.type = 'button'
      button.textContent = date.getUTCDate()
      button.dataset.date = value
      button.disabled = !inWindow(value)
      button.classList.toggle('outside', date.getUTCMonth() !== month)
      button.classList.toggle('range-start', value === startsOn.value)
      button.classList.toggle('range-end', value === endsOn.value)
      button.classList.toggle('in-range', Boolean(startsOn.value && endsOn.value && value > startsOn.value && value < endsOn.value))
      button.setAttribute('aria-label', formatDate(value))
      days.append(button)
    }

    instruction.textContent = selecting === 'start'
      ? 'Escolha o primeiro dia das férias.'
      : 'Agora escolha o último dia das férias.'
  }

  const openCalendar = (mode) => {
    selecting = mode
    viewedMonth = fromIso((mode === 'end' ? endsOn.value : startsOn.value) || startsOn.value || minimum)
    calendar.hidden = false
    renderCalendar()
    calendar.querySelector('[data-date]:not(:disabled)')?.focus()
  }

  const closeCalendar = () => {
    calendar.hidden = true
    updateTriggers()
  }

  const chooseDate = (value) => {
    if (selecting === 'start') {
      startsOn.value = value
      if (endsOn.value && endsOn.value < value) endsOn.value = ''
      selecting = 'end'
      renderCalendar()
      return
    }

    if (!startsOn.value || value < startsOn.value) {
      startsOn.value = value
      endsOn.value = ''
      selecting = 'end'
      renderCalendar()
      return
    }

    endsOn.value = value
    closeCalendar()
  }

  const applyBalanceWindow = () => {
    const balance = selectedBalance()
    const availableFrom = balance?.dataset.availableFrom || startsOn.dataset.today
    maximum = balance?.dataset.expiresOn || ''
    minimum = availableFrom > startsOn.dataset.today ? availableFrom : startsOn.dataset.today

    if (startsOn.value && !inWindow(startsOn.value)) startsOn.value = ''
    if (endsOn.value && (!inWindow(endsOn.value) || (startsOn.value && endsOn.value < startsOn.value))) endsOn.value = ''

    if (availability) {
      availability.textContent = balance?.value
        ? (maximum
            ? `Escolha férias entre ${formatDate(minimum)} e ${formatDate(maximum)}.`
            : `Escolha férias a partir de ${formatDate(minimum)}.`)
        : (entitlement ? 'Escolha um período para liberar o calendário.' : 'O calendário será liberado quando houver um período calculado.')
    }

    const enabled = Boolean(balance?.value)
    startTrigger.disabled = !enabled
    endTrigger.disabled = !enabled
    updateTriggers()
    if (!calendar.hidden) renderCalendar()
  }

  startTrigger.addEventListener('click', () => openCalendar('start'))
  endTrigger.addEventListener('click', () => openCalendar('end'))
  entitlement?.addEventListener('change', applyBalanceWindow)
  days.addEventListener('click', (event) => {
    const button = event.target.closest('[data-date]')
    if (button && !button.disabled) chooseDate(button.dataset.date)
  })
  previousMonth.addEventListener('click', () => {
    viewedMonth.setUTCMonth(viewedMonth.getUTCMonth() - 1)
    renderCalendar()
  })
  nextMonth.addEventListener('click', () => {
    viewedMonth.setUTCMonth(viewedMonth.getUTCMonth() + 1)
    renderCalendar()
  })
  calendar.querySelector('[data-calendar-clear]').addEventListener('click', () => {
    startsOn.value = ''
    endsOn.value = ''
    selecting = 'start'
    updateTriggers()
    renderCalendar()
  })
  calendar.querySelector('[data-calendar-close]').addEventListener('click', closeCalendar)
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !calendar.hidden) closeCalendar()
  })
  document.addEventListener('click', (event) => {
    if (!calendar.hidden && !calendar.contains(event.target) && !startTrigger.contains(event.target) && !endTrigger.contains(event.target)) closeCalendar()
  })

  applyBalanceWindow()
})
