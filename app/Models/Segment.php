<?php

namespace App\Models;

use App\Model\Condition;
use Illuminate\Database\Eloquent\Model;
use App\Models\Filter;
use Illuminate\Database\Eloquent\Builder;
use Exception;
use Throwable;

class Segment extends Model
{
    public $timestamps = false;

    public function filters()
    {
        return $this->belongsToMany(Filter::class, 'segments_filters', 'segment_id', 'filter_id')
            ->withPivot(['argument', 'condition_id']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->hasOne(Project::class, 'id');
    }

    public function attachFilter(Filter $filter, $argument = null, Condition $condition = null)
    {
        if (is_null($argument)) {
            $this->filters()->syncWithoutDetaching([$filter->id]);
        } else {
            $this->filters()->syncWithoutDetaching([$filter->id => ['argument' => $argument, 'condition_id' => $condition->id]]);
        }

        return $this;
    }

    public function detachFilter(Filter $filter)
    {
        $this->filters()->detach($filter);

        return $this;
    }

    public function detachAllFilters()
    {
        $filters = $this->filters;

        foreach ($filters as $filter) {
            $this->filters()->detach($filter);
        }

        return $this;
    }

    public function filterRespondents(Builder $query)
    {
        $filters = $this->filters;

        foreach ($filters as $filter) {
            try {
                $query = $this->getFilterQuery($query, $filter);
            } catch (Exception $exception) {
                return null;
            }
        }

        return $query;
    }

    private function getFilterQuery(Builder $query, Filter $filter)
    {
        $scopeName = $filter->scope;
        $filterArgument = $filter->argument;
        $filterCondition = $filter->condition;
        try {
            if (is_null($filterArgument) or is_null($filterCondition)) {
                $query->$scopeName();
            } elseif(in_array($scopeName, ['choiceQuestion', 'multipleQuestion', 'freeTextQuestion', 'guessQuestion'])) {
                $filterArgument = unserialize($filterArgument);
                $query->$scopeName($filterArgument, $filterCondition);
            } else {
                $query->$scopeName($filterArgument, $filterCondition);
            }
        } catch (Exception $exception) {
            throw new Exception('Error call scope filter - ' . $scopeName);
        } catch (Throwable $exception) {
            throw new Exception('Error call scope filter - ' . $scopeName . ' error count argument');
        }

        return $query;
    }

}
