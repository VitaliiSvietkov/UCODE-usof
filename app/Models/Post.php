<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory, CrudTrait;

    protected $table = 'posts';

    protected $fillable = [
        'author',
        'title',
        'rating',
        'content',
        'categories',
        'status',
        'locked'
    ];

    protected $casts = [
        'rating' => 'integer',
        'categories' => 'array',
        'status' => 'string'
    ];
}
