<?php

namespace App\Http\Controllers;

use App\Models\VacationEntitlement;
use App\Services\VacationEntitlementService;
use App\Services\VacationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VacationController extends Controller
{
    public function __construct(
        private VacationRequestService $vacations,
        private VacationEntitlementService $vacationEntitlements,
    ) {}

    public function index(Request $request): View
    {
        $entitlements = $request->user()->vacationEntitlements()->with('requests')->orderByDesc('acquisition_ends_on')->get();

        return view('vacations.index', [
            'vacations' => $request->user()->vacationRequests()->with(['reviewer', 'entitlement'])->latest('starts_on')->paginate(15),
            'entitlements' => $entitlements,
            'usableEntitlements' => $entitlements->filter(fn (VacationEntitlement $entitlement) => $entitlement->isUsable() && $entitlement->availableDays() > 0)->values(),
            'requestableEntitlements' => $entitlements->filter(fn (VacationEntitlement $entitlement) => $entitlement->isRequestable() && $entitlement->availableDays() > 0)->values(),
            'accrualPeriod' => $this->vacationEntitlements->currentAccrualPeriod($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['vacation_entitlement_id' => ['required', 'integer'], 'starts_on' => ['required', 'date', 'after_or_equal:today'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on']]);
        $entitlement = VacationEntitlement::findOrFail($data['vacation_entitlement_id']);
        $vacation = $this->vacations->create($request->user(), $entitlement, $data['starts_on'], $data['ends_on']);

        return back()->with('success', VacationRequestService::days($data['starts_on'], $data['ends_on']).' dia(s) de férias enviados para aprovação.');
    }
}
