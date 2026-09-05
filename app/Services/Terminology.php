<?php

namespace App\Services;

use App\Enums\ConceptClass;
use App\Models\Concept;
use App\Models\ConceptMapping;
use App\Models\ConceptSource;
use App\Models\IcdCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The terminology service: finds, matches and resolves concepts so every
 * module names things the same way.
 */
class Terminology
{
    /**
     * Concepts of a class whose name, synonym or code contains the text,
     * exact and prefix matches first.
     *
     * @return Collection<int, Concept>
     */
    public function search(ConceptClass $class, string $query, int $limit = 15): Collection
    {
        $q = trim($query);

        if ($q === '') {
            return collect();
        }

        $like = '%'.$q.'%';

        return Concept::query()
            ->active()
            ->ofClass($class)
            ->where(fn (Builder $w) => $w
                ->where('name', 'like', $like)
                ->orWhereHas('synonyms', fn (Builder $s) => $s->where('name', 'like', $like))
                ->orWhereHas('mappings', fn (Builder $m) => $m->where('code', 'like', $q.'%')))
            ->with(['mappings.source', 'synonyms'])
            ->orderByRaw('case when lower(name) = ? then 0 when lower(name) like ? then 1 else 2 end', [Str::lower($q), Str::lower($q).'%'])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * The concept a typed name refers to, when it is exactly a concept's
     * name or synonym (case-insensitive). Used to code free text on save.
     */
    public function match(ConceptClass $class, ?string $name): ?Concept
    {
        $needle = Str::lower(trim((string) $name));

        if ($needle === '') {
            return null;
        }

        return Concept::query()
            ->active()
            ->ofClass($class)
            ->where(fn (Builder $w) => $w
                ->whereRaw('lower(name) = ?', [$needle])
                ->orWhereHas('synonyms', fn (Builder $s) => $s->whereRaw('lower(name) = ?', [$needle])))
            ->orderByRaw('case when lower(name) = ? then 0 else 1 end', [$needle])
            ->first();
    }

    /**
     * The concept to record for an entry: the one chosen on the form, else
     * the one whose name the typed text equals, else none (free text stays).
     */
    public function code(ConceptClass $class, ?int $conceptId, ?string $name): ?int
    {
        if ($conceptId !== null) {
            $concept = Concept::query()->active()->ofClass($class)->find($conceptId);

            if ($concept !== null) {
                return $concept->id;
            }
        }

        return $this->match($class, $name)?->id;
    }

    /**
     * The concept a code in a coding system identifies.
     */
    public function resolve(string $sourceCode, string $code): ?Concept
    {
        return ConceptMapping::query()
            ->whereHas('source', fn (Builder $s) => $s->where('code', $sourceCode))
            ->where('code', trim($code))
            ->with('concept')
            ->first()
            ?->concept;
    }

    /**
     * ICD-10 keeps its own catalogue; this is the one place that knows so.
     */
    public function icd(string $code): ?IcdCode
    {
        return IcdCode::findByCode($code);
    }

    public function source(string $code): ConceptSource
    {
        return ConceptSource::query()->where('code', $code)->firstOrFail();
    }

    /**
     * Create or update a concept by its local code, with its mappings and
     * synonyms. The way seeders and imports load terminology.
     *
     * @param  array<string, string>  $mappings  source code => code
     * @param  list<string>  $synonyms
     * @param  array<string, mixed>  $attributes
     */
    public function register(ConceptClass $class, string $localCode, string $name, array $mappings = [], array $synonyms = [], array $attributes = []): Concept
    {
        $local = $this->source(ConceptSource::LOCAL);

        $mapping = ConceptMapping::query()
            ->where('concept_source_id', $local->id)
            ->where('code', $localCode)
            ->first();

        $concept = $mapping->concept ?? new Concept;
        $concept->fill(['class' => $class->value, 'name' => $name, ...$attributes]);
        $concept->save();

        if ($mapping === null) {
            $concept->mappings()->create(['concept_source_id' => $local->id, 'code' => $localCode]);
        }

        foreach ($mappings as $sourceCode => $code) {
            ConceptMapping::query()->firstOrCreate(
                ['concept_source_id' => $this->source($sourceCode)->id, 'code' => $code],
                ['concept_id' => $concept->id],
            );
        }

        foreach ($synonyms as $synonym) {
            $concept->synonyms()->firstOrCreate(['name' => $synonym]);
        }

        return $concept;
    }
}
