<?php
@extends('layouts.app')

@section('content')
    <h1>Результаты поиска по ISBN: "{{ $query }}"</h1>

    @if($books->count())
        <ul>
            @foreach($books as $book)
                <li>{{ $book->title }} — ISBN: {{ $book->isbn }}</li>
            @endforeach
        </ul>
    @else
        <p>Ничего не найдено</p>
    @endif
@endsection