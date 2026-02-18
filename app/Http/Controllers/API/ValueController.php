<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataEntry\BulkUpsertValuesRequest;
use App\Http\Requests\DataEntry\FilterValuesRequest;
use App\Http\Requests\DataEntry\UpsertValueRequest;
use App\Models\EnteredKey;
use App\Models\EnteredKeyValue;
use App\Services\DataEntry\DueKeyResolverService;
use App\Services\DataEntry\ValueTypeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ValueController extends Controller
{
    public function __construct(
        private readonly ValueTypeService $typeService,
        private readonly DueKeyResolverService $dueService,
    ) {}

    /**
     * POST /engine/stores/{store_id}/dates/{date}/values
     */
    public function upsertOne(UpsertValueRequest $request, string $store_id, string $date): JsonResponse
    {
        $payload = $request->validated();
        $key = EnteredKey::findOrFail($payload['key_id']);

        // type check: exactly one correct value field
        $this->typeService->assertMatchesKeyType($key, $payload);

        // store_id and date come from path
        $value = EnteredKeyValue::updateOrCreate(
            [
                'key_id' => $key->id,
                'store_id' => $store_id,
                'entry_date' => $date,
            ],
            [
                'value_text' => $payload['value_text'] ?? null,
                'value_number' => $payload['value_number'] ?? null,
                'value_boolean' => $payload['value_boolean'] ?? null,
                'value_json' => $payload['value_json'] ?? null,
            ]
        );

        return response()->json($value);
    }

    /**
     * POST /engine/stores/{store_id}/dates/{date}/values/bulk
     */
    public function upsertBulk(BulkUpsertValuesRequest $request, string $store_id, string $date): JsonResponse
    {
        $payload = $request->validated();

        $saved = DB::transaction(function () use ($payload, $store_id, $date) {
            $out = [];
            foreach ($payload['items'] as $item) {
                $key = EnteredKey::findOrFail($item['key_id']);
                $this->typeService->assertMatchesKeyType($key, $item);

                $out[] = EnteredKeyValue::updateOrCreate(
                    [
                        'key_id' => $key->id,
                        'store_id' => $store_id,
                        'entry_date' => $date,
                    ],
                    [
                        'value_text' => $item['value_text'] ?? null,
                        'value_number' => $item['value_number'] ?? null,
                        'value_boolean' => $item['value_boolean'] ?? null,
                        'value_json' => $item['value_json'] ?? null,
                    ]
                );
            }
            return $out;
        });

        return response()->json(['items' => $saved]);
    }

    /**
     * GET /engine/values (global listing)
     */
    public function index(FilterValuesRequest $request): JsonResponse
    {
        $v = $request->validated();

        $q = EnteredKeyValue::query()
            ->with('key');

        if (!empty($v['key_id'])) $q->where('key_id', $v['key_id']);
        if (!empty($v['date'])) $q->whereDate('entry_date', $v['date']);
        if (!empty($v['from'])) $q->whereDate('entry_date', '>=', $v['from']);
        if (!empty($v['to'])) $q->whereDate('entry_date', '<=', $v['to']);

        if (!empty($v['label']) || !empty($v['data_type'])) {
            $q->whereHas('key', function ($k) use ($v) {
                if (!empty($v['label'])) $k->where('label', 'like', '%' . $v['label'] . '%');
                if (!empty($v['data_type'])) $k->where('data_type', $v['data_type']);
            });
        }

        $perPage = (int)($v['per_page'] ?? 50);

        return response()->json($q->orderByDesc('entry_date')->paginate($perPage));
    }

    /**
     * GET /engine/stores/{store_id}/values (store listing with extra filters + optional due_on)
     */
    public function storeIndex(FilterValuesRequest $request, string $store_id): JsonResponse
    {
        $v = $request->validated();

        $q = EnteredKeyValue::query()
            ->with('key')
            ->where('store_id', $store_id);

        if (!empty($v['key_id'])) $q->where('key_id', $v['key_id']);
        if (!empty($v['date'])) $q->whereDate('entry_date', $v['date']);
        if (!empty($v['from'])) $q->whereDate('entry_date', '>=', $v['from']);
        if (!empty($v['to'])) $q->whereDate('entry_date', '<=', $v['to']);

        if (!empty($v['label']) || !empty($v['data_type'])) {
            $q->whereHas('key', function ($k) use ($v) {
                if (!empty($v['label'])) $k->where('label', 'like', '%' . $v['label'] . '%');
                if (!empty($v['data_type'])) $k->where('data_type', $v['data_type']);
            });
        }

        // Filter values by rules (frequency/interval) for THIS store
        if (!empty($v['frequency_type']) || !empty($v['interval'])) {
            $q->whereHas('key.storeRules', function ($r) use ($v, $store_id) {
                $r->where('store_id', $store_id);
                if (!empty($v['frequency_type'])) $r->where('frequency_type', $v['frequency_type']);
                if (!empty($v['interval'])) $r->where('interval', (int)$v['interval']);
            });
        }

        // due_on: only return values whose KEYS are due that day for store (handy)
        if (!empty($v['due_on'])) {
            $due = $this->dueService->dueForStoreOnDate($store_id, Carbon::parse($v['due_on']));
            $dueKeyIds = $due->pluck('key_id')->all();
            $q->whereIn('key_id', $dueKeyIds);
        }

        $perPage = (int)($v['per_page'] ?? 50);

        return response()->json($q->orderByDesc('entry_date')->paginate($perPage));
    }

    public function grid(string $store_id, string $date): JsonResponse
    {
        // Parse the given date string to Carbon object
        $date = Carbon::parse($date)->startOfDay(); // Start of day ensures consistency for daily values

        // Fetch all the due keys for the given store and date (from the DueKeyResolverService)
        $dueKeys = app(DueKeyResolverService::class)->dueForStoreOnDate($store_id, $date);

        // Prepare the grid: Collect due keys and match them with existing values
        $grid = $dueKeys->map(function ($item) use ($store_id, $date) {

            // For each due key, check if there's an existing value for that store and date
            $existingValue = $item['filled']
                ? $item['value']
                : null;  // If not filled, it will be null

            return [
                'key_id' => $item['key_id'],
                'label' => $item['label'],
                'data_type' => $item['data_type'],
                'filled' => $item['filled'],
                'value' => $existingValue, // Will return null if not filled
            ];
        });

        // Return the response as JSON with the grid of due keys + existing values
        return response()->json([
            'store_id' => $store_id,
            'date' => $date->toDateString(),
            'grid' => $grid,
        ]);
    }
}
