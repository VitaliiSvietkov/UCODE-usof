<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use App\Models\Like;
use App\Models\Categories;
use App\Models\Comments;
use App\Mail\UsofSubscribedPostMail;
use Illuminate\Support\Facades\Mail;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // get all posts
        // available for all users, even guests
        $user = $this->checkAuth();
        $posts = \DB::table('posts')->get();

        $posts = $this->applyFilters($posts, $user);
        return $posts;
    }
    private function applySorting($posts) {
        if (!isset($_GET['sort']))
            $_GET['sort'] = 'likes';
        
        switch ($_GET['sort']) {
            case 'likes':
                return array_values($posts->sortByDesc('rating')->all());
                break;
            case 'likes-asc':
                return array_values($posts->sortBy('rating')->all());
                break;
            case 'date':
                return array_values($posts->sortBy('created_at')->all());
                break;
            case 'date-desc':
                return array_values($posts->sortByDesc('created_at')->all());
                break;
            default:
                return array_values($posts->sortByDesc('rating')->all());
                break;
        }
    }
    private function applyFilters($posts, $user) {
        if (isset($_GET['dateStart'])) {
            if ($_GET['dateStart'] == $_GET['dateEnd']) // set the end of the day
                $_GET['dateEnd'] = $_GET['dateEnd'] . ' 23:59:59';
            $posts = $posts->whereBetween('created_at', [$_GET['dateStart'], $_GET['dateEnd']]);
            unset($_GET['dateStart'], $_GET['dateEnd']);
        }

        $posts = $this->applySorting($posts);

        $array = array();
        foreach ($posts as $val)
            array_push($array, $val);
        $posts = $array; // Get exactly an array

        if (isset($_GET['categories'])) {
            $categories = explode(',', $_GET['categories']);
            $categories = array_map('intval', $categories);
            $categories = array_filter($categories, function ($var) { // Get only numbers from 1
                return $var;
            });
            // If an array became associative, then
            // get only values
            $categories = array_values($categories);

            foreach ($categories as $categoryID) {
                $tmp_obj = new \stdClass();
                $tmp_obj->value = $categoryID;

                $_SESSION['categoryObj'] = $tmp_obj;
                $posts = array_filter($posts, function ($var) {
                    $tmp = json_decode($var->categories);
                    if (!$tmp)
                        return false;
                    return in_array($_SESSION['categoryObj'], $tmp) ? true:false;
                });
                unset($_SESSION['categoryObj']);
            }
            unset($_GET['categories']);
        }

        if (!$user) { // unauthorized user
            $posts = array_filter($posts, function($var) {
                if ($var->status === 'active')
                    return true;
            });
        }
        else if ($user->role === 'user') { // We can show inactive posts of current user
            $_GET['user_id'] = $user->id;
            if (!isset($_GET['status'])) {
                $posts = array_filter($posts, function ($var) {
                    if ($var->status === 'active' 
                        || ($var->author == $_GET['user_id'] && $var->status === 'inactive')
                       )
                        return true;
                });
            }
            else
                $posts = array_filter($posts, function ($var) {
                    if ($_GET['status'] === 'inactive') {
                        if ($var->author == $_GET['user_id'] && $var->status === 'inactive')
                            return true;
                    }
                    else
                        if ($var->status === 'active')
                            return true;
                });
            unset($_GET['user_id']);
        }
        else { // Admin gets all posts from the database
            if (isset($_GET['status']))
                $posts = array_filter($posts, function ($var) {
                    return $var->status === $_GET['status'] ? true:false;
                });
        }
        unset($_GET['status']);

        return array_values($posts);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // create a post
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        // Select only required fields
        $credentials = $request->only('title', 'content', 'categories');
        $credentials['author'] = $user->id;

        $validator = \Validator::make($credentials, [
            'title' => 'required|min:5|max:255|unique:App\Models\Post,title',
            'content' => 'required|min:10'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        // If user mentioned categories, make post active
        if (isset($credentials['categories']))
            $credentials['status'] = 'active';

        return Post::create($credentials);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // show a post
        // available for all users, even guests
        $post = Post::find($id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }

        $post->total_comments = count(Comments::where('post_id', $post->id)->get());
        
        if ($post->status === 'active')
            return $post;
        else {
            $user = $this->isAdmin();
            if (!$user)
                return response()->json([
                    'status' => 'FAIL',
                    'message' => 'You have no access rights'
                ]);
            
            return $post;
        }
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
        // update a post
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        $post = Post::find($id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }
        if ($post->author != $user->id && $user->role !== 'admin') {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        // Select only required fields
        $credentials = $request->only('title', 'content', 'categories', 'status', 'locked');
        if ($user->role === 'admin')
            unset($credentials['content'], $credentials['title']);
        else
            unset($credentials['locked']);

        $validator = \Validator::make($credentials, [
            'title' => 'min:5|max:255|unique:App\Models\Post,title',
            'content' => 'min:10',
            'categories' => 'array',
            'status' => 'in:active,inactive',
            'locked' => 'boolean'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        // Check if user sent 'status'
        if (isset($credentials['status'])) {
            if (!isset($credentials['categories'])) {
                if (!$post->categories)
                    return response()->json([
                        'status' => 'FAIL',
                        'message' => 'You can`t activate a post without categories'
                    ]);
            }
        }

        // Check if user sent 'categories'
        if (isset($credentials['categories'])) {
            if (!isset($credentials['status']))
                $credentials['status'] = 'active';
            if (empty($credentials['categories'])) {
                $credentials['categories'] = NULL;
                $credentials['status'] = 'inactive';
            }
        }
        
        $post->update($credentials);
        return $post;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // delete a post
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        $post = Post::find($id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }
        if ($user->role !== 'admin') {
            if ($post->author !== $user->id) {
                return response()->json([
                    'status' => 'FAIL',
                    'message' => 'You have no access rights'
                ]);
            }
        }

        // Delete post from starred
        $users_array = User::whereJsonContains('starred', (int)$id)->get();
        foreach ($users_array as $val) {
            $starred = json_decode($val->starred);
            $pos = array_search((int)$id, $starred);
            unset($starred[$pos]);
            $val->starred = $starred;
            $val->save();
        }

        $user = User::find($post->author);
        // Update users' rating
        $user->rating -= $post->rating;
        $user->save();
        $comments = Comments::where('post_id', $id)->get();
        foreach ($comments as $val) {
            $user = User::find($val->author);
            $user->rating -= $val->rating;
            $user->save();
        }
        

        return Post::destroy($id);
    }

    /**
     * Create a like/dislike under the post.
     * If like already exists, remove it.
     *
     * @param  \Illuminate\Http\Request $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function like(Request $request, $id) {
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        if (empty(Post::find($id))) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }

        // Get only required parameters
        $credentials = $request->only(['type']);
        $credentials['author'] = $user->id;

        $validator = \Validator::make($credentials, [
            'type' => 'required|in:like,dislike'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        $post = Post::find($id); // current post
        $like = Like::where('author', $credentials['author'])->where('post_id', $id)->get();
        if (count($like) > 0) {
            if ($credentials['type'] === 'like') { // client sent like
                if ($like[0]['type'] === 'like')
                    return $this->unlike($request, $id); // delete like
                else { // change dislike on like
                    // Update rating of post and type of like entity
                    $post->rating += 2;
                    $post->save();
                    $like[0]->type = 'like';
                    $like[0]->save();
                    $user = User::find($post->author);
                    $user->rating += 2;
                    $user->save();

                    return $like[0];
                }
            }
            else { // client sent dislike
                if ($like[0]['type'] === 'dislike')
                    return $this->unlike($request, $id); // delete dislike
                else { // change like on dislike
                    // Update rating of post and type of like entity
                    $post->rating -= 2;
                    $post->save();
                    $like[0]->type = 'dislike';
                    $like[0]->save();
                    $user = User::find($post->author);
                    $user->rating -= 2;
                    $user->save();

                    return $like[0];
                }
            }
        }

        $credentials['post_id'] = $id;

        // Update rating
        $user = User::find($post->author);
        if ($credentials['type'] === 'like') {
            $post->rating++;
            $user->rating++;
        }
        else {
            $post->rating--;
            $post->rating--;
        }
        $post->save();
        $user->save();

        return Like::create($credentials);
    }

    /**
     * Remove a like.
     *
     * @param  \Illuminate\Http\Request $request, int  $id
     * @return int
     */
    public function unlike(Request $request, $id) {
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        if (empty(Post::find($id))) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }

        $request = array('author' => $user->id);
        $request['post_id'] = $id;

        $like = Like::where('author', $request['author'])->where('post_id', $id);
        if (count($like->get()) === 0) // nothing to delete
            return 0;

        // Update rating
        $post = Post::find($id);
        $user = User::find($post->author);
        if (($like->get())[0]['type'] === 'like') {
            $post->rating--;
            $user->rating--;
        }
        else {
            $post->rating++;
            $user->rating++;
        }
        $post->save();
        $user->save();

        return $like->delete();
    }

    /**
     * Get all likes under the post.
     *
     * @param  \Illuminate\Http\Request $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function getLikes(Request $request, $id) {
        // available for all users
        if (empty(Post::find($id))) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }

        return Like::where('post_id', $id)->get();
    }

    /**
     * Get all categories of the post.
     *
     * @param  \Illuminate\Http\Request $request, int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories(Request $request, $id) {
        // available for all users
        $post = Post::find($id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }

        $categories = [];
        foreach ($post->categories as $category_id) {
            array_push($categories, Categories::find($category_id)[0]);
        }

        return response()->json($categories); 
    }

    /**
     * Create a comment under the post.
     * If there are users that subscribed to the post,
     * send them notification about update.
     *
     * @param  \Illuminate\Http\Request $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function createComment(Request $request, $id) {
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        $post = Post::find($id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }
        if ($post->locked)
            return response()->json([
                'status' => 'FAIL',
                'message' => 'The post is locked'
            ]);

        $validator = \Validator::make($request->all(), [
            'content' => 'required|min:10|max:255'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        $credentials = $request->only('content');
        $credentials['author'] = $user->id;
        $credentials['post_id'] = (int)$id;

        $comment = Comments::create($credentials);

        $subscribed_users = User::whereJsonContains('subscribed', $post->id)->get();
        foreach ($subscribed_users as $sub_user) {
            if ($user->id === $sub_user)
                continue;
            // Create an object for the sending email and send it
            $mailObj = new \stdClass();
            $mailObj->person = $user;
            $mailObj->post = $post;
            $mailObj->comment = $comment;
            $mailObj->receiver = $sub_user->full_name;
            $mailObj->path = "https://localhost:{$_SERVER['SERVER_PORT']}/api/UnsubscribeTest"; // link for the email
            Mail::to($sub_user->email)->send(new UsofSubscribedPostMail($mailObj));
        }

        return $comment;
    }

    /**
     * Get all comments under the post.
     *
     * @param  \Illuminate\Http\Request $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function getComments(Request $request, $id) {
        // available for all users
        $post = Post::find($id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ]);
        }

        return Comments::where('post_id', $id)->get();
    }

    /**
     * Add the post to the "Favourites".
     *
     * @param  int  $post_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function star($post_id) {
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ], 400);
        }

        $post = Post::find($post_id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ], 404);
        }

        if (!isset($user->starred)) {
            $user->starred = array((int)$post_id);
        }
        else {
            $user->starred = $starred = json_decode($user->starred);
            if (in_array((int)$post_id, $starred)) {
                for ($i = 0; $i < count($starred); $i++)
                    if ($starred[$i] === (int)$post_id) {
                        array_splice($starred, $i, 1);
                        $user->starred = $starred;
                        break;
                    }
                if (count($user->starred) === 0)
                    $user->starred = null;
            }   
            else
                $user->starred = array_merge($user->starred, array((int)$post_id));
        }

        $user->save();

        return response()->json([
            'status' => 'OK',
            'message' => 'Successfully starred'
        ]);
    }

    /**
     * Subscribe to the post updates.
     *
     * @param  int  $post_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe($post_id) {
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ], 400);
        }

        $post = Post::find($post_id);
        if (empty($post)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such post'
            ], 404);
        }

        if (!isset($user->subscribed)) {
            $user->subscribed = array((int)$post_id);
        }
        else {
            $user->subscribed = $subscribed = json_decode($user->subscribed);
            if (in_array((int)$post_id, $subscribed)) {
                for ($i = 0; $i < count($subscribed); $i++)
                    if ($subscribed[$i] === (int)$post_id) {
                        array_splice($subscribed, $i, 1);
                        $user->subscribed = $subscribed;
                        break;
                    }
                if (count($user->subscribed) === 0)
                    $user->subscribed = null;
            }   
            else
                $user->subscribed = array_merge($user->subscribed, array((int)$post_id));
        }

        $user->save();

        return response()->json([
            'status' => 'OK',
            'message' => 'Successfully subscribed'
        ]);
    }
}
