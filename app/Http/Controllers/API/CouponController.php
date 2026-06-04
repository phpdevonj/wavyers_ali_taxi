<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Http\Resources\CouponResource;
use App\Models\User;
use App\Models\RideRequest;

class CouponController extends Controller
{
    public function getList(Request $request)
    {
        $riderId = request('rider_id');

        $rider = User::find($riderId);

        // Total completed rides
        $completedRides = RideRequest::where('rider_id', $riderId)
            ->where('status', 'completed')
            ->count();

        // Used coupon codes
        $usedCoupons = RideRequest::where('rider_id', $riderId)
            ->where('status', 'completed')
            ->whereNotNull('coupon_code')
            ->pluck('coupon_code')
            ->toArray();

        $coupon = Coupon::where('end_date','>=',now())->where('status',1)->where('is_visible', 1);

        // New User Coupon Filter
        $coupon->where(function ($q) use ($rider, $completedRides, $usedCoupons) {

            // Non new_user coupons
            $q->where('coupon_type','!=','new_user')

            // new_user coupons
            ->orWhere(function ($query) use ($rider, $completedRides, $usedCoupons) {

                $query->where('coupon_type','new_user');

                if ($rider) {
                    $query->where('start_date','<=',$rider->created_at);
                }

                if (!empty($completedRides)) {
                    $query->where('usage_limit_per_rider','>',$completedRides);
                }

                if (!empty($usedCoupons)) {
                    $query->whereNotIn('code',$usedCoupons);
                }
            });
        });

        $coupon->when(request('region_id'), function ($q) {
            return $q->orWhereIn('region_ids', request('region_id'));
        });

        $coupon->when(request('region_id'), function ($q) {
            return $q->orWhereIn('service_ids', request('service_id'));
        });

        $coupon->when(request('code'), function ($q) {
            return $q->where('code', 'LIKE', '%' . request('code') . '%');
        });

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $coupon->count();
            }
        }

        $coupon = $coupon->orderBy('title','asc')->paginate($per_page);
        
        $items = CouponResource::collection($coupon);

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];
        
        return json_custom_response($response);
    }
}