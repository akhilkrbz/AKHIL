<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Categorymodel;

class Category extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        $schema = Validator::make($request->all(),[
                        'name' => 'required|unique:category|max:40|bail',
                        'vendor_id' => 'required|unique:category'
                    ]);

        if($schema->fails()){
            $errors = $schema->errors()->first();
            $data = [
                        'status' => 422,
                        'message' => $errors
                    ];
            
        }
        else{
            $inputs = $schema->validated();
            Categorymodel::create($inputs);

            $data = [
                        'status' => 201,
                        'message' => 'Category added successfully'
                    ];
        }

        return response()->json($data,$data['status']);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //

        $category = Categorymodel::find($id);
        return response()->json($category);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        $schema = Validator::make($request->all(),[
                        'name' => 'required|unique:category|max:40|bail',
                        'vendor_id' => 'required|unique:category'
                    ]);

        if($schema->fails()){
            $errors = $schema->errors()->first();
            $data = [
                        'status' => 422,
                        'message' => $errors
                    ];
            
        }
        else{
            $inputs = $schema->validated();

            Categorymodel::where('id',$id)->update($inputs);

            $data = [
                        'status' => 201,
                        'message' => 'Category updated successfully'
                    ];
        }

        return response()->json($data,$data['status']);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //

        Categorymodel::where('id',$id)->delete();
        $data = [
                    'status' => 201,
                    'message' => 'Category deleted successfully'
                ];
        return response()->json($data,$data['status']);

    }
}
