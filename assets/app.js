const $ = (s) => document.querySelector(s);
const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const selected = new Set();
let cursor = new Date();

function iso(day) { return `${cursor.getFullYear()}-${String(cursor.getMonth()+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`; }
function renderCalendar() {
  const year = cursor.getFullYear(), month = cursor.getMonth();
  $('#monthLabel').textContent = `${months[month]} ${year}`;
  const first = (new Date(year, month, 1).getDay() + 6) % 7;
  const days = new Date(year, month + 1, 0).getDate();
  $('#calendar').innerHTML = `${'<span></span>'.repeat(first)}${Array.from({length:days},(_,i)=>`<button data-date="${iso(i+1)}" class="${selected.has(iso(i+1))?'chosen':''}">${i+1}</button>`).join('')}`;
  $('#calendar').querySelectorAll('button').forEach(button => button.addEventListener('click', () => { selected.has(button.dataset.date) ? selected.delete(button.dataset.date) : selected.add(button.dataset.date); renderCalendar(); }));
  $('#selectedCount').textContent = selected.size;
}
$('#prevMonth').onclick = () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth()-1, 1); renderCalendar(); };
$('#nextMonth').onclick = () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth()+1, 1); renderCalendar(); };

$('#register').onclick = async () => {
  const response = await fetch('?api=register', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ csrf:document.body.dataset.csrf, dates:[...selected] }) });
  const data = await response.json();
  $('#notice').textContent = response.ok ? `${data.saved} dia(s) registrado(s) com sucesso.` : data.error;
  if (response.ok) { selected.clear(); renderCalendar(); setTimeout(() => location.reload(), 700); }
};

renderCalendar();
