<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Concerns\HandlesCsvFileUpload;
use App\Services\GoToCallCsvProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoToCallController extends Controller
{
    use HandlesCsvFileUpload;

    public function uploadCsv(Request $request): JsonResponse
    {
        return $this->processCsvUpload(
            $request,
            'go_to_calls_uploads',
            'go_to_call_',
            fn (string $filePath) => (new GoToCallCsvProcessor())->process($filePath)
        );
    }
}
