<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeatureValue extends Model
{
    protected $table = 'plan_features';

    protected $fillable = ['plan_id', 'feature', 'value'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
