<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomMessage extends Model
{
    protected $table = 'room_messages';

    protected $fillable = [
        'respondent_id',
        'body',
    ];

    public function respondent()
    {
        return $this->belongsTo(Respondent::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

}
