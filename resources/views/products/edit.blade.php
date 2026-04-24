@extends('layouts.app', ['title' => 'Edit Product'])

@section('content')
    @include('products.partials.form', ['title' => 'Edit Product', 'action' => route('products.update', $product), 'method' => 'put'])
@endsection
