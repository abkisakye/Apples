@extends('layouts.app', ['title' => 'Edit Supplier'])

@section('content')
    @include('suppliers.partials.form', ['title' => 'Edit Supplier', 'action' => route('suppliers.update', $supplier), 'method' => 'put'])
@endsection
