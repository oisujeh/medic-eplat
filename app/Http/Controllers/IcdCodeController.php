<?php

namespace App\Http\Controllers;

use App\Models\IcdCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ICD-10 lookup for the diagnosis picker.
 */
class IcdCodeController extends Controller
{
    /**
     * Look up codes by code prefix or description.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());

        if ($q === '') {
            return response()->json(['codes' => []]);
        }

        $prefix = IcdCode::normalise($q).'%';

        $codes = IcdCode::query()
            ->active()
            ->where(fn ($w) => $w
                ->where('code', 'like', $prefix)
                ->orWhere('description', 'like', "%{$q}%"))
            ->orderByRaw('case when code like ? then 0 else 1 end', [$prefix])
            ->orderBy('code')
            ->limit(15)
            ->get(['id', 'code', 'description', 'chapter'])
            ->map(fn (IcdCode $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'description' => $c->description,
                'chapter' => $c->chapter,
            ]);

        return response()->json(['codes' => $codes]);
    }
}
