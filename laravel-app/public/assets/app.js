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
  const response = await fetch(window.HIBRIDO_REQUEST_URL, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.HIBRIDO_CSRF}, body:JSON.stringify({ dates:[...selected] }) });
  const data = await response.json();
  $('#notice').textContent = response.ok ? `${data.saved} solicitação(ões) enviada(s) ao gestor.` : (data.message || 'Não foi possível enviar as solicitações.');
  if (response.ok) { selected.clear(); renderCalendar(); setTimeout(() => location.reload(), 700); }
};

renderCalendar();
