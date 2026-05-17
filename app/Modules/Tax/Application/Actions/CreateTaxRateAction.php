<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Tax\Domain\Contracts\TaxRateRepository;
use App\Modules\Tax\Domain\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class CreateTaxRateAction
{
    public function __construct(
        private readonly TaxRateRepository $repository,
    ) {}

    public function execute(array $data): TaxRate
    {
        return DB::transaction(fn () => $this->repository->create($data));
    }
}
