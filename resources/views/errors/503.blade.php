@extends('errors.layout')

@section('title', 'Page unavailable · Isimbi Mart')
@section('code', 'Error 503 · Temporarily unavailable')
@section('heading')
We are giving the shop a <em>quick</em> polish.
@endsection
@section('message', 'Isimbi Mart is briefly unavailable while we make an update. Please check back in a few minutes — your bag will be waiting.')
@section('actions')
    <a href="/" class="store-error-link is-primary">Back to home</a>
    <a href="/shop" class="store-error-link">Shop all bags</a>
@endsection
@section('hints')
    <span>Need to check an order?</span>
    <a href="/order/lookup">Track your order</a>
@endsection
