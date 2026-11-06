<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

/** How a person wants to hear from the library. The email digest is off until they turn it on. */
class Preference extends Model
{
    public const DIGESTS = ['off', 'daily', 'weekly'];

    protected $table = 'library_preferences';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_scan_at' => 'datetime', 'last_digest_at' => 'datetime'];
    }
}
