@extends('layouts.app', ['title' => 'Edit User'])

@section('content')
    @include('users.partials.form', ['title' => 'Edit User', 'action' => route('users.update', $user), 'method' => 'put'])
@endsection
