<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('login')->unique();
            $table->string('password');
            $table->string('full_name')->default(' ');
            $table->string('email')->unique();
            $table->text('profile_picture')->nullable();
            $table->integer('rating')->default(0);
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->json('starred')->nullable();
            $table->json('subscribed')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->text('remember_token')->nullable();
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
        Schema::dropIfExists('users');
        if (!file_exists(__DIR__.'/../../public/avatars'))
            mkdir(__DIR__.'/../../public/avatars');
        $dir = opendir(__DIR__.'/../../public/avatars');
        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..')
                continue;
            unlink(__DIR__.'/../../public/avatars/'.$file);
        }
        closedir($dir);
    }
}
