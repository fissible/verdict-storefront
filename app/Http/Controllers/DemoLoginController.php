<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * One-click sign-in as a seeded demo customer. A demo-only pattern: the data
 * is synthetic and reversible, and the walkthroughs need "you are Alice" to
 * be one click — do not copy this into a real application.
 */
final class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->is_reviewer ? 'approvals' : 'chat');
    }
}
