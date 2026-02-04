<?php

namespace Webkul\Doctor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Doctor\Contracts\Shift as ShiftContract;

class Shift extends Model implements ShiftContract
{
    protected $table = 'doctor_shifts';

    protected $fillable = [
        'doctor_id',
        'date',
        'start_time',
        'end_time',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProxy::modelClass(), 'doctor_id');
    }
}
