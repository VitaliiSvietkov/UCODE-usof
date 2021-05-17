<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Models\Post;

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

/* =========================== AUTHENTICATION =========================== */
// 1. registrate a new user (POST) /api/auth/register
// 2. log in user (POST) /api/auth/login
// 3. log out user (POST) /api/auth/logout
// 4. send a reset link (POST) /api/auth/password-reset
// 5. confirm new password (POST) /api/auth/password-reset/{confirm_token}

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('/login', 'App\Http\Controllers\AuthController@login');
    Route::post('/register', 'App\Http\Controllers\AuthController@register');
    Route::post('/logout', 'App\Http\Controllers\AuthController@logout');
    Route::post('/password-reset', 'App\Http\Controllers\AuthController@passwordReset');
    Route::post('/password-reset/{confirm_token}', 'App\Http\Controllers\AuthController@passwordChange');
});

/* ============================================================= */

/* =========================== USERS =========================== */
// 1. get all users (GET) /api/users
// 2. get user data (GET) /api/users/{id}
// 3. create a user (POST) /api/users
// 4. upload an avatar (POST) /api/users/avatar
// 5. update user data (PATCH) /api/users/{id}
// 6. delete user (DELETE) /api/users/{id}

Route::resource('users', 'App\Http\Controllers\UsersController');

// CREATIVE
Route::group([
    'middleware' => 'api',
    'prefix' => 'users'
], function ($router) {
    Route::get('/{id}/starred', 'App\Http\Controllers\UsersController@getStarred');
    Route::get('/{id}/subscribed', 'App\Http\Controllers\UsersController@getSubscribed');
    Route::get('/{id}/posts', 'App\Http\Controllers\UsersController@getPosts');
});
/* ============================================================= */

/* =========================== POSTS =========================== */
// 1. get all posts (GET) /api/posts
// 2. get one post (GET) /api/posts/{id}
// 3. get all comments (GET) /api/posts/{id}/comments
// 4. create a comment (POST) /api/posts/{id}/comments
// 5. get all categories (GET) /api/posts/{id}/categories
// 6. get all likes (GET) /api/posts/{id}/like
// 7. create a post (POST) /api/posts
// 8. create a like (POST) /api/posts/{id}/like
// 9. update a post (PATCH) /api/posts/{id}
// 10. delete a post (DELETE) /api/posts/{id}
// 11. delete a like (DELETE) /api/posts/{id}/like

Route::resource('posts', 'App\Http\Controllers\PostController');

Route::post('posts/{id}/like', [App\Http\Controllers\PostController::class, 'like']);
Route::delete('posts/{id}/like', [App\Http\Controllers\PostController::class, 'unlike']);
Route::get('posts/{id}/like', [App\Http\Controllers\PostController::class, 'getLikes']);
Route::get('posts/{id}/categories', [App\Http\Controllers\PostController::class, 'getCategories']);
Route::post('posts/{id}/comments', [App\Http\Controllers\PostController::class, 'createComment']);
Route::get('posts/{id}/comments', [App\Http\Controllers\PostController::class, 'getComments']);

// CREATIVE
Route::post('posts/{id}/star', [App\Http\Controllers\PostController::class, 'star']);
Route::post('posts/{id}/subscribe', [App\Http\Controllers\PostController::class, 'subscribe']);
/* ============================================================= */

/* =========================== CATEGORIES =========================== */
// 1. get all categories (GET) /api/categories
// 2. get category data (GET) /api/categories/{id}
// 3. get all posts under category (GET) /api/categories/{id}/posts
// 4. create a category (POST) /api/categories
// 5. update category data (PATCH) /api/categories/{id}
// 6. delete a category (DELETE) /api/categories/{id}

Route::resource('categories', 'App\Http\Controllers\CategoriesController');

Route::get('categories/{id}/posts', [App\Http\Controllers\CategoriesController::class, 'getPosts']);

/* ============================================================= */

/* =========================== COMMENTS =========================== */
// 1. get comment (GET) /api/comments/{id}
// 2. get all likes (GET) /api/comments/{id}/like
// 3. create a like (POST) /api/comments/{id}/like
// 4. update comment (PATCH) /api/comments/{id}
// 5. delete a comment (DELETE) /api/comments/{id}
// 6. delete a like (DELETE) /api/comments/{id}/like

Route::resource('comments', 'App\Http\Controllers\CommentsController');

Route::post('comments/{id}/like', [App\Http\Controllers\CommentsController::class, 'like']);
Route::delete('comments/{id}/like', [App\Http\Controllers\CommentsController::class, 'unlike']);
Route::get('comments/{id}/like', [App\Http\Controllers\CommentsController::class, 'getLikes']);

/* ============================================================= */

Route::middleware('auth:api')->get('/user', function () {
    $user = auth()->user();

    return $user;
});
