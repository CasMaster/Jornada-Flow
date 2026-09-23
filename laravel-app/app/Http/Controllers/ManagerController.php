<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Models\WorkRequest;
use App\Services\WorkRequestService;
use App\Support\ReportingCycle;
use App\Support\SpreadsheetSafeText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerController extends Controller
{
    public function __construct(private WorkRequestService $workRequests) {}

    private function allowedTeams(User $user): array
    {
        if ($user->role === 'super_admin') {
            return Team::pluck('name')->all();
        }

        $delegatedManagerIds = $user->receivedDelegations()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->pluck('manager_id');

        return Team::whereHas('managers', fn (Builder $query) => $query->whereKey([$user->id, ...$delegatedManagerIds]))
            ->pluck('name')->all();
    }

    private function selectedTeams(Request $request, array $allowedTeams): array
    {
        $requested = $request->has('teams')
            ? (array) $request->input('teams', [])
            : ($request->filled('team') ? [$request->string('team')->toString()] : []);

        return array_values(array_intersect(array_filter($requested, 'is_string'), $allowedTeams));
    }

    private function query(Request $request, bool $applyStatus = true): Builder
    {
        [$start,$end] = ReportingCycle::bounds($request->string('cycle')->toString() ?: null);
        $teams = $this->allowedTeams($request->user());
        $query = WorkRequest::with('user')->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $end->toDateString())->whereHas('user', fn (Builder $q) => $q->whereIn('team', $teams));
        $selectedTeams = $this->selectedTeams($request, $teams);
        if ($request->has('teams') || $request->filled('team')) {
            $query->whereHas('user', fn (Builder $q) => $q->whereIn('team', $selectedTeams));
        }
        if ($applyStatus && $request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('work_mode')) {
            $query->where('work_mode', $request->string('work_mode'));
        }
        if ($request->filled('q')) {
            $term = '%'.strtolower(trim($request->string('q')->toString())).'%';
            $query->whereHas('user', fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]));
        }
        $emails = array_filter((array) $request->input('employees', []));
        if ($emails) {
            $query->whereHas('user', fn (Builder $q) => $q->whereIn('email', $emails));
        }

        return $query;
    }

    public function index(Request $request): View
    {
        [$start,$end] = ReportingCycle::bounds($request->string('cycle')->toString() ?: null);
        $allowed = $this->allowedTeams($request->user());
        $selectedTeams = $this->selectedTeams($request, $allowed);
        $base = $this->query($request);
        $metrics = (clone $base)->selectRaw('COUNT(*) total')->selectRaw("SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) pending")->selectRaw('COUNT(DISTINCT user_id) collaborators')->first();
        $priorityRequests = (clone $base)->where('status', 'pending')->orderBy('created_at')->limit(5)->get();
        $statusSummary = (clone $base)->select('status')->selectRaw('COUNT(*) total')->groupBy('status')->pluck('total', 'status');
        $teamSummary = (clone $base)->join('users', 'users.id', '=', 'work_requests.user_id')->select('users.team')->selectRaw('COUNT(*) total')->selectRaw("SUM(CASE WHEN work_requests.status='pending' THEN 1 ELSE 0 END) pending")->groupBy('users.team')->orderByDesc('total')->limit(8)->get();
        $employeeTeams = ($request->has('teams') || $request->filled('team')) ? $selectedTeams : $allowed;

        $sorts = ['date_asc' => ['work_date', 'asc'], 'date_desc' => ['work_date', 'desc'], 'created_desc' => ['created_at', 'desc'], 'status' => ['status', 'asc']];
        [$sortColumn, $sortDirection] = $sorts[$request->string('sort')->toString()] ?? $sorts['date_asc'];
        $perPage = in_array($request->integer('per_page'), [25, 50, 100], true) ? $request->integer('per_page') : 25;

        return view('manager.dashboard', [
            'records' => $base->orderBy($sortColumn, $sortDirection)->paginate($perPage)->withQueryString(),
            'metrics' => $metrics,
            'priorityRequests' => $priorityRequests, 'statusSummary' => $statusSummary, 'teamSummary' => $teamSummary,
            'teams' => Team::whereIn('name', $allowed)->orderBy('name')->get(),
            'employees' => User::where('active', true)->where('team', '<>', '')->whereIn('team', $employeeTeams)->orderBy('name')->get(),
            'cycles' => ReportingCycle::options(), 'start' => $start, 'end' => $end,
        ]);
    }

    public function review(Request $request, WorkRequest $workRequest): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:1000']]);
        $this->authorize('review', $workRequest);
        $this->workRequests->review($request->user(), $workRequest, $data['decision'], $data['review_note'] ?? null);

        return back()->with('success', 'Solicitação analisada.');
    }

    public function reviewBatch(Request $request): RedirectResponse
    {
        $data = $request->validate(['requests' => ['required', 'array', 'min:1', 'max:100'], 'requests.*' => ['integer', 'exists:work_requests,id'], 'decision' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:1000']]);
        $records = WorkRequest::with('user')->whereIn('id', $data['requests'])->get();
        foreach ($records as $record) {
            $this->authorize('review', $record);
        }
        DB::transaction(fn () => $records->each(fn (WorkRequest $record) => $this->workRequests->review($request->user(), $record, $data['decision'], $data['review_note'] ?? null)));

        return back()->with('success', $records->count().' solicitação(ões) analisada(s).');
    }

    public function export(Request $request): StreamedResponse
    {
        [$start,$end] = ReportingCycle::bounds($request->string('cycle')->toString() ?: null);
        $format = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';
        $exportStatus = in_array($request->string('export_status')->toString(), ['pending', 'approved', 'rejected', 'all'], true) ? $request->string('export_status')->toString() : 'approved';
        $exportRecords = $this->query($request, false)->with('reviewer')->when($exportStatus !== 'all', fn (Builder $query) => $query->where('status', $exportStatus))->orderBy('work_date')->get();
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($exportRecords) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Colaborador', 'E-mail', 'Equipe', 'Data', 'Modalidade', 'Status', 'Analisado por', 'Analisado em'], ';');
                foreach ($exportRecords as $record) {
                    fputcsv($output, SpreadsheetSafeText::row([$record->user->name, $record->user->email, $record->user->team, $record->work_date->format('d/m/Y'), $record->isOnsite() ? 'Presencial' : 'Home office', ['pending' => 'Pendente', 'approved' => 'Aprovada', 'rejected' => 'Recusada'][$record->status], $record->reviewer?->name ?? '', $record->reviewed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '']), ';');
                }
                fclose($output);
            }, 'mixhome-'.$start->format('Y-m-d').'-a-'.$end->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $allowed = $this->allowedTeams($request->user());
        $people = User::where('active', true)->where('team', '<>', '')->whereIn('team', $allowed);
        if ($request->has('teams') || $request->filled('team')) {
            $people->whereIn('team', $this->selectedTeams($request, $allowed));
        } if ($emails = array_filter((array) $request->input('employees', []))) {
            $people->whereIn('email', $emails);
        } $people = $people->orderBy('name')->get();
        $map = [];
        $statusPriority = ['rejected' => 1, 'pending' => 2, 'approved' => 3];
        foreach ($exportRecords as $record) {
            $email = strtolower($record->user->email);
            $date = $record->work_date->format('Y-m-d');
            $current = $map[$email][$date] ?? null;
            if (! $current || $statusPriority[$record->status] >= $statusPriority[$current['status']]) {
                $map[$email][$date] = ['status' => $record->status, 'work_mode' => $record->work_mode];
            }
        }

        return response()->streamDownload(function () use ($start, $end, $people, $map) {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Home Office')->setShowGridlines(false);
            $dates = [];
            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                $dates[] = $date;
            }
            $last = Coordinate::stringFromColumnIndex(count($dates) + 1);
            $sheet->mergeCells("A1:{$last}1")->setCellValue('A1', 'CONTROLE DE JORNADA HÍBRIDA — '.$start->format('d/m/Y').' A '.$end->format('d/m/Y'));
            $sheet->setCellValue('A3', 'Colaborador');
            foreach ($dates as $i => $date) {
                $column = Coordinate::stringFromColumnIndex($i + 2);
                $sheet->setCellValue($column.'3', Date::PHPToExcel($date));
                $sheet->getStyle($column.'3')->getNumberFormat()->setFormatCode('dd/mm');
                if ($date->isWeekend()) {
                    $sheet->getStyle($column.'3:'.$column.max(4, $people->count() + 3))->getFill()->setFillType('solid')->getStartColor()->setRGB('F2F2F2');
                }
            }
            foreach ($people as $i => $person) {
                $row = $i + 4;
                $sheet->setCellValueExplicit('A'.$row, $person->name, DataType::TYPE_STRING);
                foreach ($dates as $d => $date) {
                    if ($entry = $map[strtolower($person->email)][$date->format('Y-m-d')] ?? null) {
                        $column = Coordinate::stringFromColumnIndex($d + 2);
                        $cell = $column.$row;
                        $status = $entry['status'];
                        $styles = ['approved' => ['HOME', '375623', 'E2F0D9'], 'pending' => ['PENDENTE', '7F6000', 'FFF2CC'], 'rejected' => ['RECUSADA', '9C0006', 'F4CCCC']];
                        [$label, $font, $fill] = $entry['work_mode'] === 'onsite' && $status === 'approved' ? ['PRESENCIAL', '174F8A', 'DCECFF'] : $styles[$status];
                        $sheet->setCellValue($cell, $label);
                        $sheet->getStyle($cell)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $font]], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $fill]], 'alignment' => ['horizontal' => 'center']]);
                    }
                }
            }
            $lastRow = max(4, $people->count() + 3);
            $note = $lastRow + 2;
            $sheet->mergeCells("A{$note}:{$last}{$note}")->setCellValue("A{$note}", 'Ciclo selecionado: '.$start->format('d/m/Y').' a '.$end->format('d/m/Y').'. Exportado em '.now()->format('d/m/Y H:i').'.');
            $sheet->getStyle("A1:{$last}1")->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '17283E']], 'alignment' => ['horizontal' => 'center']]);
            $sheet->getStyle("A3:{$last}3")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DDEBDD']], 'alignment' => ['horizontal' => 'center']]);
            $sheet->getColumnDimension('A')->setWidth(28);
            for ($i = 2; $i <= count($dates) + 1; $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(11);
            }$sheet->freezePane('B4');
            $sheet->setAutoFilter("A3:{$last}3");
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            (new Xlsx($book))->save('php://output');
        }, 'jornada-hibrida-'.$start->format('Y-m-d').'-a-'.$end->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
