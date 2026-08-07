<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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

            $table->increments('id');


            $table->integer('type')->default(0); // 0 is message and 1 is poll
            $table->longText('body');

            $table->text('answer1')->nullable();
            $table->text('answer2')->nullable();
            $table->text('answer3')->nullable();
            $table->text('answer4')->nullable(); // to poll only


            $table->boolean('is_public')->default(0);
            $table->boolean('is_read')->default(0);


            $table->string('ip')->nullable();

            $table->boolean('is_active')->default(1);// to poll only





            $table->integer('user_id')->unsigned();




            $table->timestamps();
        });

        Schema::table('posts', function($table) {
           // $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

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
