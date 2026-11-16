@extends('errors.layout')

@section('title', 'Something went wrong · Isimbi Mart')
@section('code', 'Error 500 · Something went wrong')
@section('heading')
Something came <em>undone</em> on our side.
@endsection
@section('message', 'An unexpected error interrupted this page. Nothing you did caused it, and your bag is still saved. Please try again in a moment.')
@section('actions')
    <a href="/" class="store-error-link is-primary">Back to home</a>
    <a href="/shop" class="store-error-link">Shop all bags</a>
@endsection
@section('hints')
    <span>If this keeps happening,</span>
    <a href="/order/lookup">check your order status</a>
@endsection
