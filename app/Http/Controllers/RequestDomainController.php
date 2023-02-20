<?php

namespace App\Http\Controllers;

use App\DataTables\RequestDomainDataTable;
use App\Facades\UtilityFacades;
use App\Models\Order;
use App\Models\Plan;
use App\Models\RequestDomain;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Database\Models\Domain;
use Stripe\Stripe;
use App\Mail\ApproveMail;
use App\Mail\ConatctMail;
use App\Mail\DisapprovedMail;
use App\Models\Category;
use App\Models\Posts;
use Stripe\Product;

class RequestDomainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function landingPage()
    {
        $central_domain = config('tenancy.central_domains')[0];
        $current_domain = tenant('domains');
        if (!empty($current_domain)) {
            $current_domain = $current_domain->pluck('domain')->toArray()[0];
        }
        if ($current_domain == null) {
            if (!file_exists(storage_path() . "/installed")) {
                header('location:install');
                die;
            }
            $plans = Plan::all();
            // return view('welcome', compact('plans'));
            return response()->json(['Status'=>'Success','data'=>$plans,'redirect'=>'welcome'],200);
        } else {
            $categories = Category::all();
            $category = [];
            $category['0'] = __('Select Category');
            foreach ($categories as $cate) {
                $category[$cate->id] = $cate->name;
            }
            $posts =  Posts::latest()->take(4)->get();
            // return view('welcome', compact('posts', 'category'));
            return response()->json(['Status'=>'Success','message'=>'','redirect'=>'welcome','posts'=>$posts,'category'=>$category],200);


        }
    }

    public function get_category_post(Request $request)
    {
        $post = Posts::where('category_id', $request->category)->get();
        // return response()->json($post, 200);


    }
    public function post_details($slug, Request $request)
    {
        $post = Posts::where('slug', $slug)->first();
        $random_posts = Posts::where('slug', '!=', $slug)->limit(3)->get();
        return view('posts.details', compact('post', 'random_posts'));
    }
    public function index(RequestDomainDataTable $dataTable)
    {
        if (\Auth::user()->hasrole('Super Admin')) {
            return $dataTable->render('requestdomain.index');
            return response()->json(['Status'=>'Success','message'=>'','redirect'=>'requestdomain.index'],200);
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json(['Status'=>'Error','message'=>'Permission Denied.','redirect'=>'back()'],401);
        }
    }

    public function create($data)
    {

        try {
            $data = Crypt::decrypt($data);
            $plan_id = $data['plan_id'];
        } catch (DecryptException $e) {
            return redirect()->back()->with('failed', $e->getMessage());
        }

        // return view('requestdomain.create', compact('plan_id'));
        return response()->json(['Status'=>'Success','message'=>'','redirect'=>'requestdomain.create','plan_id'=>$plan_id],200);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($request->agree == 'on') {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email,',
                    'domains' => 'required|unique:domains,domain',
                    'password' => 'same:password_confirmation',

                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                // return redirect()->back()->with('errors', $messages->first());
                return response()->json(['Status'=>'Error','message'=>$messages->first(),'redirect'=>''],400);
            }
            $domain = new RequestDomain();
            $domain->name = $request->name;
            $domain->email = $request->email;
            $domain->password = Hash::make($request->password);
            $domain->domain_name = $request->domains;
            $domain->type = 'Admin';
            $domain->save();


            $order = tenancy()->central(function ($tenant) use ($request, $domain) {
                $plan_details = Plan::find($request->plan_id);

                $data = Order::create([
                    'plan_id' => $request->plan_id,
                    'domainrequest_id' => $domain->id,
                    'amount' => $plan_details->price,
                    'status' => 0,
                ]);
                // return $data;
                return response()->json(['Status'=>'Success','message'=>'orderInfoPlan','data'=>$data,'redirect'=>''],200);
            });
            $response = array(
                'status' => 0,
                'order_id' => $order->id,
                'domainrequest_id' => $domain->id,

            );
            $database = $request->all();
            if ($request->plan_id != 1) {
                echo json_encode($response);
                die;
            } else {
                if (UtilityFacades::getsettings('database_permission') == 1) {
                    UtilityFacades::approved_request($domain->id, $database);
                }
                // return redirect()->route('landingpage')->with('success', __('Thanks for registration, your account is in review and you get email when your account active.'));
                return response()->json(['Status'=>'Success','message'=>'Thanks for registration, your account is in review and you get email when your account active.','redirect'=>'landingpage'],201);
            }
        } else {
            // return redirect()->back()->with('status', 'Please check terms and conditions');
            return response()->json(['Status'=>'Error','message'=>'Please check terms and conditions','redirect'=>'back()'],400);
        }
    }


    public function approveStatus($id)
    {
        // dd($id);
        $requestdomain = RequestDomain::find($id);
        if ($requestdomain->is_approved == 0) {

            // return view('requestdomain.edit', compact('requestdomain'));
            return response()->json(['Status'=>'Success','message'=>'','requestdomain'=>$requestdomain,'redirect'=>'requestdomain.edit'],200);
        } else {
            // return redirect()->back();
            return response()->json(['Status'=>'Error','message'=>'','redirect'=>'back()'],401);
        }
    }
    public function disapproveStatus($id)
    {
        $requestdomain = RequestDomain::find($id);
        if ($requestdomain->is_approved == 0) {
            // $view =   view('requestdomain.reason', compact('requestdomain'));
            // return ['html' => $view->render()];
            return response()->json(['Status'=>'Success','message'=>'','requestdomain'=>$requestdomain,'redirect'=>'requestdomain.reason'],200);
        } else {
            // return redirect()->back();
            return response()->json(['Status'=>'Error','message'=>'','redirect'=>'back()'],400);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'reason' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            // return redirect()->back()->with('errors', $messages->first());
            return response()->json(['Status'=>'Error','message'=>$messages->first(),'redirect'=>'back()'],400);
        }

        $requestdomain = RequestDomain::find($id);
        $requestdomain->reason = $request->reason;
        $requestdomain->is_approved = 2;
        $requestdomain->update();
        try {
            Mail::to($requestdomain->email)->send(new DisapprovedMail($requestdomain));
        } catch (\Exception $e) {
            // return redirect()->back()->with('errors', __($e->getMessage()));
            return response()->json(['Status'=>'Error','message'=>$e->getMessage(),'redirect'=>'back()'],400);
        }
        // return redirect()->back()->with('success', __('Domain Request Disapprove successfully'));
        return response()->json(['Status'=>'Success','message'=>'Domain Request Disapprove successfully','redirect'=>'back()'],201);
    }

    public function prestripeSession(Request $request)
    {

        Stripe::setApiKey(env('STRIPE_SECRET'));
        $currency = UtilityFacades::getsettings('currency');


        if (!empty($request->createCheckoutSession)) {

            $plan_details = tenancy()->central(function ($tenant) use ($request) {
                // return Plan::find($request->plan_id);
                return response()->json(['Status'=>'Success','DataPlan'=>Plan::find($request->plan_id)],200);

            });
            // Create new Checkout Session for the order
            try {
                $checkout_session = \Stripe\Checkout\Session::create([
                    'line_items' => [[
                        'price_data' => [
                            'product_data' => [
                                'name' => $plan_details->name,
                                'metadata' => [
                                    'plan_id' => $request->plan_id,
                                    'domainrequest_id' => $request->domain_id
                                ]
                            ],
                            'unit_amount' => $plan_details->price * 100,
                            'currency' => $currency,
                        ],
                        'quantity' => 1,
                        'description' => $plan_details->name,
                    ]],
                    'mode' => 'payment',
                    'success_url' => route('pre.stripe.success.pay', Crypt::encrypt(['plan_id' => $plan_details->id, 'price' => $plan_details->price, 'domainrequest_id' => $request->domain_id, 'order_id' => $request->order_id])),
                    'cancel_url' => route('pre.stripe.cancel.pay', Crypt::encrypt(['plan_id' => $plan_details->id, 'price' => $plan_details->price, 'domainrequest_id' => $request->domain_id, 'order_id' => $request->order_id])),
                ]);

                // dd($checkout_session);

            } catch (Exception $e) {
                $api_error = $e->getMessage();
                // dd($api_error);

            }

            if (empty($api_error) && $checkout_session) {
                $response = array(
                    'status' => 1,
                    'message' => 'Checkout Session created successfully!',
                    'sessionId' => $checkout_session->id
                );
            } else {
                $response = array(
                    'status' => 0,
                    'error' => array(
                        'message' => 'Checkout Session creation failed! ' . $api_error
                    )
                );
            }
        }

        // echo json_encode($response);
        return response()->json(['Status'=>'Success','action'=>'Checkout Session for the order','data'=>$response],200);

        die;
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    function prepaymentCancel($data)
    {
        $data = Crypt::decrypt($data);

        $order = tenancy()->central(function ($tenant) use ($data) {
            $datas = Order::find($data['order_id']);
            $datas->status = 2;
            $datas->update();
        });

    // return redirect()->route('landingpage')->with('error', 'Payment canceled!');
    return response()->json(['Status'=>'Error','message'=>'Payment canceled!','redirect'=>'landingpage'],402);

    }

    function prepaymentSuccess($data)
    {
        $data = Crypt::decrypt($data);
        $database = $data;
        $order = tenancy()->central(function ($tenant) use ($data) {
            $datas = Order::find($data['order_id']);
            $datas->status = 1;
            $datas->update();
        });
        if (UtilityFacades::getsettings('database_permission') == 1) {
            UtilityFacades::approved_request($data['domainrequest_id'], $database);
        }

        // return redirect()->route('landingpage')->with('status', 'Thanks for registration, your account is in review and you get email when your account active.');
        return response()->json(['Status'=>'Success','message'=>'Thanks for registration, your account is in review and you get email when your account active.','redirect'=>'landingpage'],201);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $requestdomain = RequestDomain::find($id);
        return view('requestdomain.data_edit', compact('requestdomain'));
        return response()->json(['Status'=>'Success','message'=>'edit','requestdomain'=>$requestdomain,'redirect'=>'requestdomain.data_edit'],200);
    }

    public function data_update(Request $request, $id)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email|unique:users,email,',
                'domains' => 'required|unique:domains,domain',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            // return redirect()->back()->with('errors', $messages->first());
            return response()->json(['Status'=>'Error','message'=>$messages->first(),'redirect'=>'back()'],400);

        }

        $requestdomain = RequestDomain::find($id);

        $requestdomain['name'] = $request->name;
        $requestdomain['email'] = $request->email;
        $requestdomain['domain_name'] = $request->domains;
        // $requestdomain['password'] = Hash::make($request->password);
        if (!empty($request->password)) {
            $requestdomain->password = Hash::make($request->password);
        }
        $requestdomain->update();
        // return redirect()->back()->with('success', __('Domain Request updated successfully'));
        return response()->json(['Status'=>'Success','message'=>'Domain Request updated successfully','redirect'=>'back()'],200);
    }

    public function destroy($id)
    {
        $requestdomain = RequestDomain::find($id);

        $requestdomain->delete();

        // return redirect()->route('requestdomain.index')->with('success', __('Domain Request deleted successfully'));
        return response()->json(['Status'=>'Success','message'=>'Domain Request updated successfully','redirect'=>'requestdomain.index'],200);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // dd($request->all());
        $data = RequestDomain::where('email', $request->email)->first();

        // $data = Order::where('domainrequest_id', $req->id)->first();
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email|unique:users,email,',
                'domains' => 'required|unique:domains,domain',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('errors', $messages->first());
        }
        $database = $request->all();
        UtilityFacades::approved_request($data->id, $database);

        // return redirect()->route('requestdomain.index')->with('success', __('User created successfully'));
        return response()->json(['Status'=>'Success','message'=>'User created successfully','redirect'=>'requestdomain.index'],200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */


    public function contactus()
    {
        // return view('contactus');

    return response()->json(['Status'=>'Success','message'=>'contactus','redirect'=>'contactus'],200);

    }

    public function termsandconditions()
    {
        // return view('termsandconditions');

    return response()->json(['Status'=>'Success','message'=>'termsandconditions','redirect'=>'termsandconditions'],200);

    }

    public function privacypolicy()
    {
        // return view('privacypolicy');

    return response()->json(['Status'=>'Success','message'=>'privacypolicy','redirect'=>'privacypolicy'],200);

    }

    public function faq()
    {
        // return view('faq');

    return response()->json(['Status'=>'Success','message'=>'faq','redirect'=>'faq'],200);

    }

    public function contact_mail(Request $request)
    {
        if ($request) {
            $details = $request->all();
            Mail::to(UtilityFacades::getsettings('contact_us_email'))->send(new ConatctMail($request->all()));
            // return redirect()->back()->with('success', 'Email sent Successfully');
            return response()->json(['Status'=>'Success','message'=>'Email sent Successfully','redirect'=>'back()'],200);

        } else {
            // return redirect()->back()->with('failed', __('Please check Recaptch'));
            return response()->json(['Status'=>'Error','message'=>'Please check Recaptch','redirect'=>'back()'],400);
        }
    }
}
