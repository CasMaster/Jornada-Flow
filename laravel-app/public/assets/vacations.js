document.addEventListener('DOMContentLoaded', () => {
  const startsOn = document.querySelector('#vacation-starts-on')
  const endsOn = document.querySelector('#vacation-ends-on')

  if (!startsOn || !endsOn) return

  const alignPeriod = () => {
    endsOn.min = startsOn.value || startsOn.min
    if (endsOn.value && startsOn.value && endsOn.value < startsOn.value) {
      endsOn.value = startsOn.value
    }
  }

  startsOn.addEventListener('change', alignPeriod)
  alignPeriod()
})
