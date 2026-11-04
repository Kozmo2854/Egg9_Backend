<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

// TEMPORARY: staging-only mail test, remove after Resend verification
class TestMailController extends Controller
{
    /**
     * Send a test email via the configured mailer to verify Resend works.
     * Inert in production: only runs in staging/local environments.
     */
    public function send(Request $request)
    {
        abort_unless(app()->environment(['staging', 'local']), 404);

        $validated = $request->validate([
            'to' => ['required', 'email'],
        ]);

        $to = $validated['to'];

        try {
            Mail::raw('Egg9 Resend test — if you received this, Resend email works from the Laravel app.', function ($m) use ($to) {
                $m->to($to)->subject('Egg9 — Resend Test');
            });

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
