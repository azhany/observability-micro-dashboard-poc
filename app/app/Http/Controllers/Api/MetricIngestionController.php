<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMetricRequest;
use App\Jobs\ProcessMetricSubmission;
use Illuminate\Http\JsonResponse;

class MetricIngestionController extends Controller
{
    /**
     * Store metrics (single or bulk) for ingestion.
     */
    public function store(StoreMetricRequest $request): JsonResponse
    {
        // Retrieve tenant from request (set by AuthenticateTenantToken middleware)
        $tenant = $request->attributes->get('tenant');

        // Get validated data
        $data = $request->validated();

        // Normalize payload - ensure it's always an array
        // If the first key is numeric (0), it's already an array of metrics
        // Otherwise, it's a single metric object
        $metrics = $this->normalizePayload($data);

        // Dispatch one job per HTTP request containing the full batch
        ProcessMetricSubmission::dispatch($tenant, $metrics);

        // Return 202 Accepted
        return response()->json(['status' => 'accepted'], 202);
    }

    /**
     * Normalize the payload to always return an array of metrics.
     */
    private function normalizePayload(array $data): array
    {
        // If data is empty, return empty array
        if (empty($data)) {
            return [];
        }

        // Check if it's already an array of metrics (indexed array)
        $keys = array_keys($data);
        $isIndexedArray = $keys === array_keys($keys);

        // If it's an indexed array, it's already normalized (bulk submission)
        if ($isIndexedArray) {
            return $data;
        }

        // Otherwise, it's a single metric - wrap it in an array
        return [$data];
    }
}
