<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\LikeRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class LikeCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class LikeCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Like::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/like');
        CRUD::setEntityNameStrings('like', 'likes');
    }

    protected function setupShowOperation() {
        CRUD::removeButton('update');
        CRUD::removeButton('delete');
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
        CRUD::addColumn([
            'name' => 'post_id',
            'type' => 'relationship',
            'label' => 'Post',
            'entity' => 'post',
            'attribute' => 'id',
            'model' => \App\Models\Post::class
        ]);
        CRUD::addColumn([
            'name' => 'comment_id',
            'type' => 'relationship',
            'label' => 'Comment',
            'entity' => 'comment',
            'attribute' => 'id',
            'model' => \App\Models\Comments::class
        ]);
        CRUD::column('type');

        CRUD::removeButton('show');
        CRUD::removeButton('update');
        CRUD::removeButton('delete');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(LikeRequest::class);

        CRUD::addField([
            'label' => 'Post',
            'type' => 'select2',
            'name' => 'post_id',
            'entity' => 'post',
            'attribute' => 'id',
            'model' => '\App\Models\Post'
        ]);
        CRUD::addField([
            'label' => 'Comment',
            'type' => 'select2',
            'name' => 'comment_id',
            'entity' => 'comment',
            'attribute' => 'id',
            'model' => '\App\Models\Comments'
        ]);
        CRUD::addField([
            'name' => 'type',
            'label' => 'Type',
            'type' => 'enum'
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
        // Get only required parameters
        $credentials = request()->only(['post_id', 'comment_id', 'type']);
        $credentials['author'] = backpack_user()->id;

        $entity;
        if (isset($credentials['post_id'])) {
            $entity = \App\Models\Post::find($credentials['post_id']); // current post
            unset($credentials['comment_id']);
        }
        else if (isset($credentials['comment_id'])) {
            $entity = \App\Models\Comments::find($credentials['comment_id']);
            unset($credentials['post_id']);
        }
        else
            abort(400, 'Please, set a post or comment id');
        
        if (isset($credentials['post_id']))
            $like = \App\Models\Like::where('author', $credentials['author'])->where('post_id', $credentials['post_id'])->get();
        else
            $like = \App\Models\Like::where('author', $credentials['author'])->where('comment_id', $credentials['comment_id'])->get();
 
        if (count($like) > 0) {
            if ($credentials['type'] === 'like') { // client sent like
                if ($like[0]['type'] === 'like') {
                    $this->unlike($credentials); // delete like
                    return redirect('/admin/like');
                }
                else { // change dislike on like
                    // Update rating of post and type of like entity
                    $entity->rating += 2;
                    $entity->save();
                    $like[0]->type = 'like';
                    $like[0]->save();
                    $user = \App\Models\User::find($entity->author);
                    $user->rating += 2;
                    $user->save();

                    return redirect('/admin/like/' . $like[0]->id . '/show');
                }
            }
            else { // client sent dislike
                if ($like[0]['type'] === 'dislike') {
                    $this->unlike($credentials); // delete dislike
                    return redirect('/admin/like');
                }
                else { // change like on dislike
                    // Update rating of post and type of like entity
                    $entity->rating -= 2;
                    $entity->save();
                    $like[0]->type = 'dislike';
                    $like[0]->save();
                    $user = \App\Models\User::find($entity->author);
                    $user->rating -= 2;
                    $user->save();

                    return redirect('/admin/like/' . $like[0]->id . '/show');
                }
            }
        }

        // Update rating
        $user = \App\Models\User::find($entity->author);
        if ($credentials['type'] === 'like') {
            $entity->rating++;
            $user->rating++;
        }
        else {
            $entity->rating--;
            $user->rating--;
        }
        $entity->save();
        $user->save();

        $like = \App\Models\Like::create($credentials);
        return redirect('/admin/like/' . $like->id . '/show');
    }

    public function unlike($credentials) {
        if (isset($credentials['post_id']))
            $like = \App\Models\Like::where('author', $credentials['author'])->where('post_id', $credentials['post_id']);
        else
            $like = \App\Models\Like::where('author', $credentials['author'])->where('comment_id', $credentials['comment_id']);

        if (count($like->get()) === 0) // nothing to delete
            return 0;

        // Update rating
        $entity;
        if (isset($credentials['post_id']))
            $entity = \App\Models\Post::find($credentials['post_id']);
        else
            $entity = \App\Models\Comments::find($credentials['comment_id']);

        $user = \App\Models\User::find($entity->author);
        if (($like->get())[0]['type'] === 'like') {
            $entity->rating--;
            $user->rating--;
        }
        else {
            $entity->rating++;
            $user->rating++;
        }
        $entity->save();
        $user->save();

        return $like->delete();
    }
}
