<?php

namespace App\Http\Controllers;

use App\Enums\ConceptClass;
use App\Models\Concept;
use App\Models\ConceptMapping;
use App\Services\Terminology;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Concept lookup for the pickers on clinical forms.
 */
class TerminologyController extends Controller
{
    public function search(Request $request, Terminology $terminology): JsonResponse
    {
        $data = $request->validate([
            'class' => ['required', Rule::enum(ConceptClass::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $concepts = $terminology->search(ConceptClass::from($data['class']), (string) ($data['q'] ?? ''));

        return response()->json(['concepts' => $concepts->map(fn (Concept $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'class' => $c->class->value,
            'unit' => $c->unit,
            'codes' => $c->mappings
                ->filter(fn (ConceptMapping $m) => $m->source->code !== 'LOCAL')
                ->map(fn (ConceptMapping $m) => $m->source->code.' '.$m->code)
                ->values()
                ->all(),
            'synonyms' => $c->synonyms->pluck('name')->all(),
        ])->values()]);
    }
}
