document.addEventListener('DOMContentLoaded', () => {
  const startsOn = document.querySelector('#vacation-starts-on')
  const endsOn = document.querySelector('#vacation-ends-on')
  const entitlement = document.querySelector('#vacation-entitlement')
  const availability = document.querySelector('#vacation-availability')

  if (!startsOn || !endsOn) return

  const selectedBalance = () => entitlement?.tagName === 'SELECT'
    ? entitlement.selectedOptions[0]
    : entitlement

  const formatDate = (value) => value
    ? new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`))
    : ''

  const applyBalanceWindow = () => {
    const balance = selectedBalance()
    const availableFrom = balance?.dataset.availableFrom || startsOn.dataset.today
    const expiresOn = balance?.dataset.expiresOn || ''
    const minimum = availableFrom > startsOn.dataset.today ? availableFrom : startsOn.dataset.today

    startsOn.min = minimum
    startsOn.max = expiresOn
    endsOn.max = expiresOn

    if (startsOn.value && startsOn.value < minimum) startsOn.value = minimum
    if (expiresOn && startsOn.value > expiresOn) startsOn.value = ''
    if (expiresOn && endsOn.value > expiresOn) endsOn.value = ''

    if (!availability) return
    availability.textContent = balance?.value
      ? (expiresOn
          ? `Este saldo permite férias de ${formatDate(minimum)} até ${formatDate(expiresOn)}.`
          : `Este saldo permite férias a partir de ${formatDate(minimum)}.`)
      : (entitlement ? 'Escolha um saldo para ver o período permitido.' : 'As datas serão liberadas quando houver saldo disponível.')
  }

  const alignPeriod = () => {
    endsOn.min = startsOn.value || startsOn.min
    if (endsOn.value && startsOn.value && endsOn.value < startsOn.value) {
      endsOn.value = startsOn.value
    }
  }

  startsOn.addEventListener('change', alignPeriod)
  entitlement?.addEventListener('change', () => {
    applyBalanceWindow()
    alignPeriod()
  })
  applyBalanceWindow()
  alignPeriod()
})
