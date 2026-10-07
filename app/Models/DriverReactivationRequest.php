<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverReactivationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'contact_number',
        'status',
        'resolved_by',
        'resolved_at',
        'note',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id', 'id')->withTrashed();
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by', 'id');
    }
}
