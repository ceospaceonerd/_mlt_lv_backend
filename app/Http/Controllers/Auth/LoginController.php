<?php

namespace App\Http\Controllers\Auth;

use App\Facades\UtilityFacades;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {


        $this->middleware('guest')->except('logout');
    }

    public function login(Request $request)
    {
        $central_domain = config('tenancy.central_domains')[0];
        $current_domain = tenant('domains');

        if (!empty($current_domain)) {
            $current_domain = $current_domain->pluck('domain')->toArray()[0];
        }

        $user = User::where('email', $request->email)->first();

        if (!empty($user)) {
            if ($user->type == 'Super Admin' && empty($user->tenant_id)) {
                if ($this->attemptLogin($request)) {
                    // return $this->sendLoginResponse($request);
                    return response()->json(['Status'=>'Success','message'=>'LoginData','response'=>$this->sendLoginResponse($request)],200);

                } else {
                    // return redirect()->back()->with('errors', __('Invalid username or password'));
                    return response()->json(['Status'=>'Error','message'=>'Invalid username or password','redirect'=>'back()'],401);
                }
            } elseif (!empty($current_domain) && !empty($user->tenant_id)) {
                $user_admin = tenancy()->central(function ($tenant) {
                    // return User::where('tenant_id', $tenant->id)->where('type', 'Admin')->first();
                    return response()->json(['Status'=>'Success','message'=>'loginData','response'=>User::where('tenant_id', $tenant->id)->where('type', 'Admin')->first()],200);
                });
                if ($user_admin->plan_id != '1' && !empty($user_admin->plan_expired_date) && Carbon::now() >= $user_admin->plan_expired_date) {

                    $user_admin->assignPlan(1);
                }

                $users = User::where('email', $request->email)->first();

                if ($users->active_status == 1) {
                    if ($this->attemptLogin($request)) {
                        // return $this->sendLoginResponse($request);
                        return response()->json(['Status'=>'Success','message'=>'LoginData','response'=>$this->sendLoginResponse($request)], 200);
                    } else {
                        // return redirect()->back()->with('errors', __('Invalid username or password'));
                        return response()->json(['Status'=>'Error','message'=>'Invalid username or password','redirect'=>'back()'], 401);
                    }
                } else {
                    // return redirect()->back()->with('errors', __('Please Contact to administrator'));
                    return response()->json(['Status'=>'Error','message'=>'Please Contact to administrator','redirect'=>'back()'], 401);
                }
            } else {

                // return redirect()->back()->with('errors', __('permission denied'));
                return response()->json(['Status'=>'Error','message'=>'permission denied','redirect'=>'back()'], 401);
            }
        } else {
            // return redirect()->back()->with('errors', __('user not found'));
            return response()->json(['Status'=>'Error','message'=>'user not found','redirect'=>'back()'], 401);
        }
    }
}
