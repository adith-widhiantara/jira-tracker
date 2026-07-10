<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasUuids;

    protected $guarded = [];

    public $timestamps = false;

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }
}
