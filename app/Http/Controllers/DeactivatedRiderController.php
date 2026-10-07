<?php

namespace App\Http\Controllers;

use App\DataTables\DeactivatedRiderDataTable;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DeactivatedRiderController extends Controller
{
    /**
     * Display a listing of deactivated (soft-deleted) rider accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(DeactivatedRiderDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title', ['form' => __('message.deactivated_rider')]);
        $auth_user = authSession();
        $assets = ['datatable'];

        return $dataTable->render('global.deactivated-rider-datatable', compact('assets', 'pageTitle', 'auth_user'));
    }

    /**
     * Permanently delete a deactivated rider account.
     *
     * Ride history, payments, wallet, etc. survive this: their foreign keys
     * are ON DELETE SET NULL (not cascade), so those records remain for
     * legal, tax and safety purposes - only the user row itself is erased.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (env('APP_DEMO')) {
            $message = __('message.demo_permission_denied');
            if (request()->ajax()) {
                return response()->json(['status' => true, 'message' => $message]);
            }
            return redirect()->route('deactivated-rider.index')->withErrors($message);
        }

        $user = User::onlyTrashed()->where('user_type', 'rider')->findOrFail($id);

        if ($user->stripe_customer_id) {
            $stripeResponse = deleteStripeCustomer($user->stripe_customer_id);
            if (isset($stripeResponse['error'])) {
                Log::warning('Failed to delete Stripe customer during permanent rider deletion', [
                    'user_id' => $user->id,
                    'error' => $stripeResponse['error'],
                ]);
            }
        }

        if ($user->uid) {
            app('firebase.firestore')->database()->collection('users')->document($user->uid)->delete();
        }

        $user->forceDelete();

        $message = __('message.delete_form', ['form' => __('message.deactivated_rider')]);

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }
}
