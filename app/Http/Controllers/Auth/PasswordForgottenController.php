<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Users\Actions\RequestPasswordReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordForgottenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service reset of the password from the login page (M01 §5; D-279; PR-08), for guests only.
 *
 * After a valid request the page always shows the same message, whatever the address: it never says
 * whether an account exists. The link (48 hours, once) opens the page of AccessLinkController.
 */
class PasswordForgottenController extends Controller
{
    public const SENT_KEY = 'password_forgotten.sent';

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'submitUrl' => route('password.email'),
            'loginUrl' => route('login'),
            'sent' => $request->session()->get(self::SENT_KEY) === true,
        ]);
    }

    public function store(PasswordForgottenRequest $request, RequestPasswordReset $requestReset): RedirectResponse
    {
        $requestReset->handle($request->string('email')->toString());

        return redirect()->route('password.request')->with(self::SENT_KEY, true);
    }
}
