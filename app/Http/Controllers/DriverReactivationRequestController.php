<?php

namespace App\Http\Controllers;

use App\DataTables\DriverReactivationRequestDataTable;
use App\Models\DriverReactivationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverReactivationRequestController extends Controller
{
    /**
     * Display a listing of driver reactivation requests.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(DriverReactivationRequestDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title', ['form' => __('message.driver_reactivation_request')]);
        $auth_user = authSession();
        $assets = ['datatable'];

        return $dataTable->render('global.driver-reactivation-request-datatable', compact('assets', 'pageTitle', 'auth_user'));
    }

    /**
     * Resolve a reactivation request: reactivate the driver, permanently
     * delete the account, or leave it deactivated without further action.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function resolve(Request $request, $id)
    {
        if (env('APP_DEMO')) {
            $message = __('message.demo_permission_denied');
            if ($request->ajax()) {
                return response()->json(['status' => true, 'message' => $message]);
            }
            return redirect()->route('driver-reactivation-request.index')->withErrors($message);
        }

        $request->validate([
            'action' => 'required|in:reactivate,delete_permanently,leave_deactivated',
        ]);

        $reactivationRequest = DriverReactivationRequest::findOrFail($id);

        // A request can only be actioned once. Without this, a stale page or
        // double click could e.g. permanently delete a driver that was just
        // reactivated.
        if ($reactivationRequest->status !== 'pending') {
            $message = __('message.reactivation_request_already_resolved');
            if ($request->ajax()) {
                return response()->json(['status' => false, 'message' => $message]);
            }
            return redirect()->route('driver-reactivation-request.index')->withErrors($message);
        }

        $driver = $reactivationRequest->driver;

        switch ($request->action) {
            case 'reactivate':
                if ($driver) {
                    $driver->restore();
                }
                $reactivationRequest->status = 'reactivated';
                break;

            case 'delete_permanently':
                if ($driver) {
                    if ($driver->stripe_customer_id) {
                        $stripeResponse = deleteStripeCustomer($driver->stripe_customer_id);
                        if (isset($stripeResponse['error'])) {
                            Log::warning('Failed to delete Stripe customer during permanent driver deletion', [
                                'user_id' => $driver->id,
                                'error' => $stripeResponse['error'],
                            ]);
                        }
                    }
                    if ($driver->uid) {
                        app('firebase.firestore')->database()->collection('users')->document($driver->uid)->delete();
                    }
                    $driver->forceDelete();
                }
                $reactivationRequest->status = 'permanently_deleted';
                break;

            case 'leave_deactivated':
                $reactivationRequest->status = 'left_deactivated';
                break;
        }

        $reactivationRequest->resolved_by = auth()->id();
        $reactivationRequest->resolved_at = now();
        $reactivationRequest->save();

        $message = __('message.update_form', ['form' => __('message.driver_reactivation_request')]);

        if ($request->ajax()) {
            return response()->json(['status' => true, 'message' => $message]);
        }

        return redirect()->route('driver-reactivation-request.index')->withSuccess($message);
    }
}
