<?php

namespace App\Domain\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\Users\Models\User;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $fillable = [
        'reporter_id', 'kind', 'description', 'module', 'status',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
