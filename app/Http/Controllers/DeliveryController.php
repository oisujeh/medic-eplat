<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordDeliveryRequest;
use App\Models\Birth;
use App\Models\Pregnancy;
use App\Services\MaternityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Recording deliveries and the babies born.
 */
class DeliveryController extends Controller
{
    public function __construct(private readonly MaternityService $maternity) {}

    /**
     * Record the delivery that closes a pregnancy.
     */
    public function store(RecordDeliveryRequest $request, Pregnancy $pregnancy): RedirectResponse
    {
        $data = $request->validated();
        $births = $data['births'];
        unset($data['births']);

        $delivery = $this->maternity->recordDelivery($pregnancy, $request->user(), $data, $births);

        $live = $delivery->births->filter(fn (Birth $b) => $b->outcome->isLive())->count();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Delivery recorded: '.$live.' live '.($live === 1 ? 'birth' : 'births').' of '.$delivery->births->count().'.',
        ]);

        return back();
    }

    /**
     * Open a patient record for a live-born baby.
     */
    public function registerNewborn(Request $request, Birth $birth): RedirectResponse
    {
        $baby = $this->maternity->registerNewborn($birth, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Baby registered as {$baby->file_number}."]);

        return back();
    }
}
