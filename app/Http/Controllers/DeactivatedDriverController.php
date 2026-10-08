<?php

namespace App\Http\Controllers;

use App\DataTables\DeactivatedDriverDataTable;
use App\Models\DriverReactivationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DeactivatedDriverController extends Controller
{
    /**
     * Display a listing of deactivated (soft-deleted) driver accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(DeactivatedDriverDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title', ['form' => __('message.deactivated_driver')]);
        $auth_user = authSession();
        $assets = ['datatable'];

        return $dataTable->render('global.deactivated-driver-datatable', compact('assets', 'pageTitle', 'auth_user'));
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
            return redirect()->route('deactivated-driver.index')->withErrors($message);
        }

        $user = User::onlyTrashed()->where('user_type', 'driver')->findOrFail($id);

        if ($user->stripe_customer_id) {
            $stripeResponse = deleteStripeCustomer($user->stripe_customer_id);
            if (isset($stripeResponse['error'])) {
                Log::warning('Failed to delete Stripe customer during permanent driver deletion', [
                    'user_id' => $user->id,
                    'error' => $stripeResponse['error'],
                ]);
            }
        }

        if ($user->uid) {
            app('firebase.firestore')->database()->collection('users')->document($user->uid)->delete();
        }

        $user->forceDelete();

        // Close any reactivation requests still waiting on this driver.
        DriverReactivationRequest::where('driver_id', $id)->where('status', 'pending')->update([
            'status' => 'permanently_deleted',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        $message = __('message.delete_form', ['form' => __('message.deactivated_driver')]);

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }
}
