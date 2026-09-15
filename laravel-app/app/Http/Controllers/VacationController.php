<?php

namespace App\Http\Controllers;

use App\Services\VacationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VacationController extends Controller
{
    public function __construct(private VacationRequestService $vacations) {}

    public function index(Request $request): View
    {
        return view('vacations.index', ['vacations' => $request->user()->vacationRequests()->with('reviewer')->latest('starts_on')->paginate(15)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['starts_on' => ['required', 'date', 'after_or_equal:today'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on']]);
        $vacation = $this->vacations->create($request->user(), $data['starts_on'], $data['ends_on']);
        return back()->with('success', VacationRequestService::days($data['starts_on'], $data['ends_on']).' dia(s) de férias enviados para aprovação.');
    }
}
