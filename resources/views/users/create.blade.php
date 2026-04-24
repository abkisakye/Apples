@extends('layouts.app', ['title' => 'Create User'])

@section('content')
    @include('users.partials.form', ['title' => 'Create User', 'action' => route('users.store'), 'method' => 'post'])
@endsection
