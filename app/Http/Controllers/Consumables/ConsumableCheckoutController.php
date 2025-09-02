<?php

namespace App\Http\Controllers\Consumables;

use App\Events\CheckoutableCheckedOut;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CheckInOutRequest;
use App\Models\Consumable;
use App\Models\Asset;
use Carbon\Carbon;
use App\Models\ConsumableAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use \Illuminate\Contracts\View\View;
use \Illuminate\Http\RedirectResponse;

class ConsumableCheckoutController extends Controller
{

    use CheckInOutRequest;
    /**
     * Return a view to checkout a consumable to a user.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see ConsumableCheckoutController::store() method that stores the data.
     * @since [v1.0]
     * @param int $id
     */
    public function create($id) : View | RedirectResponse
    {

        if ($consumable = Consumable::find($id)) {

            $this->authorize('checkout', $consumable);

            // Make sure the category is valid
            if ($consumable->category) {

                // Make sure there is at least one available to checkout
                if ($consumable->numRemaining() <= 0){
                    return redirect()->route('consumables.index')
                        ->with('error', trans('admin/consumables/message.checkout.unavailable', ['requested' => 1, 'remaining' => $consumable->numRemaining()]));
                }

                // Return the checkout view
                return view('consumables/checkout', compact('consumable'));
            }

            // Invalid category
            return redirect()->route('consumables.edit', ['consumable' => $consumable->id])
                ->with('error', trans('general.invalid_item_category_single', ['type' => trans('general.consumable')]));
        }

        // Not found
        return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.does_not_exist'));

    }

    /**
     * Saves the checkout information
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see ConsumableCheckoutController::create() method that returns the form.
     * @since [v1.0]
     * @param int $consumableId
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request, $consumableId)
    {
        if (is_null($consumable = Consumable::with('consumableAssignments')->find($consumableId))) {
            return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.not_found'));
        }

        $this->authorize('checkout', $consumable);

        $target = $this->determineCheckoutTarget();
        $consumable->checkout_qty = $request->input('checkout_qty', 1);


        for ($i = 0; $i < $consumable->checkout_qty; $i++) {
            
            $consumableAssignment = new ConsumableAssignment([
                'consumable_id' => $consumable->id,
                'assigned_to' => $target->id,
                'assigned_type' => $target::class,
                'note' => $request->input('note'),
            ]);
            
            dd($consumableAssignment);

            $consumableAssignment->created_by = auth()->id();
            $consumableAssignment->save(); 

        }

        event(new CheckoutableCheckedOut($consumable,  $target, auth()->user(), $request->input('note')));

        $request->request->add(['checkout_to_type' => request('checkout_to_type')]);
        if (request('checkout_to_type') === 'asset') {
            $request->request->add(['assigned_asset' => $target->id]);
        } else {
            $request->request->add(['assigned_user' => $target->id]);
        }

        session()->put(['redirect_option' => $request->get('redirect_option'), 'checkout_to_type' => $request->get('checkout_to_type')]);

        return Helper::getRedirectOption($request, $consumable->id, 'Consumables')
            ->with('success', trans('admin/consumables/message.checkout.success'));
    }
}