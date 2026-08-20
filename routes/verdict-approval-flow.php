<?php

declare(strict_types=1);

use App\Http\Controllers\ApprovalScreenController;
use App\Http\Controllers\VerdictApprovalDecisionController;
use Illuminate\Support\Facades\Route;

// Published by verdict:make-approval-flow and included from routes/web.php
// once the application's authentication and reviewer policy were in place:
// the `can:review-approvals` gate is this app's reviewer check (is_reviewer).
Route::middleware(['auth', 'can:review-approvals'])->group(function (): void {
    Route::get('/approvals', ApprovalScreenController::class)->name('approvals');
    Route::post('/verdict/approvals/approve', [VerdictApprovalDecisionController::class, 'approve'])->name('approvals.approve');
    Route::post('/verdict/approvals/reject', [VerdictApprovalDecisionController::class, 'reject'])->name('approvals.reject');
});
