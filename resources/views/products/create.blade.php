@extends('layouts.app', ['title' => 'New Product'])

@section('content')
    @include('products.partials.form', ['title' => 'New Product', 'action' => route('products.store'), 'method' => 'post'])
@endsection
