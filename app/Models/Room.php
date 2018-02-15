<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'title',
        'description',
        'bg_color',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function respondents()
    {
        return $this->belongsToMany(Respondent::class, 'room_respondent');
    }

    public function messages()
    {
        return $this->hasMany(RoomMessage::class)->orderBy('created_at');
    }

}
