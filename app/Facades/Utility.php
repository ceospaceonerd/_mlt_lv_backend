<?php

namespace App\Facades;

use App\Mail\ApproveMail;
use App\Models\Order;
use App\Models\Plan;
use App\Models\RequestDomain;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Database\Models\Domain;


class Utility
{

    public function settings()
    {
        $data = DB::table('settings');
        $data = $data->get();

        $settings = [
            'date_format' => 'M j, Y',
            'time_format' => 'g:i A',
        ];

        foreach ($data as $row) {
            $settings[$row->key] = $row->value;
        }

        return $settings;
    }

    public function date_format($date)
    {
        return Carbon::parse($date)->format($this->getsettings('date_format'));
        // return response()->json(['Status'=>'Success','message'=>'','date_format'=>Carbon::parse($date)->format($this->getsettings('date_format'))],200);
    }

    public function time_format($date)
    {
        return Carbon::parse($date)->format($this->getsettings('time_format'));
        // return response()->json(['Status'=>'Success','message'=>'','time_format'=>Carbon::parse($date)->format($this->getsettings('time_format'))],200);

    }

    public function date_time_format($date)
    {
        // return Carbon::parse($date)->format($this->getsettings('date_format') . ' ' . $this->getsettings('time_format'));
        return response()->json(['Status'=>'Success','message'=>'','date_time_format'=>Carbon::parse($date)->format($this->getsettings('date_format') . ' ' . $this->getsettings('time_format'))],200);
    }

    public function setEnvironmentValue(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {
                $keyPosition = strpos($str, "{$envKey}=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);
                // If key does not exist, add it
                if (!$keyPosition || !$endOfLinePosition || !$oldLine) {
                    $str .= "{$envKey}='{$envValue}'\n";
                } else {
                    $str = str_replace($oldLine, "{$envKey}='{$envValue}'", $str);
                }
            }
        }
        $str = substr($str, 0, -1);
        $str .= "\n";
        if (!file_put_contents($envFile, $str)) {
            return false;
        }

        return true;
    }

    public function getValByName($key)
    {
        $setting = $this->settings();
        if (!isset($setting[$key]) || empty($setting[$key])) {
            $setting[$key] = '';
        }

        return $setting[$key];
    }

    public function languages()
    {
        $dir = base_path() . '/resources/lang/';
        $glob = glob($dir . '*', GLOB_ONLYDIR);
        $arrLang = array_map(
            function ($value) use ($dir) {
                return str_replace($dir, '', $value);
            },
            $glob
        );
        $arrLang = array_map(
            function ($value) use ($dir) {
                return preg_replace('/[0-9]+/', '', $value);
            },
            $arrLang
        );
        $arrLang = array_filter($arrLang);
        return $arrLang;
    }

    public function delete_directory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!self::delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }
    public function getsettings($value = '')
    {
        $setting = Setting::select('value');
        if (!empty(tenant('id'))) {
            $setting->where('tenant_id', tenant('id'));
        } else {
            $setting->whereNull('tenant_id');
        }
        $set =  $setting->where('key', $value)->first();
        $val = '';
        if (!empty($set->value)) {

            $val = $set->value;
        }
        // return $val;
        return response()->json(['Status'=>'Success','message'=>'','getsettings'=>$val],200);
    }

    public function storesettings($formatted_array)
    {
        if (tenant('id') == null) {
            $row = Setting::where('key', $formatted_array['key'])->whereNull('tenant_id')->first();
        } else {
            $row = Setting::where('key', $formatted_array['key'])->where('tenant_id', tenant('id'))->first();
        }

        if (empty($row)) {
            Setting::create($formatted_array);
        } else {
            $row->update($formatted_array);
        }
        $affected_row = Setting::find($formatted_array['key']);
        return $affected_row;
    }
    public function getpath($name)
    {
        if (config('filesystems.default') == 'local'  && tenant('id') == null) {

            $src = $name ? Storage::url(tenant('id') . $name) : Storage::url('logo/app-logo.png');
        } elseif (config('filesystems.default') == 'local') {
            $src = $name ? Storage::url(tenant('id') . '/' . $name) : Storage::url('logo/app-logo.png');
        } else {
            $src = $name ? Storage::url($name) : Storage::url('logo/app-logo.png');
        }
        return $src;
    }

    public function approved_request($data, $database)
    {
        $req = RequestDomain::find($data);


        $data = Order::where('domainrequest_id', $req->id)->first();

        $input['name'] = $req->name;
        $input['email'] = $req->email;
        $input['password'] = $req->password;
        $input['type'] = 'Admin';
        $input['plan_id'] = 1;
        $user = User::create($input);
        $user->assignRole('Admin');
        if (tenant('id') == null && Utility::getsettings('database_permission') == 1) {
            try {

                $tenant = Tenant::create([
                    'id' => $user->id,
                ]);
                $domain = Domain::create([
                    'domain' => $req->domain_name,
                    'tenant_id' => $tenant->id,
                ]);

                $user->tenant_id = $tenant->id;
                $user->save();
            } catch (\Exception $e) {
                // return redirect()->back()->with('errors', $e->getMessage());
                return response()->json(['Status'=>'Error','message'=>$e->getMessage(),'redirect'=>'back()'],400);
            }
        } else {
        try {
            $tenant = Tenant::create([
                'id' => $user->id,
                'tenancy_db_name' => $database['db_name'],
                'tenancy_db_username' => $database['db_username'],
                'tenancy_db_password' => $database['db_password'],
            ]);
            $domain = Domain::create([
                'domain' => $req->domain_name,
                'tenant_id' => $tenant->id,
            ]);

            $user->tenant_id = $tenant->id;
            $user->save();
        } catch (\Exception $e) {
            // return redirect()->back()->with('errors', $e->getMessage());
            return response()->json(['Status'=>'Error','message'=>$e->getMessage(),'redirect'=>'back()'],400);
        }
        }
        $user = User::find($tenant->id);
        $plan = Plan::find($data['plan_id']);
        $user->plan_id = $plan->id;
        if ($plan->durationtype == 'Month' && $plan->id != '1') {
            $user->plan_expired_date = Carbon::now()->addMonths($plan->duration)->isoFormat('YYYY-MM-DD');
        } elseif ($plan->durationtype == 'Year' && $plan->id != '1') {
            $user->plan_expired_date = Carbon::now()->addYears($plan->duration)->isoFormat('YYYY-MM-DD');
        } else {
            $user->plan_expired_date = null;
        }
        $user->save();
        $req->is_approved = 1;
        $req->save();
        $data->user_id = $user->id;
        $data->save();
        try {
            Mail::to($req->email)->send(new ApproveMail($req));
        } catch (\Exception $e) {
            // return redirect()->route('requestdomain.index')->with('errors', $e->getMessage());
            return response()->json(['Status'=>'Error','message'=>$e->getMessage(),'redirect'=>'requestdomain.index'],400);
        }
    }
}
