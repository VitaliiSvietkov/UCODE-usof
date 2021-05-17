<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('author')->nullable()->constrained('users')->onDelete('set null');
            $table->string('title')->unique();
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->boolean('locked')->default(false);
            $table->integer('rating')->default(0);
            $table->text('content');
            $table->json('categories')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
