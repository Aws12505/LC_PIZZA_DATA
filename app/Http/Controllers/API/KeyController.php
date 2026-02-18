<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataEntry\StoreKeyRequest;
use App\Http\Requests\DataEntry\UpdateKeyRequest;
use App\Models\EnteredKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class KeyController extends Controller
{
    public function index(): JsonResponse
    {
        $keys = EnteredKey::with('storeRules')->orderBy('id', 'desc')->paginate();
        return response()->json($keys);
    }

    public function store(StoreKeyRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $key = DB::transaction(function () use ($payload) {
            $key = EnteredKey::create([
                'label' => $payload['label'],
                'data_type' => $payload['data_type'],
                'is_active' => $payload['is_active'] ?? true,
            ]);

            $key->storeRules()->createMany($payload['store_rules']);

            return $key->load('storeRules');
        });

        return response()->json($key, 201);
    }

    public function show(EnteredKey $key): JsonResponse
    {
        return response()->json($key->load('storeRules'));
    }

    public function update(UpdateKeyRequest $request, EnteredKey $key): JsonResponse
    {
        $payload = $request->validated();

        $key = DB::transaction(function () use ($payload, $key) {
            $key->update([
                'label' => $payload['label'],
                'data_type' => $payload['data_type'],
                'is_active' => $payload['is_active'] ?? $key->is_active,
            ]);

            // replace rules for clean UX
            $key->storeRules()->delete();
            $key->storeRules()->createMany($payload['store_rules']);

            return $key->load('storeRules');
        });

        return response()->json($key);
    }

    public function destroy(EnteredKey $key): JsonResponse
    {
        $key->update(['is_active' => false]);
        return response()->json(['message' => 'Key deactivated.']);
    }
}
