<?php

namespace App\Http\Controllers;

use App\Facades\UtilityFacades;
use App\Models\RequestDomain;
use Illuminate\Http\Request;
use App\Models\User;
use Auth;
use File;
use Hash;
use Stancl\Tenancy\Database\Models\Domain;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index()
    {
        if (!UtilityFacades::getsettings('2fa')) {
            $user = auth()->user();
            $role = $user->roles->first();
            $tenant_id = tenant('id');
            return view('profile.index', [
                'user' => $user,
                'role' => $role,
                'tenant_id' => $tenant_id
            ]);
        }
        // return $this->activeTwoFactor();
        return response()->json(['Status'=>'Success','2fa'=>$this->activeTwoFactor()],200);
    }

    private function activeTwoFactor()
    {
        $user = Auth::user();
        $google2fa_url = "";
        $secret_key = "";
        if ($user->loginSecurity()->exists()) {
            $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());
            $google2fa_url = $google2fa->getQRCodeInline(
                @UtilityFacades::getsettings('app_name'),
                $user->name,
                $user->loginSecurity->google2fa_secret
            );
            $secret_key = $user->loginSecurity->google2fa_secret;
        }
        $user = auth()->user();
        $role = $user->roles->first();
        $tenant_id = tenant('id');
        $data = array(
            'user' => $user,
            'secret' => $secret_key,
            'google2fa_url' => $google2fa_url,
            'tenant_id' => $tenant_id
        );
        // return view('profile.index', [
            // 'user' => $user,
            // 'role' => $role,
            // 'secret' => $secret_key,
            // 'google2fa_url' => $google2fa_url,
            // 'tenant_id' => $tenant_id
        // ]);
        return response()->json(['Status'=>'Success','message'=>'active 2FA','redirect'=>'profile.index','user' => $user,
        'role' => $role,
        'secret' => $secret_key,
        'google2fa_url' => $google2fa_url,
        'tenant_id' => $tenant_id],200);
    }

    public function updateLogin(Request $request)
    {
        $userDetail = Auth::user();
        $user       = User::findOrFail($userDetail['id']);
        $validator = \Validator::make(
            $request->all(),
            [
                'email' => 'required|email|unique:users,email,' . $userDetail['id'],
                'avatar' => 'image|mimes:jpeg,png,jpg,svg|max:3072',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            // return redirect()->back()->with('error', $messages->first());
            return response()->json(['Status'=>'Error','message'=>$messages->first()],400);
        }
        if ($request->hasFile('avatar')) {
            $filenameWithExt = $request->file('avatar')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('avatar')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            $dir             = storage_path('avatar/');
            $image_path      = $dir . $userDetail['avatar'];

            if (File::exists($image_path)) {
                //File::delete($image_path);
            }
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $path = $request->file('avatar')->storeAs('avatar/', $fileNameToStore);
        }
        if (!empty($request->avatar)) {
            $user['avatar'] = 'avatar/' . $fileNameToStore;
        }
        if (!empty($request->password)) {
            $user->password = bcrypt($request->password);
        }
        $user['email'] = $request['email'];
        $user->save();
        // return redirect()->back()->with('success', __('avatar successfully updated.'));
        return response()->json(['Status'=>'Success','message'=>__('avatar successfully updated.')],200);
    }

    private function generateCode()
    {
        $google2fa = app('pragmarx.google2fa');
        $generated = $google2fa->getQRCodeInline(
            config('app.name'),
            auth()->user()->name,
            auth()->user()->google2fa->google2fa_secret
        );
        // return $generated;
        return response()->json(['Status'=>'Success','code'=>$generated],200);
    }

    public function activate()
    {
        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');
        $google2fa = $google2fa->generateSecretKey();
        TwoFactor::create([
            'user_id' => $user->id,
            'google2fa_enable' => 0,
            'google2fa_secret' => $google2fa
        ]);
        // return redirect()->back()->with('success', __('2-Factor Activated'));
        return response()->json(['Status'=>'Success','message'=>__('2-Factor Activated')],200);
    }

    public function enable(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
        ]);
        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');
        $verified = $google2fa->verifyKey($user->google2fa->google2fa_secret, $request->code);
        if ($verified) {
            $user->google2fa->google2fa_enable = 1;
            $user->google2fa->save();
            // return redirect()->back()->with('success', __('2-Factor Enabled'));
            return response()->json(['Status'=>'Success','message'=>__('2-Factor Enabled')],200);
        }
        // return redirect()->back()->with('fail', __('Verification Code is Invalid'));
        return response()->json(['Status'=>'Error','message'=>__('Verification Code is Invalid')],400);
    }

    public function disable(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
            'password' => 'required',
        ]);
        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');
        if (Hash::check($request->password, $user->password)) {
            $verified = $google2fa->verifyKey($user->google2fa->google2fa_secret, $request->code);
            if ($verified) {
                $user->google2fa->delete();
                // return redirect()->back()->with('success', '2-Factor Disabled');
                return response()->json(['Status'=>'Success','message'=>'2-Factor Disabled',],200);
            }
            // return redirect()->back()->with('fail', __('Verification Code is Invalid'));
            return response()->json(['Status'=>'Error','message'=>__('Verification Code is Invalid')],400);
        } else {
            // return redirect()->back()->with('fail', __('Invalid Password! Check Password and try again'));
            return response()->json(['Status'=>'Error','message'=>__('Invalid Password! Check Password and try again')],400);
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete-user')) {
            $user = User::find($id);
            if (Auth::user()->type == 'Super Admin') {
                $domain = Domain::where('tenant_id', $user->tenant_id)->first();
                $requestdomain = RequestDomain::where('email', $user->email)->first();
                if ($domain) {
                    $domain->delete();
                }
                if ($requestdomain) {
                    $requestdomain->delete();
                }
            } else {
                tenancy()->central(function ($tenant) {
                    $central_user = User::find($tenant->id);
                    $central_user->active_status = 0;
                    $central_user->save();
                });
                if ($user->type == 'Admin') {
                    $sub_users = User::where('type', '!=', 'Admin')->get();
                } else {
                    $sub_users = User::where('created_by', $user->id)->get();
                }
                foreach ($sub_users as $sub_user) {
                    if ($sub_user) {
                        $sub_user->active_status = 0;
                        $sub_user->save();
                    }
                }
                $user->delete();
                auth()->logout();
            }
            // return redirect()->route('users.index')->with('success', __('User deleted successfully'));
            return response()->json(['Status'=>'Success','message'=>__('User deleted successfully')],200);
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>__('Permission Denied.')],401);
        }
    }

    public function verify()
    {
        return redirect(URL()->previous());
    }

    public function instruction()
    {
        return view('google2fa.instruction');
    }

    public function profileStatus()
    {
        $user = tenancy()->central(function ($tenant) {
            $central_user= User::find($tenant->id);
            $central_user->active_status = 0;
            $central_user->save();
        });
        $user = User::find(Auth::user()->id);
        $user->active_status = 0;
        $user->save();
        auth()->logout();
        // return redirect()->route('home');
        return response()->json(['Status'=>'Success','redirect'=>'/'],200);
    }
}
