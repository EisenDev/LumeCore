<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentationController extends Controller
{
    /**
     * Display the documentation page.
     */
    public function index(Request $request)
    {
        return Inertia::render('Documentation/Index', [
            'section' => $request->query('section', 'introduction'),
        ]);
    }
}
