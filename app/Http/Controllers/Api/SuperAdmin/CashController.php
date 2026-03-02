<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cash\OpeningBalanceRequest;
use App\Services\CashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashController extends Controller
{
    public function __construct(private CashService $cashService) {}

    public function openingBalance(OpeningBalanceRequest $request): JsonResponse
    {
        $this->cashService->setOpeningBalance(
            storeId: Auth::user()->getStoreId(),
            amount: $request->amount,
            createdBy: Auth::id(),
        );

        return response()->json(['message' => 'تم تسجيل الرصيد الافتتاحي.']);
    }

    public function dailyReport(Request $request): JsonResponse
    {
        $date   = $request->date ?? today()->toDateString();
        $perPage = $this->resolvePerPage($request, 20);
        $fast = $request->boolean('fast');
        $report = $this->cashService->getDailyReport(
            Auth::user()->getStoreId(),
            $date,
            $perPage,
            $fast,
        );

        return response()->json($report);
    }

    public function currentBalance(): JsonResponse
    {
        return response()->json([
            'balance' => $this->cashService->getCurrentBalance(
                Auth::user()->getStoreId()
            ),
        ]);
    }
}
