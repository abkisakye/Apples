@extends('layouts.app', ['title' => 'New Supplier'])

@section('content')
    @include('suppliers.partials.form', ['title' => 'New Supplier', 'action' => route('suppliers.store'), 'method' => 'post'])
@endsection
