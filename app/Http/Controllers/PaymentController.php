<?php

namespace App\Http\Controllers;

use App\Facades\UtilityFacades;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Stripe\Charge;
use Stripe\Stripe as StripeStripe;

class PaymentController extends Controller
{
    public function stripe()
    {
        $view =  view('payment.PaymentStripe');
        return ['html' => $view->render()];
    }

    public function stripeSession(Request $request)
    {
        if (Auth::user()->type != 'Admin') {
            StripeStripe::setApiKey(UtilityFacades::getsettings('STRIPE_SECRET'));
            $currency = UtilityFacades::getsettings('currency');
        } else {
            $currency = tenancy()->central(function ($tenant) {
                return UtilityFacades::getsettings('currency');
            });
            StripeStripe::setApiKey(env('STRIPE_SECRET'));
        }

        if (!empty($request->createCheckoutSession)) {

            if (Auth::user()->type == 'Admin') {
                $plan_details = tenancy()->central(function ($tenant) use ($request) {
                    return Plan::find($request->plan_id);
                });
            } else {
                $plan_details =  Plan::find($request->plan_id);
            }


            // Create new Checkout Session for the order
            try {
                $checkout_session = \Stripe\Checkout\Session::create([
                    'line_items' => [[
                        'price_data' => [
                            'product_data' => [
                                'name' => $plan_details->name,
                                'metadata' => [
                                    'plan_id' => $request->plan_id,
                                    'user_id' => Auth::user()->id
                                ]
                            ],
                            'unit_amount' => $plan_details->price * 100,
                            'currency' => $currency,
                        ],
                        'quantity' => 1,
                        'description' => $plan_details->name,
                    ]],
                    'mode' => 'payment',
                    'success_url' => route('stripe.success.pay', Crypt::encrypt(['plan_id' => $plan_details->id, 'price' => $plan_details->price, 'user_id' => Auth::user()->id, 'order_id' => $request->order_id])),
                    'cancel_url' => route('stripe.cancel.pay', Crypt::encrypt(['plan_id' => $plan_details->id, 'price' => $plan_details->price, 'user_id' => Auth::user()->id, 'order_id' => $request->order_id])),
                ]);


            } catch (Exception $e) {
                $api_error = $e->getMessage();
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
        // return response()->json($response);
        return response()->json(['Status'=>'Success','message'=>$response,'redirect'=>''],200);
        die;
    }
    function paymentPending(Request $request)
    {

        if (Auth::user()->type == 'Admin') {
            $user = User::find(Auth::user()->id);

            $order = tenancy()->central(function ($tenant) use ($request, $user) {
                $plan_details = Plan::find($request->plan_id);
                $user = User::where('email', $user->email)->first();

                // dd($user);
                $data = Order::create([
                    'plan_id' => $request->plan_id,
                    'user_id' => $user->id,
                    'amount' => $plan_details->price,
                    'status' => 0,
                ]);
                return $data;
            });
            $response = array(
                'status' => 0,
                'order_id' => $order->id
            );
            // echo json_encode($response);
            return response()->json(['Status'=>'Success','message'=>$response,'redirect'=>''],200);
            die;
        } else {

            $user = User::find(Auth::user()->id); {
                $plan_details = Plan::find($request->plan_id);
                $user = User::where('email', $user->email)->first();
                $data = Order::create([
                    'plan_id' => $request->plan_id,
                    'user_id' => Auth::user()->id,
                    'amount' => $plan_details->price,
                    'status' => 0,
                ]);
            }
            $response = array(
                'status' => 0,
                'order_id' => $data->id
            );
            // echo json_encode($response);
            return response()->json(['Status'=>'Success','message'=>$response,'redirect'=>''],200);
            die;

        }
    }

    function paymentCancel($data)
    {
        $data = Crypt::decrypt($data);
        if (Auth::user()->type == 'Admin') {
            $order = tenancy()->central(function ($tenant) use ($data) {
                $datas = Order::find($data['order_id']);
                $datas->status = 2;
                $datas->update();
            });
        } else {
            $datas = Order::find($data['order_id']);
            $datas->status = 2;
            $datas->update();
        }

        // return redirect()->route('plans.index')->with('error', 'Payment canceled!');
        return response()->json(['Status'=>'Error','message'=>'Payment canceled!','redirect'=>'plans.index'],401);
    }

    function paymentSuccess($data)
    {
        $data = Crypt::decrypt($data);
        if (Auth::user()->type == 'Admin') {

            $order = tenancy()->central(function ($tenant) use ($data) {
                $datas = Order::find($data['order_id']);
                $datas->status = 1;
                $datas->update();


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
            });
        } else {
            $datas = Order::find($data['order_id']);
            $datas->status = 1;
            $datas->update();


            $user = User::find(Auth::user()->id);
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
        }


        // return redirect()->route('plans.index')->with('status', 'Payment successfully!');
        return response()->json(['Status'=>'Success','message'=>'Payment successfully!','redirect'=>'plans.index'],201);
    }

    public function stripePost(Request $request)
    {
        if (Auth::user()->type != 'Admin') {
            StripeStripe::setApiKey(UtilityFacades::getsettings('STRIPE_SECRET'));
        } else {
            StripeStripe::setApiKey(env('STRIPE_SECRET'));
        }

        if (Auth::user()->type == 'Admin') {
            $plan = tenancy()->central(function ($tenant) use ($request) {
                return Plan::find($request->plan_id);
            });
        } else {
            $plan = Plan::where('id', $request->plan_id)->first();
        }

        try {
            $charge = Charge::create([
                "amount" => $plan->price * 100,
                "currency" => "usd",
                "source" => $request->stripeToken,
                "description" => $plan->name
            ]);
        } catch (Exception $e) {
            // return redirect()->back()->with('failed', $e->getMessage());
            return response()->json(['Status'=>'Error','message'=>$e->getMessage(),'redirect'=>'back()'],401);
        }
        if ($charge) {

            if (Auth::user()->type == 'Admin') {
                $order = tenancy()->central(function ($tenant) use ($plan, $charge) {
                    $user = User::find($tenant->id);
                    Order::create([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'stripe_payment_id' => $charge->id,
                        'amount' => $plan->price
                    ]);
                    $user->plan_id = $plan->id;
                    if ($plan->durationtype == 'Month' && $plan->id != '1') {
                        $user->plan_expired_date = Carbon::now()->addMonths($plan->duration)->isoFormat('YYYY-MM-DD');
                    } elseif ($plan->durationtype == 'Year' && $plan->id != '1') {
                        $user->plan_expired_date = Carbon::now()->addYears($plan->duration)->isoFormat('YYYY-MM-DD');
                    } else {
                        $user->plan_expired_date = null;
                    }
                    $user->save();
                });
            } else {
                $order = Order::create([
                    'user_id' => Auth::user()->id,
                    'plan_id' => $plan->id,
                    'stripe_payment_id' => $charge->id,
                    'amount' => $plan->price
                ]);
            }
            Auth::user()->assignPlan($plan->id);
            // return redirect()->back()->with('success', __('Payment Done.'));
            return response()->json(['Status'=>'Success','message'=>__('Payment Done.'),'redirect'=>'back()'],200);
        }
        // return redirect()->back()->with('failed', __('Payment Failed.'));
        return response()->json(['Status'=>'Error','message'=>__('Payment Failed.'),'redirect'=>'back()'],401);
    }
}
