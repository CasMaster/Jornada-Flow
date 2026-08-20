const $ = (s) => document.querySelector(s);
const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const selected = new Set();
const holidays = new Map((window.HIBRIDO_HOLIDAYS || []).map(item => [item.date, item]));
let cursor = new Date();

function iso(day) { return `${cursor.getFullYear()}-${String(cursor.getMonth()+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`; }
function renderCalendar() {
  const year = cursor.getFullYear(), month = cursor.getMonth();
  $('#monthLabel').textContent = `${months[month]} ${year}`;
  const first = (new Date(year, month, 1).getDay() + 6) % 7;
  const days = new Date(year, month + 1, 0).getDate();
  $('#calendar').innerHTML = `${'<span></span>'.repeat(first)}${Array.from({length:days},(_,i)=>{const date=iso(i+1),holiday=holidays.get(date);return `<button data-date="${date}" title="${holiday?.name || ''}" ${holiday?.blocked?'disabled':''} class="${selected.has(date)?'chosen':''} ${holiday?'corporate-date':''}">${i+1}</button>`}).join('')}`;
  $('#calendar').querySelectorAll('button:not(:disabled)').forEach(button => button.addEventListener('click', () => { selected.has(button.dataset.date) ? selected.delete(button.dataset.date) : selected.add(button.dataset.date); renderCalendar(); }));
  $('#selectedCount').textContent = selected.size;
}
$('#prevMonth').onclick = () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth()-1, 1); renderCalendar(); };
$('#nextMonth').onclick = () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth()+1, 1); renderCalendar(); };

$('#register').onclick = async () => {
  if (!selected.size) { $('#notice').textContent = 'Selecione ao menos um dia antes de enviar.'; return; }
  $('#register').disabled = true;
  try {
    const response = await fetch(window.HIBRIDO_REQUEST_URL, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.HIBRIDO_CSRF}, body:JSON.stringify({ dates:[...selected] }) });
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
