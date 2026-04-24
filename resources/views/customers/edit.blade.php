@extends('layouts.app', ['title' => 'Edit Customer'])

@section('content')
    @include('customers.partials.form', ['title' => 'Edit Customer', 'action' => route('customers.update', $customer), 'method' => 'put'])
@endsection
