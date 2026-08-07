<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('username')->unique();
            $table->string('image')->nullable();
            $table->string('text_profile')->nullable();
            $table->string('ip_address', 45)->nullable();



            $table->integer('visitors')->default(0);
            $table->integer('active')->default(1); // one is active // 2 is block

            $table->boolean('is_public')->default(1);
            $table->boolean('accept_posts')->default(1);
            $table->boolean('show_zwar')->default(1);
            $table->boolean('active_notification')->default(1);



            $table->string('token_notification')->nullable();


            $table->string('web')->nullable();
            $table->string('twitter')->nullable();
            $table->string('instagram')->nullable();
            $table->string('youtube')->nullable();
            $table->string('snapchat')->nullable();
            $table->string('telegram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();





            $table->rememberToken();
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
    }
}
