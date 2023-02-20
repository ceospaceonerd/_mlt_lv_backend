<?php

namespace App\Http\Controllers;

use App\DataTables\RolesDataTable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Module;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{

    public function index(RolesDataTable $dataTable)
    {
        if (\Auth::user()->can('manage-role')) {
            // return $dataTable->render('roles.index');
            return response()->json(['Status'=>'Success','message'=>'','redirect'=>'roles.index'],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);

        }
    }

    public function create()
    {
        if (\Auth::user()->can('create-role')) {
            $permission = Permission::get();
            // return view('roles.create', compact('permission'));
            return response()->json(['Status'=>'Success','message'=>'','redirect'=>'roles.create','permission'=>$permission],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create-role')) {
            request()->validate([
                'name' => 'required',
            ]);
            Role::create(['name' => $request->input('name'), 'tenant_id' => tenant('id')]);
            // return redirect()->route('roles.index')->with('success', __('Role Added successfully.'));
            return response()->json(['Status'=>'Success','message'=>'Role Added successfully.','redirect'=>'roles.index'],201);
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function show($id)
    {
        $role = Role::find($id);
        if ($role->tenant_id == tenant('id')) {
            if ($id == 1) {
                $permissions = $role->permissions->pluck('name', 'id')->toArray();
                $allpermissions = Permission::all()->pluck('name', 'id')->toArray();
            } else {

                $permissions = DB::table("role_has_permissions")
                    ->select(['role_has_permissions.*', 'permissions.name'])
                    ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where("role_has_permissions.role_id", $id)
                    ->pluck('permissions.name', 'role_has_permissions.permission_id')
                    ->toArray();
                $allpermissions = \Auth::user()->roles->first()->permissions->pluck('name', 'id')->toArray();
            }
            $allmodules = Module::all()->pluck('name', 'id')->toArray();
            // return view('roles.show')
            //     ->with('role', $role)
            //     ->with('permissions', $permissions)
            //     ->with('allpermissions', $allpermissions)
            //     ->with('allmodules', $allmodules);
            return response()->json(['Status'=>'Success','message'=>'rolesInfo'
            ,'role'=> $role
            ,'permissions'=> $permissions
            ,'allpermissions'=> $allpermissions
            ,'allmodules'=> $allmodules
            ,'redirect'=>'roles.show'],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function edit($id)
    {
        if (\Auth::user()->can('edit-role')) {
            $role = Role::find($id);

            if ($role->tenant_id == tenant('id')) {

                $permission = Permission::get();
                $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", $id)
                    ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
                    ->all();
                // return view('roles.edit', compact('role', 'permission', 'rolePermissions'));
                return response()->json(['Status'=>'Success','message'=>''
                ,'role'=>$role
                ,'permission'=>$permission
                ,'rolePermissions'=>$rolePermissions
                ,'redirect'=>''],200);

            } else {
                return redirect()->back()->with('failed', __('Permission Denied.'));
            }
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit-role')) {
            $role = Role::find($id);
            $role->name = $request->input('name');
            $role->save();
            $role->syncPermissions($request->input('permission'));
            // return redirect()->route('roles.index')->with('success', __('Role updated successfully'));
            return response()->json(['Status'=>'Success','message'=>'Role updated successfully','redirect'=>'roles.index'],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete-role')) {
            $role = Role::find($id);
            if ($role->id != 1) {
                $role->delete();
            } else {
                // return redirect()->back()->with('failed', __('Permission Denied.'));
                return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
            }
            // return redirect()->route('roles.index')->with('success', 'Role deleted successfully');
            return response()->json(['Status'=>'Success','message'=>'Role deleted successfully','redirect'=>'roles.index'],200);

        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function assignPermission(Request $request, $id)
    {

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::find($id);
        $permissions = $role->permissions()->get();
        $role->revokePermissionTo($permissions);
        $role->givePermissionTo($request->permissions);
        // return redirect()->route('roles.index')->with('success', 'Permissions assigned to Role successfully');
        return response()->json(['Status'=>'Success','message'=>'Permissions assigned to Role successfully','redirect'=>'roles.index'],200);
    }
}
