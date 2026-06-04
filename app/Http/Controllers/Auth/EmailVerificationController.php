<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Display the email verification notice page.
     */
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('admin.dashboard', ['locale' => app()->getLocale()])
            : view('auth.verify-email');
    }

    /**
     * Handle the email verification callback link.
     */
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('admin.dashboard', ['locale' => app()->getLocale()])
            ->with('verified', true);
    }

    /**
     * Handle resending verification notification link.
     */
    public function send(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('resent', true);
    }
}
