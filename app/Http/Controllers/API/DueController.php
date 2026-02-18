<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataEntry\DueRangeRequest;
use App\Services\DataEntry\DueKeyResolverService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DueController extends Controller
{
    public function __construct(
        private readonly DueKeyResolverService $due
    ) {}

    /**
     * GET /engine/stores/{store_id}/dates/{date}/due
     */
    public function dueOnDate(string $store_id, string $date): JsonResponse
    {
        $items = $this->due->dueForStoreOnDate($store_id, Carbon::parse($date));
        return response()->json([
            'store_id' => $store_id,
            'date' => $date,
            'items' => $items,
        ]);
    }

    /**
     * GET /engine/stores/{store_id}/due-range?from=...&to=...
     */
    public function dueRange(DueRangeRequest $request, string $store_id): JsonResponse
    {
        $v = $request->validated();
        $data = $this->due->dueRange($store_id, Carbon::parse($v['from']), Carbon::parse($v['to']));

        return response()->json([
            'store_id' => $store_id,
            'from' => $v['from'],
            'to' => $v['to'],
            'days' => $data,
        ]);
    }
}
