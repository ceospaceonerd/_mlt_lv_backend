<?php

namespace App\Http\Controllers;

use App\DataTables\UsersDataTable;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\RequestDomain;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\Role;
use App\Models\Tenant;
use DB;
use Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Features\UserImpersonation;


class UserController extends Controller
{
    public function index(UsersDataTable $dataTable)
    {
        if (\Auth::user()->can('manage-user')) {
            // return $dataTable->render('users.index');
            return response()->json(['Status'=>'Success','message'=>'','redirect'=>'users.index'],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],400);
        }
    }

    public function create()
    {
        if (\Auth::user()->can('create-user')) {
            if (Auth::user()->type == 'Super Admin') {
                $roles = Role::pluck('name', 'name');
                $domains = Domain::pluck('domain', 'domain')->all();
            } else if (Auth::user()->type == 'Admin') {
                $roles = Role::where('name', '!=', 'Super Admin')->where('name', '!=', 'Admin')->where('tenant_id', tenant('id'))->pluck('name', 'name');
                $domains = Domain::pluck('domain', 'domain')->all();
            } else {
                $roles = Role::where('name', '!=', 'Super Admin')->where('name', '!=', 'Admin')->where('name', Auth::user()->type)->where('tenant_id', tenant('id'))->pluck('name', 'name');
                $domains = Domain::pluck('domain', 'domain')->all();
            }
            // return view('users.create', compact('roles', 'domains'));
            return response()->json(['Status'=>'Success','message'=>'','roles'=>$roles, 'domains'=>$domains,'redirect'=>'users.create'],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create-user')) {
            if (\Auth::user()->type == 'Super Admin') {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'email' => 'required|email|unique:users,email,',
                        'password' => 'same:confirm-password',
                        'domains' => 'required|unique:domains,domain',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();
                    // return redirect()->back()->with('errors', $messages->first());
                    return response()->json(['Status'=>'Error','message'=> $messages->first(),'redirect'=>'back()'],400);

                }
                $input = $request->all();
                $input['password'] = Hash::make($input['password']);
                $input['type'] = 'Admin';
                $input['plan_id'] = 1;
                $input['created_by'] = Auth::user()->id;
                $user = User::create($input);
                $user->assignRole('Admin');
                if (tenant('id') == null) {
                    try {
                        $tenant = Tenant::create([
                            'id' => $user->id,
                            'tenancy_db_name' => $request->db_name,
                            'tenancy_db_username' => $request->db_username,
                            'tenancy_db_password' => $request->db_password,
                        ]);
                        $domain = Domain::create([
                            'domain' => $request->domains,
                            'tenant_id' => $tenant->id,
                        ]);
                        $user->tenant_id = $tenant->id;
                        $user->created_by = Auth::user()->id;
                        $user->save();
                    } catch (\Exception $e) {
                        // return redirect()->back()->with('errors', $e->getMessage());
                        return response()->json(['Status'=>'Error','message'=>$e->getMessage(),'redirect'=>'back()'],401);
                    }
                }
            } elseif (\Auth::user()->type == 'Admin') {
                $this->validate($request, [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email,',
                    'password' => 'same:confirm-password',

                    'roles' => 'required',
                ]);
                $input = $request->all();
                $input['password'] = Hash::make($input['password']);
                $input['type'] = $input['roles'];
                $input['created_by'] = Auth::user()->id;
                $input['plan_id'] = 1;
                $user = User::create($input);
                $user->assignRole($request->input('roles'));
                $user->update();
            } else {
                $this->validate($request, [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email,',
                    'password' => 'same:confirm-password',
                    'roles' => 'required',
                ]);
                $users = User::where('tenant_id', tenant('id'))->where('created_by', Auth::user()->id)->count();
                $usr = Auth::user();
                $user = user::where('email', $usr->email)->first();
                $plan = Plan::find($user->plan_id);
                if ($users < $plan->max_users) {
                    $input = $request->all();
                    $input['password'] = Hash::make($input['password']);
                    $input['type'] = $input['roles'];
                    $input['created_by'] = Auth::user()->id;
                    $user = User::create($input);
                    $user->assignRole($request->input('roles'));
                    $user->update();
                } else {
                    // return redirect()->back()->with('failed', __('Your user limit is over, Please upgrade plan.'));
                    return response()->json(['Status'=>'Error','message'=>'Your user limit is over, Please upgrade plan.','redirect'=>'back()'],401);
                }
            }
            // return redirect()->route('users.index')->with('success', __('User created successfully'));
            return response()->json(['Status'=>'Success','message'=>'User created successfully','redirect'=>'users.index'],200);
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'','redirect'=>''],401);
        }
    }

    public function show($id)
    {
        if (\Auth::user()->can('show-user')) {
            $user = User::find($id);
            // return view('users.show', compact('user'));
            return response()->json(['Status'=>'Success','message'=>'','user'=>$user,'redirect'=>'users.show'],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function edit($id)
    {
        if (\Auth::user()->can('edit-user')) {
            $user = User::find($id);
            if (Auth::user()->type == 'Super Admin') {
                $roles = Role::pluck('name', 'name');
                $domains = Domain::pluck('domain', 'domain')->all();
            } else {
                $roles = Role::where('name', '!=', 'Super Admin')->where('name', '!=', 'Admin')->where('tenant_id', tenant('id'))->pluck('name', 'name');
                $domains = Domain::pluck('domain', 'domain')->all();
            }
            $domains = Domain::pluck('domain', 'domain')->all();
            $user_domain = Domain::where('tenant_id', $user->tenant_id)->first();
            $userRole = $user->roles->pluck('name', 'name')->all();
            // return view('users.edit', compact('user', 'roles', 'domains', 'user_domain', 'userRole'));
            return response()->json(['Status'=>'Success','message'=>'',
            'user'=>$user,
            'roles'=>$roles,
            'domains'=>$domains,
            'user_domain'=>$user_domain,
            'userRole'=>$userRole,
            'redirect'=>'users.edit']
            ,200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit-user')) {
            if (\Auth::user()->type == 'Super Admin') {
                $this->validate($request, [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email,' . $id,
                    'domains' => 'required',
                ]);
                $input = $request->all();
                $user = User::find($id);
                $user->update($input);
                $domain = Domain::where('tenant_id', $user->tenant_id)->first();
                if ($request->domains != $domain->domain) {
                    $check = Domain::where('domain', $request->domains)->first();
                    if (!$check) {
                        $domain->domain = $request->domains;
                        $domain->save();
                    }
                }
            } else {
                $this->validate($request, [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email,' . $id,
                    'roles' => 'required',
                ]);
                $input = $request->all();
                $input['type'] = $input['roles'];
                $user = User::find($id);
                $current_date = Carbon::now();
                $newEndingDate = date("Y-m-d", strtotime(date("Y-m-d", strtotime($user->created_at)) . " + 1 year"));
                if ($current_date <= $newEndingDate) {
                }
                $user->update($input);
                DB::table('model_has_roles')->where('model_id', $id)->delete();
                $user->assignRole($request->input('roles'));
            }
            // return redirect()->route('users.index')->with('success', __('User updated successfully'));
            return response()->json(['Status'=>'Success','message'=>'User updated successfully','redirect'=>''],200);

        } else {
            return redirect()->back()->with('failed', __('Permission Denied.'));
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
            }
            if ($user->id != 1) {
                $user->delete();
            }
            return redirect()->route('users.index')->with('success', __('User deleted successfully'));
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function impersonate($id)
    {
        // dd(\Auth::user());
        if (\Auth::user()->can('impersonate-user')) {
            $user = User::find($id);
            $current_domain = $user->tenant->domains->first()->domain;
            $redirectUrl = '/';
            $token = tenancy()->impersonate($user->tenant, $id, $redirectUrl);
            // dd($token->token);
            // return redirect("http://$current_domain/impersonate/{$token->token}");
            // return response()->json(['Status'=>'Error','message'=>'',],401);
            return response()->json(['Status'=>'Success','message'=>'','redirect'=>'http://'.$current_domain.'/impersonate/'.$token->token],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],400);
        }
    }
}
