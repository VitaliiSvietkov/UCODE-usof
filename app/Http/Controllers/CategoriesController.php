<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categories;
use App\Models\Post;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // get all categories
        // avilable for all users
        return Categories::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // create a category
        // available only for admins
        $user = $this->isAdmin();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        $credentials = $request->only(['title', 'description']);

        $validator = \Validator::make($credentials, [
            'title' => 'required|unique:App\Models\Categories,title',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        return Categories::create($credentials);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // show a category
        // available for all users
        $category = Categories::find($id);
        if (empty($category)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such category'
            ]);
        }

        return $category;
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
        // update a category
        // available only for admins
        $user = $this->isAdmin();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        $category = Categories::find($id);
        if (empty($category)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such category'
            ]);
        }

        $credentials = $request->only(['title', 'description']);

        $validator = \Validator::make($credentials, [
            'title' => 'unique:App\Models\Categories,title',
            'description' => 'min:10'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        $category->update($credentials);
        return $category;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // delete a category
        // available only for admins
        $user = $this->isAdmin();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        $category = Categories::find($id);
        if (empty($category)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such category'
            ]);
        }

        return Categories::destroy($id);
    }

    /**
     * Get all posts with a specific category.
     *
     * @param  \Illuminate\Http\Request $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function getPosts(Request $request, $id) {
        // available for all users
        $category = Categories::find($id);
        if (empty($category)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such category'
            ]);
        }

        return \DB::table('posts')->whereJsonContains('categories', array('value' => (int)$id))->get();
    }
}
