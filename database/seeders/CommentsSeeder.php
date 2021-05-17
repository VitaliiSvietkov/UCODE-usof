<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CommentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Comments::create([
            'author' => 3,
            'post_id' => 2,
            'content' => 'First testing comment',
        ]);
    }
}
