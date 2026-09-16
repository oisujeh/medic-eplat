<?php

namespace App\Http\Controllers;

use App\Enums\ServiceCategory;
use App\Models\ServiceCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceChargeController extends Controller
{
    /**
     * The facility fee schedule.
     */
    public function index(): Response
    {
        $services = ServiceCharge::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceCharge $s) => $this->card($s));

        return Inertia::render('billing/Services', [
            'services' => $services,
            'categories' => collect(ServiceCategory::cases())->map(fn (ServiceCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    /**
     * Add a service to the fee schedule.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:service_charges,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        ServiceCharge::create([...$data, 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Service added to the fee schedule.']);

        return back();
    }

    /**
     * Update a service's price / details.
     */
    public function update(Request $request, ServiceCharge $serviceCharge): RedirectResponse
    {
        $serviceCharge->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fee updated.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function card(ServiceCharge $service): array
    {
        return [
            'id' => $service->id,
            'code' => $service->code,
            'name' => $service->name,
            'label' => $service->label(),
            'category' => $service->category->value,
            'category_label' => $service->category->label(),
            'unit' => $service->unit,
            'price' => $service->price,
            'is_active' => $service->is_active,
        ];
    }
}
