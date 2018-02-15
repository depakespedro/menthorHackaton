@extends('layouts.mails')
@section('content')

    Здравствуйте {{$respondent->first_name}} !!!
    {{ $text }}.
    Открыть комнату в чате можно по ссылке <a href="{{$roomUrl}}">{{ $roomUrl }}</a>

@endsection
