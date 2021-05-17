<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CommentsRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use App\Mail\UsofSubscribedPostMail;
use Illuminate\Support\Facades\Mail;

/**
 * Class CommentsCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class CommentsCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation { destroy as traitDestroy; }


    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Comments::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/comments');
        CRUD::setEntityNameStrings('comments', 'comments');
    }

    protected function setupShowOperation() {
        CRUD::removeButton('update');
        CRUD::setFromDB();
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('id');
        CRUD::column('author');
        CRUD::column('post_id');
        CRUD::column('rating');

        CRUD::removeButton('update');

        CRUD::modifyColumn('post_id', [
            'name' => 'post_id',
            'type' => 'relationship',
            'label' => 'Post',
            'entity' => 'post',
            'attribute' => 'id',
            'model' => \App\Models\Post::class
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(CommentsRequest::class);

        CRUD::field('content');
        CRUD::addField([
            'label' => 'Post',
            'type' => 'select2',
            'name' => 'post_id',
            'entity' => 'post',
            'attribute' => 'id',
            'model' => '\App\Models\Post'
        ]);
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    public function store() {
        $credentials = request()->only('content', 'post_id');
        $credentials['author'] = backpack_user()->id;

        $post = \App\Models\Post::find($credentials['post_id']);

        if ($post->locked)
            abort(400, 'The post has been locked');

        $validator = \Validator::make($credentials, [
            'content' => 'required|min:10|max:255'
        ]);
        if ($validator->fails())
            abort(400, ($validator->errors())->first());

        $comment = \App\Models\Comments::create($credentials);

        $subscribed_users = \App\Models\User::whereJsonContains('subscribed', $post->id)->get();
        foreach ($subscribed_users as $sub_user) {
            if (backpack_user()->id === $sub_user)
                continue;
            // Create an object for the sending email and send it
            $mailObj = new \stdClass();
            $mailObj->person = backpack_user();
            $mailObj->post = $post;
            $mailObj->comment = $comment;
            $mailObj->receiver = $sub_user->full_name;
            $mailObj->path = "https://localhost:{$_SERVER['SERVER_PORT']}/api/UnsubscribeTest"; // link for the email
            Mail::to($sub_user->email)->send(new UsofSubscribedPostMail($mailObj));
        }

        return redirect('/admin/comments/' . $comment->id . '/show');
    }

    public function destroy($id) {
        $this->crud->hasAccessOrFail('delete');

        $comment = CRUD::getCurrentEntry();
        $user = \App\Models\User::find($comment->author);

        // Update user's rating
        $user->rating -= $comment->rating;
        $user->save();

        return $this->crud->delete($id);
    }
}
