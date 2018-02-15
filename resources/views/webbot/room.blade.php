@extends('layouts.webbot')

@section('content')
    <rooms room-id="{{ $room->id }}" project-id="{{ $survey->project_id }}" widget-id="{{ $widget->id }}" survey-id="{{ $survey->id }}" widget-json="{{ json_encode($widget) }}" start-data="{{ json_encode($start_data) }}"></rooms>
@endsection
