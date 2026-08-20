<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only pages over the decision-evidence, provenance, and receipt tables
 * (#237): every walkthrough ends at one of these rows. Reads the tables
 * directly on purpose — evidence is an ordinary queryable audit store
 * (verdict docs/incident-response.md), and showing that IS the demo.
 */
final class EvidenceBrowserController extends Controller
{
    public function __invoke(): View
    {
        return view('evidence', [
            'decisions' => DB::table('verdict_evidence')
                ->latest('recorded_at')->latest('id')
                ->limit(50)
                ->get(),
            'receipts' => DB::table('verdict_approval_receipts')
                ->latest('created_at')
                ->limit(50)
                ->get(),
            'derivations' => DB::table('verdict_provenance_derivations')
                ->latest('recorded_at')
                ->limit(50)
                ->get(),
        ]);
    }
}
