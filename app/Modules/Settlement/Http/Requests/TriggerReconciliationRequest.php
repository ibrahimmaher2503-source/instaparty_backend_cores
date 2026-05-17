<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Requests;

use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class TriggerReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     *
     * @bodyParam scope string required The scope of the reconciliation run. Enum: all, wallet, vendor, date_range, recent_touch. Example: all
     * @bodyParam wallet_id integer The wallet ID (required when scope=wallet). Example: 42
     * @bodyParam vendor_id integer The vendor profile ID (required when scope=vendor). Example: 7
     * @bodyParam date_from string ISO date (required when scope=date_range). Example: 2026-01-01
     * @bodyParam date_to string ISO date (required when scope=date_range). Example: 2026-01-31
     * @bodyParam window string Time window string (used when scope=recent_touch). Example: 60min
     */
    public function rules(): array
    {
        return [
            'scope'     => ['required', 'string', 'in:all,wallet,vendor,date_range,recent_touch'],
            'wallet_id' => ['sometimes', 'integer', 'min:1'],
            'vendor_id' => ['sometimes', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to'   => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'window'    => ['sometimes', 'string', 'max:20'],
        ];
    }

    public function toInput(): RunReconciliationInput
    {
        $scopeParams = array_filter([
            'wallet_id' => $this->input('wallet_id'),
            'vendor_id' => $this->input('vendor_id'),
            'date_from' => $this->input('date_from'),
            'date_to'   => $this->input('date_to'),
            'window'    => $this->input('window'),
        ]);

        return new RunReconciliationInput(
            scopeType:         $this->input('scope'),
            scopeParams:       $scopeParams,
            triggerKind:       'admin_endpoint',
            triggeredByUserId: $this->user()?->id,
            idempotencyKey:    $this->header('Idempotency-Key'),
            correlationId:     (string) Str::ulid(),
        );
    }
}
