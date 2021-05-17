<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Post;
use App\Models\Comments;

use Validator;
use DB;
//use App\Http\Conrollers\AuthController;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // available for all users
        return User::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // create a user
        // available only for admins
        $user = $this->isAdmin();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        $credentials = $request->only(['login', 'password', 'email', 'role', 'full_name', 'profile_picture']);

        $validator = Validator::make($credentials, [
            'login' => 'required|unique:App\Models\User,login',
            'password' => 'required|min:6|max:14',
            'email' => 'required|unique:App\Models\User,email',
            'role' => 'in:admin,user'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        $credentials['password'] = Hash::make($credentials['password']);

        $image_data;
        if (isset($credentials['profile_picture'])) {
            $image_data = $credentials['profile_picture'];
            unset($credentials['profile_picture']);
        }

        $user = User::create($credentials);

        if (isset($image_data)) {
            $image = $credentials['profile_picture'];
            $image_data = 'avatars/' . $user->id . '.png';
            $file = fopen($image_data, "w");
            fwrite($file, base64_decode($image));
            fclose($file);

            $user->profile_picture = $image_data;
            $user->save();
        }

        return response()->json([
            'status' => 'OK',
            'message' => 'User successfully registered',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // available for all users
        $user = User::find($id);

        $user_posts_id = array();
        $user_posts = Post::where('author', $user->id)->get();
        $user->total_posts = count($user_posts); // Amount of user's posts
        foreach($user_posts as $post)
            array_push($user_posts_id, (int)$post->id);

        $count = 0;
        foreach ($user_posts_id as $post_id) {
            $users_that_starred = User::whereJsonContains('starred', $post_id)->get();
            $count += count($users_that_starred);
        }
        $user->posts_total_starred = $count; // Amount of users that starred current user's posts

        $user_comments = Comments::where('author', $id)->get();
        $user->total_comments = count($user_comments); // Amount of user's comments

        return $user;
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
        // available only for admins and user itself
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        if ($user->id != $id && $user->role != 'admin') {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        $credentials = $request->only([
            'login', 
            'password', 
            'full_name', 
            'email', 
            'profile_picture',
            'role'
        ]);

        if ($user->role != 'admin')
            unset($credentials['role']);

        $validator = Validator::make($credentials, [
            'login' => 'unique:App\Models\User,login',
            'password' => 'min:6|max:14',
            'email' => 'unique:App\Models\User,email',
            'role' => 'in:admin,user'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        // Encrypt the password
        if (isset($credentials['password']))
            $credentials['password'] = Hash::make($credentials['password']);

        $image = $credentials['profile_picture'];
        if (isset($image)) {
            $credentials['profile_picture'] = 'avatars/' . $id . '.png';

            if (file_exists($credentials['profile_picture']))
                file_put_contents($credentials['profile_picture'], base64_decode($image));
            else {
                $file = fopen($credentials['profile_picture'], "w");
                fwrite($file, base64_decode($image));
                fclose($file);
            }
        }

        $user = User::find($id);
        $user->update($credentials);
        return $user;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // available only for admins and user itself
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        if ($user->id != $id && $user->role != 'admin') {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        $path = 'avatars/' . $id . '.png';
        if (file_exists($path))
            unlink($path);

        \Auth::guard('api')->logout();
        return User::destroy($id);  
    }

    public function getStarred($id) {
        // available for all users
        $user = User::find($id);
        if (!$user->starred)
            return [];

        $user->starred = json_decode($user->starred);
        $response = array();
        foreach ($user->starred as $post_id)
            array_push($response, Post::find($post_id));
        
        return response()->json($response);
    }

    public function getPosts($id) {
        // available for all users
        $user = User::find($id);
        if (!$user)
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such user'
            ]);
        
        return Post::where('author', $user->id)->get();
    }
}
