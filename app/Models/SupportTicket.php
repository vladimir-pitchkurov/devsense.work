<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_email',
        'sender_name',
        'subject',
        'message',
        'attachments',
        'type',
        'status',
        'reply_message',
        'replied_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Scope a query to only include open tickets.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope a query to only include answered tickets.
     */
    public function scopeAnswered($query)
    {
        return $query->where('status', 'answered');
    }
}
