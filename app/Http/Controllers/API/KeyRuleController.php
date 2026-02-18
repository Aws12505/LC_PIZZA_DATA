<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataEntry\UpdateKeyRequest;
use App\Models\EnteredKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class KeyRuleController extends Controller
{
    public function index(EnteredKey $key): JsonResponse
    {
        return response()->json($key->storeRules()->orderBy('store_id')->get());
    }

    /**
     * Replace rules only (expects same payload format as update key, but we only use store_rules).
     */
    public function replace(UpdateKeyRequest $request, EnteredKey $key): JsonResponse
    {
        $payload = $request->validated();

        $key = DB::transaction(function () use ($payload, $key) {
            $key->storeRules()->delete();
            $key->storeRules()->createMany($payload['store_rules']);
            return $key->load('storeRules');
        });

        return response()->json($key->storeRules);
    }
}
