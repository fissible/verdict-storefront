@extends('layouts.app')

@section('content')
    <h2 style="font-size:1rem">Pending approvals</h2>

    @if (session('verdict_approval_outcome'))
        <p class="notice">Decision outcome: <strong>{{ session('verdict_approval_outcome') }}</strong></p>
    @endif

    @forelse ($pending as $item)
        <div class="msg assistant">
            <div class="who">{{ $item['challenge']->capability }} — requested by {{ $item['customer'] ?? 'unknown' }}</div>
            @if ($item['challenge']->reason)<div>{{ $item['challenge']->reason }}</div>@endif
            <div class="toolcall">{{ json_encode($item['arguments']) }}</div>
            <div class="quiet">expires {{ $item['challenge']->expiresAt->format('Y-m-d H:i:s') }} UTC</div>
            <div style="margin-top:.5rem; display:flex; gap:.5rem">
                <form method="POST" action="{{ route('approvals.approve') }}">
                    @csrf
                    <input type="hidden" name="receipt_id" value="{{ $item['challenge']->receiptId }}">
                    <input type="hidden" name="tool_call_id" value="{{ $item['challenge']->toolCallId }}">
                    <button type="submit">Approve</button>
                </form>
                <form method="POST" action="{{ route('approvals.reject') }}">
                    @csrf
                    <input type="hidden" name="receipt_id" value="{{ $item['challenge']->receiptId }}">
                    <input type="hidden" name="tool_call_id" value="{{ $item['challenge']->toolCallId }}">
                    <button type="submit">Reject</button>
                </form>
            </div>
        </div>
    @empty
        <p class="quiet">Nothing is waiting for approval.</p>
    @endforelse
@endsection
