<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PostsRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use \App\Models\User;

/**
 * Class PostsCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PostsCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Post::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/posts');
        CRUD::setEntityNameStrings('posts', 'posts');
    }

    public function setupShowOperation() {
        CRUD::addColumn('id');
        CRUD::setFromDb();
        CRUD::addColumn('locked');
        CRUD::modifyColumn('content', [
            'type' => 'markdown'
        ]);
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {        
        CRUD::addColumn('id');
        CRUD::setFromDb(); // columns

        CRUD::removeColumn('content');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(PostsRequest::class);

        CRUD::setFromDb(); // fields
        CRUD::modifyField('status', [
            'type' => 'enum',
        ]);

        CRUD::removeField('rating');
        CRUD::removeField('author');
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::addField([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'enum'
        ]);
        CRUD::addField('locked');
        CRUD::addField('categories');
    }

    public function store()
    {
        // Select only required fields
        $credentials = request()->only('title', 'content', 'categories');
        $credentials['author'] = backpack_user()->id;
        
        $validator = \Validator::make($credentials, [
            'title' => 'required|min:5|max:255|unique:App\Models\Post,title',
            'content' => 'required|min:10'
        ]);
        if ($validator->fails())
            abort(400, ($validator->errors())->first());

        // If user mentioned categories, make post active
        if (isset($credentials['categories'])) {
            $credentials['categories'] = json_decode($credentials['categories']);
            for ($i = 0; $i < count($credentials['categories']); ++$i)
                $credentials['categories'][$i]->value = (int)$credentials['categories'][$i]->value;
            $credentials['status'] = 'active';
        }

        $post = \App\Models\Post::create($credentials);
        return redirect('/admin/posts/' . $post->id . '/show');
    }

    public function update()
    {
        // Select only required fields
        $credentials = request()->only('status', 'categories', 'locked');
        $post = CRUD::getCurrentEntry();

        // Check if user sent 'status'
        if (isset($credentials['status']) && $credentials['status'] === 'active') {
            if (!isset($credentials['categories'])) {
                if (!$post->categories)
                    abort(400, 'You can`t activate a post without categories');
            }
        }

        // Check if user sent 'categories'
        if (!isset($credentials['categories'])) {
            $credentials['categories'] = NULL;
            $credentials['status'] = 'inactive';
        }
        else {
            $credentials['categories'] = json_decode($credentials['categories']);
            for ($i = 0; $i < count($credentials['categories']); ++$i)
                $credentials['categories'][$i]->value = (int)$credentials['categories'][$i]->value;
        }
        
        $post->update($credentials);
        return redirect('/admin/posts/' . $post->id . '/show');
    }

    public function destroy($id) {
        $this->crud->hasAccessOrFail('delete');

        $post = CRUD::getCurrentEntry();
        $user = \App\Models\User::find($post->author);

        // Update users' rating
        $user->rating -= $post->rating;
        $user->save();
        $comments = \App\Models\Comments::where('post_id', $id)->get();
        foreach ($comments as $val) {
            $user = User::find($val->author);
            $user->rating -= $val->rating;
            $user->save();
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

        return $this->crud->delete($id);
    }
}
