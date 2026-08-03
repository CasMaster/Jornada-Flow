<?php
namespace App\Http\Controllers;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkRequest;
use App\Support\ReportingCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class ManagerController extends Controller
{
    private function allowedTeams(User $user): array { return $user->role === 'super_admin' ? Team::pluck('name')->all() : $user->managedTeams()->pluck('name')->all(); }
    private function query(Request $request, bool $applyStatus = true): Builder
    {
        [$start,$end] = ReportingCycle::bounds($request->string('cycle')->toString() ?: null); $teams = $this->allowedTeams($request->user());
        $query = WorkRequest::with('user')->whereDate('work_date','>=',$start->toDateString())->whereDate('work_date','<=',$end->toDateString())->whereHas('user', fn(Builder $q)=>$q->whereIn('team',$teams));
        if ($request->filled('team')) $query->whereHas('user', fn(Builder $q)=>$q->where('team',$request->string('team')));
        if ($applyStatus && $request->filled('status')) $query->where('status',$request->string('status'));
        $emails = array_filter((array)$request->input('employees',[])); if ($emails) $query->whereHas('user', fn(Builder $q)=>$q->whereIn('email',$emails));
        return $query;
    }
    public function index(Request $request): View
    {
        [$start,$end]=ReportingCycle::bounds($request->string('cycle')->toString() ?: null); $allowed=$this->allowedTeams($request->user());
        return view('manager.dashboard',['records'=>$this->query($request)->orderBy('work_date')->get(),'teams'=>Team::whereIn('name',$allowed)->orderBy('name')->get(),'employees'=>User::where('role','employee')->whereIn('team',$allowed)->orderBy('name')->get(),'cycles'=>ReportingCycle::options(),'start'=>$start,'end'=>$end,'allTeams'=>Team::orderBy('name')->get(),'users'=>$request->user()->role==='super_admin'?User::with('managedTeams')->orderBy('name')->get():collect()]);
    }
    public function review(Request $request, WorkRequest $workRequest): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approved,rejected']]); abort_unless(in_array($workRequest->user->team,$this->allowedTeams($request->user()),true),403);
        $workRequest->update(['status'=>$data['decision'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]); return back()->with('success','Solicitação analisada.');
    }
    public function export(Request $request)
    {
        [$start,$end]=ReportingCycle::bounds($request->string('cycle')->toString() ?: null);
        $approved=$this->query($request, false)->where('status','approved')->get(); $allowed=$this->allowedTeams($request->user());
        $people=User::where('role','employee')->where('active',true)->whereIn('team',$allowed);
        if($request->filled('team'))$people->where('team',$request->string('team')); if($emails=array_filter((array)$request->input('employees',[])))$people->whereIn('email',$emails); $people=$people->orderBy('name')->get();
        $map=[]; foreach($approved as $record)$map[strtolower($record->user->email)][$record->work_date->format('Y-m-d')]=true;
        return response()->streamDownload(function()use($start,$end,$people,$map){
            $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet; $sheet=$book->getActiveSheet(); $sheet->setTitle('Home Office')->setShowGridlines(false); $dates=[]; for($date=$start;$date->lte($end);$date=$date->addDay())$dates[]=$date;
            $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($dates)+1); $sheet->mergeCells("A1:{$last}1")->setCellValue('A1','CONTROLE DE HOME OFFICE — '.$start->format('d/m/Y').' A '.$end->format('d/m/Y')); $sheet->setCellValue('A3','Colaborador');
            foreach($dates as $i=>$date){$column=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+2);$sheet->setCellValue($column.'3',\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date));$sheet->getStyle($column.'3')->getNumberFormat()->setFormatCode('dd/mm');if($date->isWeekend())$sheet->getStyle($column.'3:'.$column.max(4,$people->count()+3))->getFill()->setFillType('solid')->getStartColor()->setRGB('F2F2F2');}
            foreach($people as $i=>$person){$row=$i+4;$sheet->setCellValue('A'.$row,$person->name);foreach($dates as $d=>$date)if(!empty($map[strtolower($person->email)][$date->format('Y-m-d')])){$column=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($d+2);$cell=$column.$row;$sheet->setCellValue($cell,'HOME');$sheet->getStyle($cell)->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'9C0006']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'F4CCCC']],'alignment'=>['horizontal'=>'center']]);}}
            $lastRow=max(4,$people->count()+3);$note=$lastRow+2;$sheet->mergeCells("A{$note}:{$last}{$note}")->setCellValue("A{$note}",'Ciclo selecionado: '.$start->format('d/m/Y').' a '.$end->format('d/m/Y').'. Exportado em '.now()->format('d/m/Y H:i').'.');$sheet->getStyle("A1:{$last}1")->applyFromArray(['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'17283E']],'alignment'=>['horizontal'=>'center']]);$sheet->getStyle("A3:{$last}3")->applyFromArray(['font'=>['bold'=>true],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'DDEBDD']],'alignment'=>['horizontal'=>'center']]);$sheet->getColumnDimension('A')->setWidth(28);for($i=2;$i<=count($dates)+1;$i++)$sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth(11);$sheet->freezePane('B4');$sheet->setAutoFilter("A3:{$last}3");$sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
        },'home-office-'.$start->format('Y-m-d').'-a-'.$end->format('Y-m-d').'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
