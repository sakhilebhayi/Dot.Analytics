<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

/** Something the library noticed about what a person watches. Written once, never edited, read or unread. */
class Alert extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'library_alerts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
