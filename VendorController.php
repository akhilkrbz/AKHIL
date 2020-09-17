<?php

namespace App\Http\Controllers;

use App\Libraries\MSG91\Msg91;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

use App\Models\Vendor;
use App\Models\Product;
use App\Models\User;
use App\Mail\VerificationMail;

use App\Events\AttachCategoriesUnitsEvent;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    public function search(Request $request)
    {
        $schema = Validator::make(
            $request->all(),                  //lat-long search pending
            [
                'vendor_type' => 'required',
                'category' => 'required',
                'locality' => 'required',
                'product_query' => 'required'
            ]
        );
        if ($schema->fails()) {
            $error = $schema->errors()->first();
            $data = [
                'status' => 422,
                'message' => $error
            ];
        } else {
            $locality = $request->locality;
            $category = $request->category;
            $vendor_type = $request->vendor_type;
            $product = $request->product_query;

            $vendors = Vendor::where('locality', 'ilike', '%' . $locality . '%')
                ->where('vendor_type', $vendor_type)
                ->where('is_active', true)
                ->whereHas('products', function ($query) use ($product) {
                    $query->where('name', 'ilike', '%' . $product . '%');
                })
                ->whereHas('categories', function ($query) use ($category) {
                    $query->where('name', 'ilike', '%' . $category . '%');
                })
                ->paginate(20);

            if ($vendors->count() > 0) {
                $data = [
                    'status' => 200,
                    'response' => $vendors
                ];
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No vendors found'
                ];
            }
        }

        return response()->json($data, $data['status']);
    }

    public function search_v2(Request $request)
    {

        $schema = Validator::make(
            $request->all(),                  //lat-long search pending
            [
                'vendor_type' => 'required',
                'category' => 'required',
                'geo_location' => 'required|array||bail',
                'geo_location.longitude' => 'required',
                'geo_location.latitude' => 'required',
                'product_query' => 'required'
            ]
        );
        if ($schema->fails()) {
            $error = $schema->errors()->first();
            $data = [
                'status' => 422,
                'message' => $error
            ];
        } else {

            $vendor_type = $request->vendor_type;
            $category = $request->category;
            $product = $request->product_query;

            $geo_location = $request->geo_location;
            $longitude = $geo_location['longitude'];
            $latitude = $geo_location['latitude'];

            //stores within 2 or 3 kms distance //will dbug
            $surrounding_vendor_ids = DB::select("select id from vendors where ST_DWithin(geo_location,ST_MakePoint('$longitude','$latitude')::geography,3000);");
            $surrounding_vendor_ids = array_column($surrounding_vendor_ids, 'id');

            $vendors = Vendor::where('vendor_type', $vendor_type)
                ->where('is_active', true)
                ->whereIn('id', $surrounding_vendor_ids)
                ->whereHas('products', function ($query) use ($product) {
                    $query->where('name', 'ilike', '%' . $product . '%');
                })
                ->whereHas('categories', function ($query) use ($category) {
                    $query->where('name', 'ilike', '%' . $category . '%');
                })
                ->select('id', 'business_name', DB::raw("round(ST_Distance(geo_location, ST_MakePoint('$longitude', '$latitude')::geography) / 1000) as distance"))
                ->paginate(20);
            $data = [
                'status' => 200,
                'response' => $vendors,
            ];
        }
        return response()->json($data, $data['status']);
    }

    public function list_products($vendor_id)  //for consumer,list products 
    {
        $vendor = Vendor::with('products')->find($vendor_id);
        if ($vendor) {
            $products = $vendor->products()->get();   //PAGINATE    
            if ($products->count() > 0) {
                $data = [
                    'status' => 200,
                    'response' => $products
                ];
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No products'
                ];
            }
        } else {
            $data = ['status' => 406, 'message' => 'Invalid store'];
        }
        return response()->json($data, $data['status']);
    }

    public function index(Request $request)
    {
        $user_id = $request->user_info->user_id;
        $user = User::where('id', $user_id)->with('vendors')->first();
        $vendors = $user->vendors()->select(['vendors.id', 'vendors.business_name'])->get(); //dbug
        $data = [
            'status' => 200,
            'response' => $vendors
        ];
        return response()->json($data, $data['status']);
    }

    public function create(Request $request)
    {
        $schema = Validator::make($request->all(), [
            'email' => [Rule::requiredIf($request->input('phone', false) === false), 'email',  'max:120',  'bail'],
            'phone' => [Rule::requiredIf($request->input('email', false) === false), 'min:10', 'max:10', 'bail'],
            'password' => 'required|max:25|min:6',
            'business_name' => 'required|max:120',
            'first_name' => 'required|max:120',
            'last_name' => 'required|max:120',
            'address' => 'required|array',
            'address.addressLine1' => 'required|max:120',
            'address.addressLine2' => 'required|max:120',
            'address.pincode' => 'required|max:6|min:6',
            'address.city' => 'required|max:120',
            'address.state' => 'required|max:120',
            'addess.landmark' => 'sometimes|max:120',
            'geo_location' => 'array|required',
            'geo_location.longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
            'geo_location.latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'gstin' => 'sometimes|min:15|max:15|unique:vendors',
            'state_code' => 'required|max:2',
            'state' => 'required|max:40',
            'locality' => 'required',
            'vendor_type' => ['required', Rule::in(
                'stationary',
                'vegetable_store',
                'departmental_store',
                'chicken_stall',
                'super_market',
                'agro_store',
                'meat_stall',
                'fish_store',
                'dry_fruit_store',
                'grocery'
            )]
        ]);

        if ($schema->fails()) {
            $error = $schema->errors()->first();
            $data = [
                'status' => 422,
                'message' => $error
            ];
        } else {
            $inputs = $schema->validated();

            $email = $request->input('email', false);
            $phone = $request->input('phone', false);

            $user = User::where('phone', $phone)->orWhere('email', $email)->first();

            if ($user) {
                $is_vendor = User::where('id', $user->id)->has('vendors')->exists();

                if ($is_vendor) {

                    $data = [
                        'status' => 422,
                        'message' => 'A vendor already exist on this email/phone'
                    ];
                } else {

                    $vendor = $user->vendors()->create($inputs);

                    event(new AttachCategoriesUnitsEvent($vendor));

                    $geo_location = $request->geo_location;
                    $this->update_geo_location($vendor->id, $geo_location);

                    $data = [
                        'status' => 201,
                        'message' => 'Vendor created',

                    ];
                }
            } else {

                $user = User::create($inputs);
                $vendor = $user->vendors()->create($inputs);
                event(new AttachCategoriesUnitsEvent($vendor));

                $geo_location = $request->geo_location;
                $this->update_geo_location($vendor->id, $geo_location);


                $user  = $request->phone ?? $request->email;

                $otp = random_int(1000, 9999);
                $fifteen_minute = 60 * 15;
                $key_name = 'USER:' . $user;
                Cache::put($key_name, $otp, $fifteen_minute);


                if ($phone !== false) {
                    $message = $otp . ' is your OTP for your account verification of Tradeit';
                    Msg91::sendSms($phone, $message);
                } else {
                    Mail::to($email)->queue(new VerificationMail($otp));
                }

                $message = 'Otp sent to ' . $user;
                $data = [
                    'status' => 201,
                    'messsage' => $message,
                ];
            }
        }

        return response()->json($data, $data['status']);
    }

    private function update_geo_location($vendor_id, $geo_location)
    {
        $longitude = $geo_location['longitude'];
        $latitude = $geo_location['latitude'];

        DB::statement("UPDATE vendors SET geo_location = ST_SetSRID(ST_MakePoint('$longitude','$latitude'), 4326) WHERE id='$vendor_id';");
    }

    public function show(Request $request, $id)
    {
        $vendor = $request->vendor;
        $data = [
            'status' => 200,
            'results' => $vendor
        ];
        return response()->json($data, $data['status']);
    }


    public function update(Request $request)
    {
        $schema = Validator::make($request->all(),  [
            'email' => [Rule::requiredIf($request->input('phone', false) === false), 'email',  'max:120',  'bail'],
            'phone' => [Rule::requiredIf($request->input('email', false) === false), 'min:10', 'max:10', 'bail'],
            'password' => 'required|max:25|min:6',
            'business_name' => 'required|max:120',
            'first_name' => 'required|max:120',
            'last_name' => 'required|max:120',
            'address' => 'required|array',
            'address.addressLine1' => 'required|max:120',
            'address.addressLine2' => 'required|max:120',
            'address.pincode' => 'required|max:6|min:6',
            'address.city' => 'required|max:120',
            'address.state' => 'required|max:120',
            'addess.landmark' => 'sometimes|max:120',
            'geo_location' => 'array|required',
            'geo_location.longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
            'geo_location.latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'gstin' => 'sometimes|min:15|max:15|unique:vendors',
            'state_code' => 'required|max:2',
            'state' => 'required|max:40',
            'locality' => 'required',
            'vendor_type' => ['required', Rule::in(
                'stationary',
                'vegetable_store',
                'departmental_store',
                'chicken_stall',
                'super_market',
                'agro_store',
                'meat_stall',
                'fish_store',
                'dry_fruit_store',
                'grocery'
            )]
        ]);

        if ($schema->fails()) {
            $error = $schema->errors()->first();
            $data = [
                'status' => 422,
                'message' => $error
            ];
        } else {
            $inputs = $schema->validated();
            $vendor = $request->vendor;
            $vendor->update($inputs);
            $data = [
                'status' => 200,
                'message' => 'Vendor updated'
            ];
        }
        return response()->json($data, $data['status']);
    }

    public function update_bank_details(Request $request)
    {
        $schema = Validator::make(
            $request->all(),
            [
                'bank_name' => 'required|max:30',   //supported banks ???
                'account_holder_name' => 'required|max:40',
                'account_number' => 'required|max:20',
                'ifsc' => 'required|max:15'
            ]
        );
        if ($schema->fails()) {
            $error = $schema->errors()->first();
            $data = [
                'status' => 422,
                'message' => $error
            ];
        } else {
            $inputs = $schema->validated();

            $vendor = $request->vendor;
            $vendor->update(['bank_details' => json_encode($inputs)]);
            $data = [
                'status' => 200,
                'message' => 'Bank account details updated'
            ];
        }
        return response()->json($data, $data['status']);
    }

    public function deactivate(Request $request)
    {
        $vendor = $request->vendor;
        $vendor->update(['is_active' => false]);  //deactivate
        $data = [
            'status' => 200,
            'message' => 'Vendor deactivated'
        ];
        return response()->json($data, $data['status']);
    }





    //vendor_products... 
    public function vendor_products($vid = "")
    {
        $products = Product::where('vendor_id',$vid)->get();

        if($products->count() > 0){
            $data = [
                'status' => 200,
                'response' => $products
            ];
        }
        else{
            $data = [
                'status' => 404,
                'response' => 'No products found under this vendor'
            ];
        }
        return response()->json($data);
    }
}
