<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMetricRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by auth.tenant middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Check if the payload is an array of metrics or a single metric
        $input = $this->all();

        // If the root is an array (bulk submission), validate each item
        if ($this->isArrayOfMetrics($input)) {
            return [
                '*.metric_name' => 'required|string|max:64',
                '*.value' => 'required|numeric',
                '*.timestamp' => 'required|date',
                '*.agent_id' => 'nullable|string',
                '*.dedupe_id' => 'nullable|string',
            ];
        }

        // Single metric submission
        return [
            'metric_name' => 'required|string|max:64',
            'value' => 'required|numeric',
            'timestamp' => 'required|date',
            'agent_id' => 'nullable|string',
            'dedupe_id' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'metric_name.required' => 'The metric name is required.',
            'metric_name.string' => 'The metric name must be a string.',
            'metric_name.max' => 'The metric name may not be greater than 64 characters.',
            'value.required' => 'The metric value is required.',
            'value.numeric' => 'The metric value must be a number.',
            'timestamp.required' => 'The timestamp is required.',
            'timestamp.date' => 'The timestamp must be a valid date.',
            '*.metric_name.required' => 'Each metric must have a metric name.',
            '*.metric_name.string' => 'Each metric name must be a string.',
            '*.metric_name.max' => 'Each metric name may not be greater than 64 characters.',
            '*.value.required' => 'Each metric must have a value.',
            '*.value.numeric' => 'Each metric value must be a number.',
            '*.timestamp.required' => 'Each metric must have a timestamp.',
            '*.timestamp.date' => 'Each metric timestamp must be a valid date.',
        ];
    }

    /**
     * Determine if the input is an array of metrics.
     *
     * @param mixed $input
     * @return bool
     */
    private function isArrayOfMetrics($input): bool
    {
        // If it's not an array, it's a single metric
        if (!is_array($input)) {
            return false;
        }

        // If it's an empty array, treat as bulk (will fail validation)
        if (empty($input)) {
            return true;
        }

        // Check if it's an indexed array (numeric keys starting from 0)
        // vs an associative array (single metric with string keys)
        $keys = array_keys($input);
        return $keys === array_keys($keys);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
