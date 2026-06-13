<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class BackofficeController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('backoffice.templates.index');
    }
}
