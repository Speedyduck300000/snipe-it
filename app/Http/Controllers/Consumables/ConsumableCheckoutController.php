<?php

namespace App\Http\Controllers\Consumables;

use App\Events\CheckoutableCheckedOut;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Consumable;
use App\Models\ConsumableAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use \Illuminate\Contracts\View\View;
use \Illuminate\Http\RedirectResponse;

class ConsumableCheckoutController extends Controller
{
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
        if (is_null($consumable = Consumable::with('users')->find($consumableId))) {
            return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.not_found'));
        }

        $this->authorize('checkout', $consumable);
        
        // If the quantity is not present in the request or is not a positive integer, set it to 1
        $quantity = $request->input('checkout_qty');
        if (!isset($quantity) || !ctype_digit((string)$quantity) || $quantity <= 0) {
            $quantity = 1;
        }

        // Make sure there is at least one available to checkout
        if ($consumable->numRemaining() <= 0 || $quantity > $consumable->numRemaining()) {
            return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.checkout.unavailable', ['requested' => $quantity, 'remaining' => $consumable->numRemaining() ]));
        }

        $admin_user = auth()->user();
        
        $consumable = Consumable::with([
            'consumableAssignments:id,consumable_id,assigned_to,asset_id,created_by,note,created_at',
            'consumableAssignments.user:id,first_name,last_name',
            'consumableAssignments.asset:id,asset_tag,name',
        ])->findOrFail($consumableId);

        // Zugriff:
        foreach ($consumable->consumableAssignments as $asgn) {
            $userId  = $asgn->assigned_to;
            $assetId = $asgn->asset_id;
            $user    = $asgn->user;   // optional vor-geladen
            $asset   = $asgn->asset;  // optional vor-geladen
        }


        for ($i = 0; $i < $quantity; $i++){
        $consumable->users()->attach($consumable->id, [ 
            'consumable_id' => $consumable->id,
            'created_by' => $admin_user->id,
            'assigned_to' => e($request->input('assigned_to')),
            'asset_id' => e($request->input('assigned_to')),
            'note' => $request->input('note'),
        ]);
        }
        
        $consumable->checkout_qty = $quantity;

        $consumable = $this->findConsumableToCheckout($consumable, $consumableId);

        if($request->filled('asset_id')){

            $checkoutTarget = $this->checkoutToAsset($consumable);
            $request->request->add(['assigned_asset' => $checkoutTarget->id]);
            session()->put(['redirect_option' => $request->get('redirect_option'), 'checkout_to_type' => 'asset']);

        } elseif ($request->filled('assigned_to')) {
            $checkoutTarget = $this->checkoutToUser($consumable);
            $request->request->add(['assigned_user' => $checkoutTarget->id]);
            session()->put(['redirect_option' => $request->get('redirect_option'), 'checkout_to_type' => 'user']);
        }

        if (isset($checkoutTarget)) {
            return Helper::getRedirectOption($request, $consumable->id, 'Consumables')
                ->with('success', trans('admin/consumables/message.checkout.success'));
        }

        return Helper::getRedirectOption($request, $consumable->id, 'Consumables')
            ->with('error', trans('admin/consumables/message.checkout.error'));
    }

    protected function findConsumableToCheckout($consumable, $consumableId)
    {
        $consumable = ConsumableAssignment::find($consumableId);

        if (! $consumable) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.checkout.unavailable')));    
        }
        return $consumable;
    }

    protected function checkoutToUser($consumable)
    {
        dd($consumable);
        //Check if the user exists
        if (is_null($target = User::find(request('assigned_to')))) {
            // Redirect to the consumable management page with error
            return redirect()->route('consumables.checkout.show', $consumable)->with('error', trans('admin/consumables/message.checkout.user_does_not_exist'))->withInput();
        }
        $consumable->assigned_to = request('assigned_to');
        
        if ($consumable->save()) {
            event(new CheckoutableCheckedOut($consumable, $target, auth()->user(), request('notes')));
            return $target;
        }

    
        return false;
    }

    protected function checkoutToAsset($consumable)
    {
        if (is_null($target = Asset::find(request('asset_id')))) {
            return redirect()->route('consumable.index')->with('error', trans('admin/consumable/message.asset_does_not_exist'));
        }
        $consumable->asset_id = request('asset_id');

        // Override asset's assigned user if available
        if ($target->checkedOutToUser()) {
            $consumable->assigned_to = $target->assigned_to;
        }
        if ($consumable->save()) {
            event(new CheckoutableCheckedOut($consumable, $target, auth()->user(), request('notes')));
            return $target;
        }

        return false;
    }
}
