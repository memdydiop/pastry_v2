<?php

namespace App\Livewire\Concerns;

use App\Enums\PlanFeature;
use Illuminate\Validation\ValidationException;

trait HandlesPlanLimits
{
    protected function abortIfFeatureMissing(PlanFeature $feature): void
    {
        $tenant = tenant();
        if (!$tenant || !$tenant->canUse($feature)) {
            throw ValidationException::withMessages([
                'plan' => __('Votre forfait ne permet pas cette action.'),
            ]);
        }
    }

    protected function checkLimit(string $key, int $currentCount, int $toBeAdded = 1): void
    {
        $tenant = tenant();
        if (!$tenant) return;

        $limit = $tenant->getLimit($key);
        if ($limit !== null && ($currentCount + $toBeAdded) > $limit) {
            throw ValidationException::withMessages([
                'plan' => __("Limite atteinte (:limit maximum). Passez à un forfait supérieur.", ['limit' => $limit]),
            ]);
        }
    }
}
