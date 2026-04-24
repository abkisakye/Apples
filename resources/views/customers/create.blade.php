@extends('layouts.app', ['title' => 'New Customer'])

@section('content')
    @include('customers.partials.form', ['title' => 'New Customer', 'action' => route('customers.store'), 'method' => 'post'])
@endsection
