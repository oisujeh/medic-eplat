<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    /**
     * Show a module landing page.
     *
     * This is a generic placeholder so the role-driven navigation works
     * end-to-end. Replace it with real module controllers/pages as each
     * module is built, protecting them with the `module:{slug}` middleware.
     */
    public function show(Request $request, Module $module): Response
    {
        abort_unless($module->is_active && $request->user()->canAccessModule($module->slug), 403);

        return Inertia::render('modules/Show', [
            'module' => [
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
            ],
        ]);
    }
}
