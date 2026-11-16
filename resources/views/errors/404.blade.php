@extends('errors.layout')

@section('title', 'Page not found · Isimbi Mart')
@section('code', 'Error 404 · Page not found')
@section('heading')
This page has <em>slipped</em> out of the bag.
@endsection
@section('message', 'The page you are looking for may have moved, sold out, or never existed. Your bag is safe — let us help you find something beautiful instead.')
@section('actions')
    <a href="/shop" class="store-error-link is-primary">Continue shopping</a>
    <a href="/" class="store-error-link">Back to home</a>
@endsection
@section('hints')
    <span>Looking for an order?</span>
    <a href="/order/lookup">Track your order</a>
@endsection
