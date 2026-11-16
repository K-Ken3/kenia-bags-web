@extends('errors.layout')

@section('title', 'Access denied · Isimbi Mart')
@section('code', 'Error 403 · Access denied')
@section('heading')
This part of the shop is <em>private</em>.
@endsection
@section('message', 'You do not have permission to view this page. If you followed a link from your account, try signing in again.')
@section('actions')
    <a href="/" class="store-error-link is-primary">Back to home</a>
    <a href="/shop" class="store-error-link">Shop all bags</a>
@endsection
@section('hints')
    <span>An administrator?</span>
    <a href="/admin/login">Sign in to the admin panel</a>
@endsection
