<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = new \stdClass();
        $categories->value = 1;
        \App\Models\Post::create([
            'author' => 1,
            'title' => 'My first post',
            'content' => 'First testing content',
            'categories' => [$categories],
            'status' => 'active'
        ]);

        $categories->value = 2;
        \App\Models\Post::create([
            'author' => 2,
            'title' => 'My second post',
            'content' => 'Second testing content',
            'categories' => [$categories],
            'status' => 'active'
        ]);

        \App\Models\Post::create([
          'author' => 3,
          'title' => 'My third post',
          'content' => 'Third testing content',
      ]);
    }
}
