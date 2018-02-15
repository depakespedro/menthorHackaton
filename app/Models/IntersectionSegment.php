<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntersectionSegment extends Model
{
    protected $table = 'intersections_segments';

    public $timestamps = false;

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function segmentOne()
    {
        return $this->hasOne(Segment::class, 'id', 'segment_one_id');
    }

    public function segmentTwo()
    {
        return $this->hasOne(Segment::class, 'id', 'segment_two_id');
    }
}
