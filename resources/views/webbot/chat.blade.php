@extends('layouts.webbot')

@section('content')
    <chat latlng="{{ $latlng }}" project-id="{{ $survey->project_id }}" widget-id="{{ $widget->id }}" survey-id="{{ $survey->id }}" widget-json="{{ json_encode($widget) }}" server-timeout="{{ (int)round((microtime(true) - $timeStarted) * 1000) }}" start-data="{{ json_encode($start_data) }}"></chat>
@endsection
