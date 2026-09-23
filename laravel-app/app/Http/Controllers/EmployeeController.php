<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\WorkRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(private WorkRequestService $workRequests) {}

    public function index(Request $request): View
    {
        return view('employee.dashboard', ['records' => $request->user()->workRequests()->with('reviewer')->latest('work_date')->paginate(24), 'calendarRequests' => $request->user()->workRequests()->whereDate('work_date', '>=', now()->startOfMonth()->subMonths(2))->get(['work_date', 'work_mode', 'status']), 'holidays' => Holiday::where('date', '>=', now()->startOfMonth()->subMonth())->orderBy('date')->get(), 'notifications' => $request->user()->notifications()->latest()->limit(8)->get()]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['dates' => ['required', 'array', 'min:1'], 'dates.*' => ['date_format:Y-m-d'], 'work_mode' => ['sometimes', 'in:home_office,onsite']]);
        $workMode = $data['work_mode'] ?? 'home_office';
        $added = $this->workRequests->createMany($request->user(), $data['dates'], $workMode);
        if ($request->expectsJson()) {
            return response()->json(['saved' => $added, 'work_mode' => $workMode], 201);
        }

        return back()->with('success', $added ? "$added solicitação(ões) enviada(s)." : 'Esses dias já estavam registrados.');
    }
}
