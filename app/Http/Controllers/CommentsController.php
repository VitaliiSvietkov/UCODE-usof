<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comments;
use App\Models\Like;
use App\Models\User;

class CommentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // get all comments
        // availabel only for admins
        $user = $this->isAdmin();
        if (!$user)
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        
        return Comments::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // create a comment
        // return Comments::create($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // show a comment
        // available for all users
        return Comments::find($id);
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
        // update a comment
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        $comment = Comments::find($id);
        if (empty($comment)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such comment'
            ]);
        }
        if ($comment->author !== $user->id) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'You have no access rights'
            ]);
        }

        // Select only required fields
        $credentials = $request->only('content');

        $validator = \Validator::make($credentials, [
            'content' => 'required|min:10',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }

        $comment->update($credentials);
        return $comment;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // delete a comment
        // available only for authorized users
        $user = $this->checkAuth();
        if (!$user) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Unauthorized'
            ]);
        }

        $comment = Comments::find($id);
        if (empty($comment)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such comment'
            ]);
        }
        if ($user->role !== 'admin') {
            if ($comment->author !== $user->id) {
                return response()->json([
                    'status' => 'FAIL',
                    'message' => 'You have no access rights'
                ]);
            }
        }

        $user = User::find($comment->author);
        $user->rating -= $comment->rating;
        $user->save();

        return Comments::destroy($id);
    }

    /**
     * Create a like/dislike under the comment.
     * If like already exists, remove it.
     *
     * @param  \Illuminate\Http\Request, int  $id
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

        if (empty(Comments::find($id))) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such comment'
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

        $comment = Comments::find($id); // current comment
        $user = User::find($comment->author);
        $like = Like::where('author', $credentials['author'])->where('comment_id', $id)->get();
        if (count($like) > 0) {
            if ($credentials['type'] === 'like') { // client sent like
                if ($like[0]['type'] === 'like')
                    return $this->unlike($request, $id); // delete like
                else { // change dislike on like
                    // Update rating of comment and type of like entity
                    $comment->rating += 2;
                    $comment->save();
                    $like[0]->type = 'like';
                    $like[0]->save();
                    $user->rating += 2;
                    $user->save();

                    return $like[0];
                }
            }
            else { // client sent dislike
                if ($like[0]['type'] === 'dislike')
                    return $this->unlike($request, $id); // delete dislike
                else { // change like on dislike
                    // Update rating of comment and type of like entity
                    $comment->rating -= 2;
                    $comment->save();
                    $like[0]->type = 'dislike';
                    $like[0]->save();
                    $user->rating -= 2;
                    $user->save();

                    return $like[0];
                }
            }
        }

        $credentials['comment_id'] = $id;

        // Update rating
        if ($credentials['type'] === 'like') {
            $comment->rating++;
            $user->rating++;
        }
        else {
            $comment->rating--;
            $user->rating--;
        }
        $comment->save();
        $user->save();

        return Like::create($credentials);
    }

    /**
     * Remove a like.
     *
     * @param  \Illuminate\Http\Request, int  $id
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

        if (empty(Comments::find($id))) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such comment'
            ]);
        }

        $request = array('author' => $user->id);
        $request['comment_id'] = $id;

        $like = Like::where('author', $request['author'])->where('comment_id', $id);
        if (count($like->get()) === 0) // nothing to delete
            return 0;

        // Update rating
        $comment = Comments::find($id);
        $user = User::find($comment->author);
        if (($like->get())[0]['type'] === 'like') {
            $comment->rating--;
            $user->rating--;
        }
        else {
            $comment->rating++;
            $user->rating++;
        }
        $comment->save();
        $user->save();

        return $like->delete();
    }

    /**
     * Get all likes under the comment.
     *
     * @param  \Illuminate\Http\Request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function getLikes(Request $request, $id) {
        // available for all users
        if (empty(Comments::find($id))) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no such comment'
            ]);
        }

        return Like::where('comment_id', (int)$id)->get();
    }
}
