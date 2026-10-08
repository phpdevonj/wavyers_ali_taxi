<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'username','country_code', 'contact_number', 'gender', 'email_verified_at', 'address', 'user_type', 'player_id', 'fcm_token', 'fleet_id', 'latitude', 'longitude', 'last_notification_seen', 'status', 'is_online', 'is_available', 'uid', 'login_type', 'display_name', 'timezone', 'service_id', 'is_verified_driver', 'last_location_update_at', 'otp_verify_at','last_actived_at','app_version', 'referral_code', 'referred_by', 'device_id', 'device_type', 'date_of_birth', 'license_number', 'license_expiration_date', 'social_security_number','stripe_customer_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_available'  => 'integer',
        'service_id'        => 'integer',
        'fleet_id'          => 'integer',
        'is_verified_driver'=> 'integer',
        'is_online'         => 'integer',
        'last_location_update_at'   => 'datetime',
        'otp_verify_at'     => 'datetime',
    ];

    public function userDetail() {
        return $this->hasOne(UserDetail::class, 'user_id', 'id');
    }

    public function userBankAccount() {
        return $this->hasOne(UserBankAccount::class, 'user_id', 'id');
    }

    public function fleet() {
        return $this->belongsTo(User::class, 'fleet_id', 'id');
    }

    public function userWallet() {
        return $this->hasOne(Wallet::class, 'user_id', 'id');
    }

    public function userPoint(){
        return $this->hasOne(Point::class, 'user_id', 'id');
    }

    public function userAddresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }

    public function scopeAdmin($query) {
        return $query->where('user_type', 'admin')->first();
    }

    public function scopeGetUser($query, $user_type=null)
    {
        $auth_user = auth()->user();

        if( $auth_user->hasAnyRole(getActiveAdminsRoles()) ) {
            $query->where('user_type', $user_type)->where('status','active');
            return $query;
        }
        if( $auth_user->hasRole('fleet') ) {
            return $query->where('user_type', 'driver')->where('fleet_id', $auth_user->id);
        }
    }

    public function riderRideRequestDetail() {
        return $this->hasMany(RideRequest::class, 'rider_id', 'id');
    }

    public function driverRideRequestDetail() {
        return $this->hasMany(RideRequest::class, 'driver_id', 'id');
    }

    public function driverDocument(){
        return $this->hasMany(DriverDocument::class, 'driver_id', 'id');
    }

    public function service() {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function riderRating(){
        return $this->hasMany(RideRequestRating::class, 'rider_id', 'id');
    }

    public function driverRating(){
        return $this->hasMany(RideRequestRating::class, 'driver_id', 'id');
    }

    public function routeNotificationForOneSignal()
    {
        return $this->player_id;
    }

    public function routeNotificationForFcm($notification)
    {
        return $this->fcm_token;
    }

    public function userWithdraw(){
        return $this->hasMany(WithdrawRequest::class, 'user_id', 'id');
    }

    public function bids()
    {
        return $this->hasMany(RideRequestBid::class, 'driver_id');
    }
    protected static function boot(){
        parent::boot();
        static::deleted(function ($row) {
            // Only clean up related records on a permanent (force) delete.
            // A soft delete (account deactivation) must leave everything intact.
            if (! $row->isForceDeleting()) {
                return;
            }

            // user_details has no DB-level foreign key, so it must be cleaned up
            // here. Ride history, payments, wallet, withdraw requests, etc. are
            // intentionally left alone: their foreign keys are ON DELETE SET NULL
            // (not cascade), so those records survive a permanent delete for
            // legal, tax and safety purposes. userBankAccount/driverDocument/
            // userAddresses/referrals already cascade-delete at the DB level.
            $row->userDetail()->delete();
        });
    }

    /**
     * Ride statuses in which a rider and driver are actively together. An
     * account cannot be deactivated while one of these is open.
     */
    const IN_FLIGHT_RIDE_STATUSES = ['accepted', 'arriving', 'arrived', 'in_progress'];

    /**
     * Digits-only form of a phone number, so "+1 555-0002" and "15550002" compare equal.
     */
    public static function normalizeContactNumber($number): string
    {
        return preg_replace('/\D+/', '', (string) $number);
    }

    /**
     * Find a soft-deleted (deactivated) driver by contact number. Matches on
     * digits only; a number typed without its country code still matches when
     * it identifies exactly one deactivated account.
     */
    public static function findTrashedUserByContactNumber(string $contactNumber, string $userType)
    {
        $needle = static::normalizeContactNumber($contactNumber);
        if ($needle === '') {
            return null;
        }

        $sql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";

        $exact = static::onlyTrashed()->where('user_type', $userType)->whereRaw("$sql = ?", [$needle])->first();
        if ($exact) {
            return $exact;
        }

        $suffix = static::onlyTrashed()->where('user_type', $userType)->whereRaw("$sql LIKE ?", ['%' . $needle])->limit(2)->get();

        return $suffix->count() === 1 ? $suffix->first() : null;
    }

    /**
     * Find a deactivated driver by any identifier that is still held on the
     * (un-anonymized) row: contact number, email or username. Email and
     * username are unique in the database, so a returning driver using a
     * different phone must still be recognised instead of hitting a 500.
     */
    public static function findTrashedDriverByIdentity($contactNumber = null, $email = null, $username = null)
    {
        $driver = $contactNumber ? static::findTrashedUserByContactNumber((string) $contactNumber, 'driver') : null;
        if ($driver) {
            return $driver;
        }

        if (! $email && ! $username) {
            return null;
        }

        return static::onlyTrashed()->where('user_type', 'driver')->where(function ($q) use ($email, $username) {
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($username) {
                $q->orWhere('username', $username);
            }
        })->first();
    }

    /**
     * True when this account is part of a ride that is under way right now.
     */
    public function hasInFlightRide(): bool
    {
        $column = $this->user_type === 'driver' ? 'driver_id' : 'rider_id';

        return RideRequest::where($column, $this->id)->whereIn('status', self::IN_FLIGHT_RIDE_STATUSES)->exists();
    }

    /**
     * Clean up not-yet-started rides so they are not dispatched for, or to, an
     * account that is about to disappear from the app.
     *  - rider: open/scheduled rides are cancelled and held card payments released.
     *  - driver: scheduled rides assigned to them are un-assigned so the
     *    scheduler picks another driver.
     */
    public function releaseOpenRides(): void
    {
        if ($this->user_type === 'rider') {
            $rides = RideRequest::where('rider_id', $this->id)
                ->whereIn('status', ['new_ride_requested', 'bid_placed', 'pending', 'scheduled', 'driver_accepted'])
                ->get();

            foreach ($rides as $ride) {
                if ($ride->payment_type === 'card' && ! empty($ride->held_payment_intent_id)) {
                    try {
                        $refund = releaseOrRefundStripePayment($ride->held_payment_intent_id);
                        if (! empty($refund['success']) && ($payment = $ride->payment)) {
                            $payment->update(['payment_status' => 'refunded']);
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Failed to release held payment while deactivating rider', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
                    }
                }

                $ride->update([
                    'status' => 'canceled',
                    'cancel_by' => 'rider',
                    'reason' => 'Rider account was deleted',
                ]);
            }
        } elseif ($this->user_type === 'driver') {
            RideRequest::where('driver_id', $this->id)
                ->whereIn('status', ['scheduled', 'driver_accepted'])
                ->update(['driver_id' => null, 'status' => 'scheduled', 'riderequest_in_driver_id' => null]);

            RideRequest::where('riderequest_in_driver_id', $this->id)
                ->whereIn('status', ['new_ride_requested', 'scheduled'])
                ->update(['riderequest_in_driver_id' => null]);
        }
    }

    /**
     * Take the account out of service without erasing anything: revoke API
     * tokens, go offline/unavailable, release open rides, then soft delete.
     */
    public function deactivate()
    {
        $this->releaseOpenRides();
        $this->tokens()->delete();
        $this->forceFill(['is_online' => 0, 'is_available' => 0])->save();

        $this->delete();
    }

    /**
     * Deactivate this (rider) account: replace the identifiers that have a
     * DB-level unique constraint (or are personally identifying) so the same
     * phone/email/username can sign up again as a brand new, separate account.
     * The phone number is stored as a keyed hash (HMAC-SHA256 with the app key)
     * so it cannot be brute-forced back from the database alone. Ride history,
     * payments, wallet, etc. are left untouched and remain visible to admins.
     */
    public function deactivateAndAnonymize()
    {
        $this->releaseOpenRides();

        $this->fill([
            'contact_number' => $this->contact_number
                ? hash_hmac('sha256', $this->contact_number, config('app.key')) . '-' . $this->id
                : $this->contact_number,
            'email' => 'deleted-user-' . $this->id . '@deleted.invalid',
            'username' => 'deleted-user-' . $this->id,
        ]);
        $this->forceFill(['is_online' => 0, 'is_available' => 0])->save();

        $this->tokens()->delete();
        $this->delete();
    }

    public function reactivationRequests()
    {
        return $this->hasMany(DriverReactivationRequest::class, 'driver_id', 'id');
    }

    public function getPayment(){
        
        return $this->hasManyThrough( 
            Payment::class,
            RideRequest::class,
            'driver_id',
            'ride_request_id',
            'id',
            'id'
        )->where('payment_status','paid');
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }
    
    public function getReferralCount($status = 'complete')
    {
        return $this->referrals()
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->count();
    }

    public function getDriverScoreAttribute()
    {
        $driverId = $this->id;

        $data = DB::table('ride_requests')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN cancel_by = 'driver' THEN 1 ELSE 0 END) as cancelled,
                    (
                        SELECT COUNT(*) 
                        FROM ride_requests AS r2 
                        WHERE JSON_CONTAINS(r2.cancelled_driver_ids, ?)
                    ) as unaccepted
                ", ["$driverId"])
                ->where('driver_id', $driverId)
                ->first();

        $total = $data->total ?? 0;
        $cancelled = $data->cancelled ?? 0;
        $unaccepted = $data->unaccepted ?? 0;

        if ($total === 0) {
            return 100; // full score if no rides yet
        }

        // Scoring formula: 100 - % of failed rides
        $score = 100 - (($cancelled + $unaccepted) / $total * 100);
        return round($score, 2);
    }


    public function completedTripsAsRiderCount()
    {
        return RideRequest::where('rider_id', $this->id)
                        ->where('status', 'completed')
                        ->count();
    }

    public function completedTripsAsDriverCount()
    {
        return RideRequest::where('driver_id', $this->id)
                        ->where('status', 'completed')
                        ->count();
    }
}
