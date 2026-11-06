<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Model;

/** Public intelligence: a store of its own, with no company in it. */
abstract class IntelligenceModel extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function getConnectionName(): ?string
    {
        return config('intelligence.connection');
    }
}
