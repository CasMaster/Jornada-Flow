<?php
namespace App\Http\Controllers;
use App\Models\WorkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        return view('employee.dashboard', ['records' => $request->user()->workRequests()->latest('work_date')->get()]);
    }
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['dates'=>['required','array','min:1'],'dates.*'=>['date_format:Y-m-d']]);
        $added = 0;
        foreach (array_unique($data['dates']) as $date) {
            $record = WorkRequest::firstOrCreate(['user_id'=>$request->user()->id,'work_date'=>$date], ['status'=>'pending']);
            $added += $record->wasRecentlyCreated ? 1 : 0;
        }
        return back()->with('success', $added ? "$added solicitação(ões) enviada(s)." : 'Esses dias já estavam registrados.');
    }
}
