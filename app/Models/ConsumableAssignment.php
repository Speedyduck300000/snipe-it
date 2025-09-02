<?php

namespace App\Models;

use App\Models\Traits\CompanyableTrait;
use Illuminate\Database\Eloquent\Model;
use Watson\Validating\ValidatingTrait;

class ConsumableAssignment extends Model
{
    use CompanyableTrait;
    use ValidatingTrait;

    protected $table = 'consumables_users';

    protected $fillable = [
        'consumable_id',
        'assigned_to',
        'assigned_type',
        'note'
    ];

    protected $rules = [

    ];

    public function consumable()
    {
        return $this->belongsTo(\App\Models\Consumable::class);
    }

    public function assignedTo()
    {
        return $this->morphTo(null, 'assigned_type', 'assigned_to');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function asset()
    {
        return $this->belongsTo(\App\Models\Asset::class, 'assigned_to');
    }

    public function adminuser()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
