@extends('layouts.app')

@section('content')
    <h2 style="font-size:1rem">Decision evidence</h2>
    <p class="quiet">Every proposal, refresh, approval phase, and execution leaves a row. Newest first.</p>
    @if ($decisions->isEmpty())
        <p class="quiet">No decisions recorded yet — talk to the support agent first.</p>
    @endif
    <div style="overflow-x:auto">
        <table style="width:100%; border-collapse:collapse; font-size:.85rem">
            <thead>
            <tr style="text-align:left">
                <th style="padding:.3rem .5rem">recorded</th>
                <th style="padding:.3rem .5rem">capability</th>
                <th style="padding:.3rem .5rem">stage</th>
                <th style="padding:.3rem .5rem">disposition</th>
                <th style="padding:.3rem .5rem">reason</th>
                <th style="padding:.3rem .5rem">target</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($decisions as $row)
                <tr style="border-top:1px solid rgba(148,163,184,.3)">
                    <td style="padding:.3rem .5rem; white-space:nowrap">{{ $row->recorded_at }}</td>
                    <td style="padding:.3rem .5rem">{{ $row->capability }}</td>
                    <td style="padding:.3rem .5rem">{{ $row->stage }}{{ $row->approval_phase ? ' · '.$row->approval_phase : '' }}</td>
                    <td style="padding:.3rem .5rem"><strong>{{ $row->disposition }}</strong></td>
                    <td style="padding:.3rem .5rem">{{ $row->reason }}</td>
                    <td style="padding:.3rem .5rem">{{ $row->target_strategy }}{{ $row->target_identity_matched !== null ? ($row->target_identity_matched ? ' ✓' : ' ✗') : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <h2 style="font-size:1rem; margin-top:1.5rem">Approval receipts</h2>
    <div style="overflow-x:auto">
        <table style="width:100%; border-collapse:collapse; font-size:.85rem">
            <thead>
            <tr style="text-align:left">
                <th style="padding:.3rem .5rem">created</th>
                <th style="padding:.3rem .5rem">capability</th>
                <th style="padding:.3rem .5rem">status</th>
                <th style="padding:.3rem .5rem">decided by</th>
                <th style="padding:.3rem .5rem">consumed</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($receipts as $receipt)
                <tr style="border-top:1px solid rgba(148,163,184,.3)">
                    <td style="padding:.3rem .5rem; white-space:nowrap">{{ $receipt->created_at }}</td>
                    <td style="padding:.3rem .5rem">{{ $receipt->capability }}</td>
                    <td style="padding:.3rem .5rem"><strong>{{ $receipt->consumed_at ? 'consumed' : $receipt->status }}</strong></td>
                    <td style="padding:.3rem .5rem">{{ $receipt->approved_by ?? $receipt->rejected_by }}</td>
                    <td style="padding:.3rem .5rem; white-space:nowrap">{{ $receipt->consumed_at }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <h2 style="font-size:1rem; margin-top:1.5rem">Provenance derivations</h2>
    <p class="quiet">Declared upstream sources for proposals (fingerprints — raw content is never stored here).</p>
    <div style="overflow-x:auto">
        <table style="width:100%; border-collapse:collapse; font-size:.85rem">
            <thead>
            <tr style="text-align:left">
                <th style="padding:.3rem .5rem">recorded</th>
                <th style="padding:.3rem .5rem">correlation</th>
                <th style="padding:.3rem .5rem">kind</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($derivations as $derivation)
                <tr style="border-top:1px solid rgba(148,163,184,.3)">
                    <td style="padding:.3rem .5rem; white-space:nowrap">{{ $derivation->recorded_at }}</td>
                    <td style="padding:.3rem .5rem">{{ $derivation->correlation_id }}</td>
                    <td style="padding:.3rem .5rem">{{ $derivation->kind }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="padding:.3rem .5rem" class="quiet">None declared yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
