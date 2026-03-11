<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDebrief;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeDebriefController extends Controller
{

    /**
     * List debrief notes
     */
    public function index(Request $request, string $store_id): JsonResponse
    {

        $q = EmployeeDebrief::query()
            ->where('store_id', $store_id)
            ->with('author');

        if ($request->filled('employee_name')) {

            $q->where('employee_name', 'like', '%' . $request->employee_name . '%');

        }

        if ($request->filled('date')) {

            $q->whereDate('date', $request->date);

        }

        $perPage = (int) ($request->get('per_page', 50));

        return response()->json(
            $q->orderByDesc('date')->paginate($perPage)
        );
    }

    /**
     * Create note
     */
    public function store(Request $request, string $store_id): JsonResponse
    {

        $data = $request->validate([
            'employee_name' => 'required|string|max:255',
            'note' => 'required|string|max:5000',
            'date' => 'required|date_format:Y-m-d'
        ]);

        $user = $request->user();

        $debrief = EmployeeDebrief::create([

            'store_id' => $store_id,

            'user_id' => $user->id,

            'employee_name' => $data['employee_name'],

            'note' => $data['note'],

            'date' => $data['date'],

        ]);

        return response()->json($debrief, 201);
    }

    /**
     * Show single note
     */
    public function show(string $store_id, EmployeeDebrief $debrief): JsonResponse
    {

        if ($debrief->store_id !== $store_id) {

            abort(404);

        }

        return response()->json(
            $debrief->load('author')
        );
    }

    /**
     * Delete note
     */
    public function destroy(string $store_id, EmployeeDebrief $debrief): JsonResponse
    {

        if ($debrief->store_id !== $store_id) {

            abort(404);

        }

        $debrief->delete();

        return response()->json([
            'message' => 'Debrief deleted.'
        ]);
    }

}