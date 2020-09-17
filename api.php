<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Events\newOrderEvent;
use App\Models\Order;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//regular user
Route::post('user/signup', 'UserController@create');
Route::post('user/login', 'UserController@login');
Route::post('user/verify', 'UserController@verify');

Route::post('user/change_password', 'UserController@change_password')->middleware('jwt_auth');
Route::post('user/forgot_password', 'UserController@forgot_password');
Route::post('user/reset_password', 'UserController@reset_password');

//consumer facing
Route::post('vendors/search', 'VendorController@search');  //store search,dbug
Route::post('vendors/search/v2', 'VendorController@search_v2'); //store search

Route::get('categories', 'CategoryController@list_categories');     //list all categories
Route::get('products/{vendor_id}', 'VendorController@list_products'); //list products in a specific store

// Route::get('stores/{category_id}', 'CategoryController@list_stores'); //list all stores with category



Route::post('vendor/signup', 'VendorController@create'); //direct vendor signup
Route::post('user/vendor/signup', 'VendorController@user_signup')->middleware(['jwt_auth']);  //logged in USER signup

Route::post('vendor/login', 'UserController@login');
Route::get('vendors', 'VendorController@index')->middleware(['jwt_auth', 'is_vendor']);
Route::get('vendors/{id}', 'VendorController@show')->middleware(['jwt_auth', 'is_vendor', 'is_owner:vendor']);
Route::put('vendors/{id}', 'VendorController@update')->middleware(['jwt_auth', 'is_vendor', 'is_owner:vendor']);
Route::delete('vendors/{id}', 'VendorController@deactivate')->middleware(['jwt_auth', 'is_vendor', 'is_owner:vendor']);

//save bank account details for vendor
Route::post('vendor/bank_details/{id}', 'VendorController@update_bank_details')->middleware(['jwt_auth', 'is_vendor', 'is_owner:vendor']);

//need read api--operated by root or sudoer ???    

//categories
Route::get('vendor/categories', 'CategoryController@index')->middleware(['jwt_auth', 'is_vendor']);
Route::get('vendor/categories/{id}', 'CategoryController@show')->middleware(['jwt_auth', 'is_vendor', 'is_owner:category']);
Route::post('vendor/categories', 'CategoryController@store')->middleware(['jwt_auth', 'is_vendor']);;
Route::put('vendor/categories/{id}', 'CategoryController@update')->middleware(['jwt_auth', 'is_vendor', 'is_owner:category']);
Route::delete('vendor/categories/{id}', 'CategoryController@destroy')->middleware(['jwt_auth', 'is_vendor', 'is_owner:category']);

//units
Route::get('vendor/units', 'UnitController@index')->middleware(['jwt_auth', 'is_vendor']);
Route::get('vendor/units/{id}', 'UnitController@show')->middleware(['jwt_auth', 'is_vendor', 'is_owner:unit']);
Route::post('vendor/units', 'UnitController@store')->middleware(['jwt_auth', 'is_vendor']);;
Route::put('vendor/units/{id}', 'UnitController@update')->middleware(['jwt_auth', 'is_vendor', 'is_owner:unit']);
Route::delete('vendor/units/{id}', 'UnitController@destroy')->middleware(['jwt_auth', 'is_vendor', 'is_owner:unit']);


//products
Route::get('vendor/products', 'ProductController@index');
Route::get('vendor/products/{id}', 'ProductController@show');
Route::post('vendor/products', 'ProductController@store');
Route::put('vendor/products/{id}', 'ProductController@update');
Route::delete('vendor/products/{id}', 'ProductController@destroy');

//orders
Route::get('vendor/orders', 'OrderController@index')->middleware(['jwt_auth', 'is_vendor']);
Route::get('vendor/orders/{id}', 'OrderController@show')->middleware(['jwt_auth', 'is_vendor', 'is_owner:order']);
Route::post('vendor/orders/{id}/dispatch', 'OrderController@order_dispatched')->middleware(['jwt_auth', 'is_vendor', 'is_owner:order']);

// order manipulations for user 
Route::post('orders', 'OrderController@create')->middleware(['jwt_auth']);
Route::get('orders/{id}', 'OrderController@show')->middleware(['jwt_auth', 'is_owner:user_order']);
Route::put('orders/{id}', 'OrderController@update')->middleware(['jwt_auth', 'is_owner:user_order']);
Route::post('orders/cancel/{id}', 'OrderController@cancel_order')->middleware(['jwt_auth', 'is_owner:user_order']);

//vendor
Route::post('vendor/orders/change_status/{id}', 'OrderController@change_status')->middleware(['jwt_auth', 'is_vendor', 'is_owner:order']);
Route::post('vendor/orders/{id}/decline', 'OrderController@decline_order')->middleware(['jwt_auth', 'is_vendor', 'is_owner:order']);

//user initiated cancel order
Route::post('orders/cancel/{id}', 'OrderController@cancel_order')->middleware(['jwt_auth', 'is_owner:user_order']);

//COD,manually update as PAID
Route::post('vendor/update_payment', 'PaymentController@update_payment_status')->middleware(['jwt_auth', 'is_vendor', 'is_owner:order']); //dbug
//payment
Route::post('payment', 'PaymentController@create')->middleware(['jwt_auth']);
Route::post('payment_webhook', 'PaymentController@payment_webhook');


Route::post('firestore', function (Request $request) {

    $order = Order::first();
    $return_data = event(new newOrderEvent($order))[0];

    return response()->json($return_data);
});



Route::get('vendor/listproducts/{id}','VendorController@vendor_products');