<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'analytics_dashboard_id', 'widget_type', 'title', 'config', 'col', 'row', 'width', 'height',
    ];

    protected $casts = [
        'config' => 'array',
        'col' => 'integer',
        'row' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(AnalyticsDashboard::class, 'analytics_dashboard_id');
    }
}
