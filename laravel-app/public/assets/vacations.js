document.addEventListener('DOMContentLoaded', () => {
  const startsOn = document.querySelector('#vacation-starts-on')
  const endsOn = document.querySelector('#vacation-ends-on')
  const startTrigger = document.querySelector('#vacation-start-trigger')
  const endTrigger = document.querySelector('#vacation-end-trigger')
  const entitlement = document.querySelector('#vacation-entitlement')
  const availability = document.querySelector('#vacation-availability')
  const calendar = document.querySelector('#vacation-calendar')
  const cashAllowance = document.querySelector('#vacation-cash-allowance')
  const planSummary = document.querySelector('#vacation-plan-summary')
  const plans = [...document.querySelectorAll('input[name="vacation_plan"]')]

  if (!startsOn || !endsOn || !startTrigger || !endTrigger || !calendar) return

  const title = calendar.querySelector('[data-calendar-title]')
  const days = calendar.querySelector('[data-calendar-days]')
  const instruction = calendar.querySelector('[data-calendar-instruction]')
  const previousMonth = calendar.querySelector('[data-calendar-prev]')
  const nextMonth = calendar.querySelector('[data-calendar-next]')
  const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' })
  const dateFormatter = new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' })
  const blockedStarts = new Set((calendar.dataset.blockedStarts || '').split(',').filter(Boolean))
  let minimum = startsOn.dataset.today
  let maximum = ''
  let selecting = 'start'
  let viewedMonth = null

  const fromIso = (value) => new Date(`${value}T00:00:00Z`)
  const toIso = (date) => date.toISOString().slice(0, 10)
  const formatDate = (value) => value ? dateFormatter.format(fromIso(value)) : ''
  const selectedBalance = () => entitlement?.tagName === 'SELECT' ? entitlement.selectedOptions[0] : entitlement
  const inWindow = (value) => value >= minimum && (!maximum || value <= maximum)
  const invalidCltStart = (date, value) => {
    if (date.getUTCDay() === 0 || date.getUTCDay() === 6 || blockedStarts.has(value)) return true
    for (let offset = 1; offset <= 2; offset++) {
      const next = new Date(date)
      next.setUTCDate(date.getUTCDate() + offset)
      if (next.getUTCDay() === 0 || blockedStarts.has(toIso(next))) return true
    }
    return false
  }
  const selectedPlan = () => plans.find((plan) => plan.checked)?.value || 'custom'
  const addDays = (value, amount) => {
    const date = fromIso(value)
    date.setUTCDate(date.getUTCDate() + amount)
    return toIso(date)
  }

  const updatePlan = () => {
    const balance = selectedBalance()
    const available = Number(balance?.dataset.availableDays || 0)
    const total = Number(balance?.dataset.totalDays || available)
    const allowance = Number(balance?.dataset.allowanceDays || 0)
    const allowanceOption = plans.find((plan) => plan.value === 'allowance')
    if (allowanceOption) allowanceOption.disabled = balance?.dataset.allowanceEligible !== '1'
    document.querySelector('[data-plan-full]').textContent = available ? `${available} dias de descanso` : 'Período integral'
    document.querySelector('[data-plan-allowance]').textContent = allowance ? `${total - allowance} dias + ${allowance} de abono` : 'Descanso + abono'
    if (allowanceOption?.disabled && allowanceOption.checked) plans.find((plan) => plan.value === 'custom').checked = true
    cashAllowance.value = selectedPlan() === 'allowance' ? allowance : 0
    endTrigger.disabled = selectedPlan() !== 'custom' || !balance?.value
    if (startsOn.value && selectedPlan() !== 'custom') {
      const restDays = selectedPlan() === 'allowance' ? total - allowance : available
      endsOn.value = addDays(startsOn.value, restDays - 1)
      if (!inWindow(endsOn.value)) endsOn.value = ''
    }
    const restDays = startsOn.value && endsOn.value ? Math.round((fromIso(endsOn.value) - fromIso(startsOn.value)) / 86400000) + 1 : 0
    planSummary.textContent = restDays
      ? `${restDays} dias de descanso${Number(cashAllowance.value) ? ` + ${cashAllowance.value} dias de abono` : ''}. Total utilizado: ${restDays + Number(cashAllowance.value)} dias.`
      : (allowanceOption?.disabled && balance?.value ? 'O abono não está disponível para este saldo ou o prazo legal terminou.' : '')
    updateTriggers()
  }

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
      const invalidStart = selecting === 'start' && invalidCltStart(date, value)
      button.disabled = !inWindow(value) || invalidStart
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
      if (selectedPlan() !== 'custom') {
        updatePlan()
        closeCalendar()
        return
      }
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
    updatePlan()
    closeCalendar()
  }

  const applyBalanceWindow = () => {
    const balance = selectedBalance()
    const availableFrom = balance?.dataset.availableFrom || startsOn.dataset.cltMinStart
    maximum = balance?.dataset.expiresOn || ''
    minimum = availableFrom > startsOn.dataset.cltMinStart ? availableFrom : startsOn.dataset.cltMinStart

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
    updatePlan()
    updateTriggers()
    if (!calendar.hidden) renderCalendar()
  }

  startTrigger.addEventListener('click', () => openCalendar('start'))
  endTrigger.addEventListener('click', () => openCalendar('end'))
  entitlement?.addEventListener('change', applyBalanceWindow)
  plans.forEach((plan) => plan.addEventListener('change', () => {
    startsOn.value = ''
    endsOn.value = ''
    updatePlan()
  }))
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
    updatePlan()
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
