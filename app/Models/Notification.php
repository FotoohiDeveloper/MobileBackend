<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'description',
        'message',
        'read'
    ];

    // Make notification read
    public function markAsRead()
    {
        $this->update(['read' => 1]);
    }

    // Make all users notifications read
    public static function markAllAsRead($userId)
    {
        self::where('user_id', $userId)->update(['read' => 1]);
    }

}
