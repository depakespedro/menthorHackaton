<?php

namespace App\Models;

use App\Model\Condition;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class Filter extends Model
{
    protected $table = 'filters';

    public $timestamps = false;

    protected $appends = [
        'argument',
        'condition'
    ];

    public function conditions()
    {
        return  $this->belongsToMany(Condition::class, 'filters_conditions');
    }

    public function getArgumentAttribute()
    {
        $pivot = $this->pivot;

        if (is_null($pivot)) {
            return null;
        }

        return $this->pivot->argument;
    }

    public function getConditionAttribute()
    {
        $pivot = $this->pivot;

        if (is_null($pivot)) {
            return null;
        }

        $condition = Condition::find($this->pivot->condition_id);

        return $condition;
    }

    public function getRespondents(array $filterArguments = null)
    {
        $respondetns = Respondent::query();

        $scopeName = $this->scope;

        try {
            if (is_null($filterArguments) or empty($filterArguments)) {
                $respondetns->$scopeName();
            } else {
                $respondetns->$scopeName($filterArguments);
            }
        } catch (Throwable $exception) {
            return null;
        }

        return $respondetns->get();
    }
}
