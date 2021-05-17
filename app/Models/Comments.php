<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    use HasFactory;

    protected $fillable = [
        'author',
        'content',
        'post_id',
        'rating'
    ];

    protected $casts = [
        'rating' => 'integer'
    ];

    public function post() {
        return $this->belongsTo('\App\Models\Post');
    }
}
