<?php

namespace App\Http\Controllers;

use App\Facades\UtilityFacades;
use App\Mail\TestMail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Str;

class SettingsController extends Controller
{
    public function index()
    {
        // return view('settings.index');
        return response()->json(['Status'=>'Success','message'=>'','redirect'=>'settings.index'],200);

    }

    public function appNameUpdate(Request $request)
    {
        $this->validate($request, [
            'app_logo' => 'nullable|image|max:2048|mimes:png',
            'app_small_logo' => 'nullable|image|max:2048|mimes:png',
            'app_dark_logo' =>'nullable|image|max:2048|mimes:png',
            'favicon_logo' => 'nullable|image|max:2048|mimes:png',
            'app_name' => 'required'
        ]);
        $data = [];
        if ($request->app_logo) {
            Storage::delete(UtilityFacades::getsettings('app_logo'));
            $app_logo_name = 'app-logo.' . $request->app_logo->extension();
            $request->app_logo->storeAs('logo', $app_logo_name);
            $data['app_logo'] = 'logo/' . $app_logo_name;
        }
        if ($request->app_dark_logo) {
            Storage::delete(UtilityFacades::getsettings('app_dark_logo'));
            $app_dark_logo_name = 'app-dark-logo.' . $request->app_dark_logo->extension();
            $request->app_dark_logo->storeAs('logo', $app_dark_logo_name);
            $data['app_dark_logo'] = 'logo/' . $app_dark_logo_name;
        }
        if ($request->app_small_logo) {

            Storage::delete(UtilityFacades::getsettings('app_small_logo'));
            $app_small_logo_name = 'app-small-logo.' . $request->app_small_logo->extension();
            $request->app_small_logo->storeAs('logo', $app_small_logo_name);
            $data['app_small_logo'] = 'logo/' . $app_small_logo_name;
        }
        if ($request->favicon_logo) {

            Storage::delete(UtilityFacades::getsettings('favicon_logo'));
            $favicon_logo_name = 'app-favicon-logo.' . $request->favicon_logo->extension();
            $request->favicon_logo->storeAs('logo', $favicon_logo_name);
            $data['favicon_logo'] = 'logo/' . $favicon_logo_name;
        }
        $data['app_name'] = $request->app_name;
        foreach ($data as $key => $value) {
            UtilityFacades::storesettings(['key' => $key, 'value' => $value]);
        }
        // return redirect()->back()->with('success', __('App Setting changed successfully'));
        return response()->json(['Status'=>'Success','message'=>'App Setting changed successfully','redirect'=>'back()'],200);

    }

    public function pusherSettingUpdate(Request $request)
    {

        $this->validate($request, [
            'pusher_id' => 'required|regex:/^[0-9]+$/',
            'pusher_key' => 'required|regex:/^[A-Za-z0-9_.,()]+$/',
            'pusher_secret' => 'required|regex:/^[A-Za-z0-9_.,()]+$/',
            'pusher_cluster' => 'required|regex:/^[A-Za-z0-9_.,()]+$/',
        ], [
            'pusher_id.regex' => 'Invalid Entry! The pusher id only letters, underscore and numbers are allowed',
            'pusher_key.regex' => 'Invalid Entry! The pusher key only letters, underscore and numbers are allowed',
            'pusher_secret.regex' => 'Invalid Entry! The pusher secret only letters, underscore and numbers are allowed',
            'pusher_cluster.regex' => 'Invalid Entry! The pusher cluster only letters, underscore and numbers are allowed',
        ]);
        $data = [
            'pusher_id' => $request->pusher_id,
            'pusher_key' => $request->pusher_key,
            'pusher_secret' => $request->pusher_secret,
            'pusher_cluster' => $request->pusher_cluster,
            'pusher_status' => ($request->pusher_status == 'on') ? 1 : 0,
        ];
        foreach ($data as $key => $value) {
            UtilityFacades::storesettings(['key' => $key, 'value' => $value]);
        }
        // return redirect()->back()->with('success', __('Pusher API Keys Updated Successfully'));
        return response()->json(['Status'=>'Success','message'=>'Pusher API Keys Updated Successfully','redirect'=>'back()'],200);
    }

    public function s3SettingUpdate(Request $request)
    {
        if ($request->settingtype == 's3') {
            $this->validate($request, [
                's3_key' => 'required',
                's3_secret' => 'required',
                's3_region' => 'required',
                's3_bucket' => 'required',
                's3_url' => 'required',
                's3_endpoint' => 'required',
            ], [
                's3_key.regex' => 'Invalid Entry! The s3 key only letters, underscore and numbers are allowed',
                's3_secret.regex' => 'Invalid Entry! The s3 secret only letters, underscore and numbers are allowed',
            ]);
            $data = [
                's3_key' => $request->s3_key,
                's3_secret' => $request->s3_secret,
                's3_region' => $request->s3_region,
                's3_bucket' => $request->s3_bucket,
                's3_url' => $request->s3_url,
                's3_endpoint' => $request->s3_endpoint,
                'settingtype' => $request->settingtype,
            ];
            foreach ($data as $key => $value) {
                UtilityFacades::storesettings(['key' => $key, 'value' => $value]);
            }
        } else {
            UtilityFacades::storesettings(['key' => 'settingtype', 'value' => $request->settingtype]);
        }
        // return redirect()->back()->with('success', __('S3 API Keys Updated Successfully'));
        return response()->json(['Status'=>'Success','message'=>'S3 API Keys Updated Successfully','redirect'=>'back()'],200);
    }

    public function emailSettingUpdate(Request $request)
    {
        $this->validate($request, [
            'mail_mailer' => 'required',
            'mail_host' => 'required',
            'mail_port' => 'required',
            'mail_username' => 'required|email',
            'mail_password' => 'required',
            'mail_encryption' => 'required',
            'mail_from_address' => 'required',
            'mail_from_name' => 'required',
        ], [
            'mail_mailer.regex' => 'Required Entry! The Mail Mailer Not allow empty',
            'mail_host.regex' => 'Required Entry! The Mail Host Not allow empty',
            'mail_port.regex' => 'Required Entry! The Mail Port Not allow empty',
            'mail_username.regex' => 'Required Entry! The Username Mailer Not allow empty',
            'mail_password.regex' => 'Required Entry! The Password Mailer Not allow empty',
            'mail_encryption.regex' => 'Invalid Entry! The Mail encryption Mailer Not allow empty',
            'mail_from_address.regex' => 'Invalid Entry! The Mail From Address Not allow empty',
            'mail_from_name.regex' => 'Invalid Entry! The From name Not allow empty',
        ]);
        $data = [
            'mail_mailer' => $request->mail_mailer,
            'mail_host' => $request->mail_host,
            'mail_port' => $request->mail_port,
            'mail_username' => $request->mail_username,
            'mail_password' => $request->mail_password,
            'mail_encryption' => $request->mail_encryption,
            'mail_from_address' => $request->mail_from_address,
            'mail_from_name' => $request->mail_from_name,
        ];
        foreach ($data as $key => $value) {
            UtilityFacades::storesettings(['key' => $key, 'value' => $value]);
        }
        // return redirect()->back()->with('success', __('Email Setting Updated Successfully'));
        return response()->json(['Status'=>'Success','message'=>'Email Setting Updated Successfully','redirect'=>'back()'],200);
    }


    public function paymentSettingUpdate(Request $request)
    {
        $this->validate($request, [
            'stripe_key' => 'required',
            'stripe_secret' => 'required',
        ], [
            'stripe_key.regex' => 'Invalid Entry! The stripe key only letters, underscore and numbers are allowed',
            'stripe_secret.regex' => 'Invalid Entry! The stripe secret only letters, underscore and numbers are allowed',
        ]);
        $data = [
            'STRIPE_KEY' => $request->stripe_key,
            'STRIPE_SECRET' => $request->stripe_secret,
            'payment' => ($request->payment == 'on') ? 1 : 0,

        ];
        if (Auth::user()->type == 'Admin') {
            foreach ($data as $key => $value) {
                UtilityFacades::storesettings(['key' => $key, 'value' => $value]);
            }
        } else {
            foreach ($data as $key => $value) {
                UtilityFacades::setEnvironmentValue([$key => $value]);
            }
        }

        return redirect()->back()->with('success', __('Payment Setting Updated Successfully'));
    }

    public function authSettingsUpdate(Request $request)
    {
        // dd($request->all());
        if ($request->database_permission == 'on') {
            try {

            DB::statement('create database test_db');
            } catch (\Exception $e) {
                return redirect()->back()->with('errors', __('Please Give Permission To Create Database To User'));
            }
            DB::statement('drop database test_db');
        }
        $data = [
            '2fa' => ($request->two_factor_auth == 'on') ? 1 : 0,
            'rtl' => ($request->rtl_setting == 'on') ? '1' : '0',
            'date_format' => $request->date_format,
            'time_format' => $request->time_format,
            'default_language' => $request->default_language,
            'currency' => $request->currency,
            'currency_symbol' => $request->currency_symbol,
            'dark_mode'=>$request->dark_mode,
            'color' => ($request->color)? $request->color: UtilityFacades::getsettings('color') ,
            'database_permission' => ($request->database_permission == 'on') ? '1' : '0',

        ];
        foreach ($data as $key => $value) {
            UtilityFacades::storesettings(['key' => $key, 'value' => $value]);
        }
        // return redirect()->back()->with('success', __('General Settings Updated Successfully'));
        return response()->json(['Status'=>'Success','message'=>'General Settings Updated Successfully','redirect'=>'back()'],200);
    }


    public function backupFiles()
    {
        Artisan::call('backup:run', ['--only-files' => true]);
        $output = Artisan::output();
        if (Str::contains($output, 'Backup completed!')) {
            // return redirect()->back()->with('success', __('Application Files Backed-up successfully'));
            return response()->json(['Status'=>'Success','message'=>'Application Files Backed-up successfully','redirect'=>'back()'],200);
        } else {
            // return redirect()->back()->with('error', __('Application Files Backed-up failed'));
            return response()->json(['Status'=>'Error','message'=>'Application Files Backed-up failed','redirect'=>''],401);

        }
    }

    public function backupDb()
    {
        Artisan::call('backup:run', ['--only-db' => true]);
        $output = Artisan::output();
        if (Str::contains($output, 'Backup completed!')) {
            // return redirect()->back()->with('success', __('Application Database Backed-up successfully'));
            return response()->json(['Status'=>'Success','message'=>'Application Database Backed-up successfully','redirect'=>'back()'],200);
        } else {
            // return redirect()->back()->with('error', __('Application Database Backed-up failed'));
            return response()->json(['Status'=>'Error','message'=>'Application Database Backed-up failed','redirect'=>''],401);

        }
    }

    private function getBackups()
    {
        $path = storage_path('app/app-backups');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0777, true, true);
        }
        $files = File::allFiles($path);
        $backups = collect([]);
        foreach ($files as $dt) {
            $backups->push([
                'filename' => pathinfo($dt->getFilename(), PATHINFO_FILENAME),
                'extension' => pathinfo($dt->getFilename(), PATHINFO_EXTENSION),
                'path' => $dt->getPath(),
                'size' => $dt->getSize(),
                'time' => $dt->getMTime(),
            ]);
        }
        // return $backups;
        return response()->json(['Status'=>'Success','message'=>'','data'=>$backups],200);

    }

    public function downloadBackup($name, $ext)
    {
        $path = storage_path('app/app-backups');
        $file = $path . '/' . $name . '.' . $ext;
        $status = Storage::disk('backup')->download($name . '.' . $ext, $name . '.' . $ext);
        // return $status;
        return response()->json(['Status'=>'Success','message'=>'','data'=>$status],200);

    }
    public function deleteBackup($name, $ext)
    {
        $path = storage_path('app/app-backups');
        $file = $path . '/' . $name . '.' . $ext;
        $status = File::delete($file);
        if ($status) {
            // return redirect()->back()->with('success', __('Backup deleted successfully'));
            return response()->json(['Status'=>'Success','message'=>'Backup deleted successfully','redirect'=>'back()'],200);

        } else {
            // return redirect()->back()->with('error', __('Ops! an error occured, Try Again'));
            return response()->json(['Status'=>'Error','message'=>'Ops! an error occured, Try Again','redirect'=>''],401);
        }
    }

    function loadsetting($type)
    {
        $t =  ucfirst(str_replace('-', ' ', $type));
        $tenant_id = tenant('id');
        // return view('settings.' . $type, compact('t', 'tenant_id'));
        return response()->json(['Status'=>'Success','message'=>'','t'=>$t, 'tenant_id'=>$tenant_id,'redirect'=>'settings.' . $type],200);

    }
    public function testMail()
    {
        // return view('settings.test-mail');
        return response()->json(['Status'=>'Success','message'=>'','redirect'=>'settings.test-mail'],200);


    }
    public function testSendMail(Request $request)
    {
        $validator = \Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            // return redirect()->back()->with('error', $messages->first());
            return response()->json(['Status'=>'Error','message'=>$messages->first(),'redirect'=>'back()'],400);
        }
        try {
            Mail::to($request->email)->send(new TestMail());
        } catch (\Exception $e) {
            // return redirect()->back()->with('errors', $e->getMessage());
            return response()->json(['Status'=>'Error','message'=>$messages->first(),'redirect'=>'back()'],400);
        }
        // return redirect()->back()->with('success', __('Email send Successfully.'));
        return response()->json(['Status'=>'Success','message'=>'Email send Successfully.','redirect'=>'back()'],200);

    }

    public function landingPage(Request $request)
    {
        // return view('settings.landing');
        return response()->json(['Status'=>'Success','message'=>'','redirect'=>'settings.landing'],200);

    }

    public function landingPagestore(Request $request)
    {

        $data = [
            'footer_page_content' => $request->footer_page_content,
            'privacy' => $request->privacy,
            'contact_us' => $request->contact_us,
            'term_condition' => $request->term_condition,
            'faq_page_content' => $request->faq_page_content,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'recaptcha_key' => $request->recaptcha_key,
            'recaptcha_secret' => $request->recaptcha_secret,
            'contact_us_email' => $request->contact_us_email,
            'captcha_status' => ($request->captcha_status == 'on') ? 1 : 0,
        ];
        foreach ($data as $key => $value) {
            UtilityFacades::storesettings(['key' => $key, 'value' => $value]);
        }
        // return redirect()->back()->with('success', __('Landing Page Setting Updated Successfully'));
        return response()->json(['Status'=>'Success','message'=>'Landing Page Setting Updated Successfully','redirect'=>'back()'],200);

    }
    public function upload(Request $request)
    {
        if ($request->hasFile('upload')) {
            $originName = $request->file('upload')->getClientOriginalName();
            $fileName = pathinfo($originName, PATHINFO_FILENAME);
            $extension = $request->file('upload')->getClientOriginalExtension();
            $fileName = $fileName . '_' . time() . '.' . $extension;

            $request->file('upload')->move(public_path('images'), $fileName);

            $CKEditorFuncNum = $request->input('CKEditorFuncNum');
            $url = asset('public/images/' . $fileName);

            $msg = 'Image uploaded successfully';

            $response = "<script>window.parent.CKEDITOR.tools.callFunction($CKEditorFuncNum, '$url', '$msg')</script>";
            @header('Content-type: text/html; charset=utf-8');
            // echo $response;
            return response()->json(['Status'=>'Success','message'=>'','data'=>$response],200);

        }
    }
}
