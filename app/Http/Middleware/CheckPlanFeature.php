<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();
        $featureEnum = PlanFeature::tryFrom($feature);

        if (!$tenant || !$featureEnum || !$tenant->canUse($featureEnum)) {
            abort(403, __('Votre forfait ne permet pas d\'accéder à cette fonctionnalité.'));
        }

        return $next($request);
    }
}
