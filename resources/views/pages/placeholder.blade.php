@extends('layouts.app')

@section('title', $pageTitle . ' - NADI Console')

@push('styles')
<style>
    .page-header
    {
        margin-bottom: 24px;
    }

    .page-header h1
    {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .page-header p
    {
        font-size: 14px;
        color: #64748b;
    }

    .placeholder-card
    {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 32px;
        text-align: center;
    }

    .placeholder-icon
    {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        background: #e0f2fe;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 28px;
    }

    .placeholder-card h2
    {
        font-size: 18px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .placeholder-card p
    {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1>{{ $pageTitle }}</h1>

    <p>
        NADI Console / {{ $pageTitle }}
    </p>
</div>

<div class="placeholder-card">
    <div class="placeholder-icon">
        <i class="fas fa-layer-group"></i>
    </div>

    <h2>{{ $pageTitle }} Module</h2>

    <p>
        This module is ready for development.
    </p>
</div>
@endsection