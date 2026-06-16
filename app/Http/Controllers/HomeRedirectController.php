<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeRedirectController extends Controller
{
    /**
     * Redirect the application root to the dashboard for authenticated
     * users, or to the login screen for guests.
     */
    public function __invoke(): RedirectResponse
    {
        return redirect()->route(auth()->check() ? 'dashboard' : 'login');
    }
}
