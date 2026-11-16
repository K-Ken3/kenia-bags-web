@extends('errors.layout')

@section('title', 'Session expired · Isimbi Mart')
@section('code', 'Error 419 · Session expired')
@section('heading')
Your session took a <em>little</em> pause.
@endsection
@section('message', 'For your security, this page expired after a period of inactivity. Go back, refresh the page, and submit the form again.')
@section('actions')
    <a href="/" class="store-error-link is-primary">Back to home</a>
    <a href="/shop" class="store-error-link">Continue shopping</a>
@endsection
@section('hints')
    <span>Checking out?</span>
    <a href="/cart">Return to your bag</a>
@endsection
