const $ = (s) => document.querySelector(s);
const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const selected = new Set();
const holidays = new Map((window.HIBRIDO_HOLIDAYS || []).map(item => [item.date, item]));
const requests = new Map();
(window.HIBRIDO_REQUESTS || []).forEach(item => {
  const candidate = { status:item.status, workMode:item.work_mode || 'home_office' };
  const current = requests.get(item.date);
  const priority = { approved:3, pending:2, rejected:1 };
  if (!current || priority[candidate.status] >= priority[current.status]) requests.set(item.date, candidate);
});
let cursor = new Date();
let workMode = 'home_office';

function iso(day) { return `${cursor.getFullYear()}-${String(cursor.getMonth()+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`; }
function renderCalendar() {
  const year = cursor.getFullYear(), month = cursor.getMonth();
  $('#monthLabel').textContent = `${months[month]} ${year}`;
  const first = (new Date(year, month, 1).getDay() + 6) % 7;
  const days = new Date(year, month + 1, 0).getDate();
  const calendar = $('#calendar');
  calendar.replaceChildren();
  for (let i = 0; i < first; i++) calendar.append(document.createElement('span'));
  for (let day = 1; day <= days; day++) {
    const date = iso(day), holiday = holidays.get(date), request = requests.get(date), requestStatus = request?.status, button = document.createElement('button');
    button.dataset.date = date;
    button.title = holiday?.name || '';
    button.disabled = Boolean(holiday?.blocked || requestStatus);
    if (holiday?.blocked) button.classList.add('blocked-date');
    if (requestStatus) button.classList.add('registered-date', `registered-${requestStatus}`, `registered-mode-${request.workMode}`);
    if (selected.has(date)) button.classList.add('chosen');
    if (holiday) {
      const type = holiday.source === 'manual' ? 'manual' : (holiday.scope || 'national');
      button.classList.add('corporate-date', `holiday-${type}`);
    }
    if (requestStatus) button.setAttribute('aria-label', `${day}, solicitação ${request.workMode === 'onsite' ? 'presencial' : 'de home office'} ${requestStatus === 'pending' ? 'pendente' : requestStatus === 'approved' ? 'aprovada' : 'recusada'}`);
    const number = document.createElement('strong');
    number.className = 'calendar-day-number';
    number.textContent = day;
    button.append(number);
    if (holiday) {
      const marker = document.createElement('span');
      marker.className = 'calendar-holiday-marker';
      marker.setAttribute('aria-hidden', 'true');
      button.append(marker);
      button.setAttribute('aria-label', `${day}, ${holiday.name}${holiday.blocked ? ', indisponível' : ', data informativa'}`);
    }
    calendar.append(button);
  }
  calendar.querySelectorAll('button:not(:disabled)').forEach(button => button.addEventListener('click', () => { selected.has(button.dataset.date) ? selected.delete(button.dataset.date) : selected.add(button.dataset.date); renderCalendar(); }));
  $('#selectedCount').textContent = selected.size;
}
$('#prevMonth').onclick = () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth()-1, 1); renderCalendar(); };
$('#nextMonth').onclick = () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth()+1, 1); renderCalendar(); };
document.querySelectorAll('[data-work-mode]').forEach(button => button.addEventListener('click', () => {
  workMode = button.dataset.workMode;
  selected.clear();
  document.querySelectorAll('[data-work-mode]').forEach(option => {
    const active = option === button;
    option.classList.toggle('active', active);
    option.setAttribute('aria-pressed', String(active));
  });
  $('#notice').textContent = workMode === 'onsite' ? 'Selecione os dias presenciais para enviar ao gestor.' : 'Revise as datas antes de enviar.';
  $('#register').innerHTML = workMode === 'onsite' ? 'Solicitar presencial <span>→</span>' : 'Enviar solicitação <span>→</span>';
  renderCalendar();
}));

$('#register').onclick = async () => {
  if (!selected.size) { $('#notice').textContent = 'Selecione ao menos um dia antes de enviar.'; return; }
  $('#register').disabled = true;
  try {
    const response = await fetch(window.HIBRIDO_REQUEST_URL, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.HIBRIDO_CSRF}, body:JSON.stringify({ dates:[...selected], work_mode:workMode }) });
    const data = await response.json().catch(() => ({}));
    const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
    $('#notice').textContent = response.ok ? `${data.saved} solicitação(ões) enviada(s) ao gestor.` : (validationMessage || data.message || `Não foi possível enviar (erro ${response.status}). Atualize a página e tente novamente.`);
    if (response.ok) { selected.clear(); renderCalendar(); setTimeout(() => location.reload(), 700); }
  } catch (_) {
    $('#notice').textContent = 'Falha de conexão. Atualize a página para renovar sua sessão e tente novamente.';
  } finally {
    $('#register').disabled = false;
  }
};

renderCalendar();
