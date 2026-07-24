<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class BackofficeController extends Controller
{
    /** The company picker is the entry point: everything else acts on the selected company. */
    public function index(): RedirectResponse
    {
        return redirect()->route('backoffice.tenants.index');
    }
}
