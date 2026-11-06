<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

/** A development or a question a person wants to come back to. */
class Saved extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'library_saved';

    protected $guarded = ['id'];
}
