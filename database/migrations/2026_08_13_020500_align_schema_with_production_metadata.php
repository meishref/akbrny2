<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align local schema with production metadata (localh202ost.sql / akbrny2_home2).
 *
 * Safe for databases that already have these columns (production): uses hasColumn/hasTable.
 * Do NOT run migrate:fresh on production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'user_pass')) {
                $table->string('user_pass')->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'android_token')) {
                $table->text('android_token')->nullable()->after('token_notification');
            }
            if (! Schema::hasColumn('users', 'tiktok')) {
                $table->string('tiktok', 250)->nullable()->after('linkedin');
            }
            if (! Schema::hasColumn('users', 'words_block')) {
                $table->mediumText('words_block')->nullable()->after('tiktok');
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'post_is_fav')) {
                $table->integer('post_is_fav')->default(0)->after('is_read');
            }
            if (! Schema::hasColumn('posts', 'post_time')) {
                $table->string('post_time', 50)->nullable()->after('post_is_fav');
            }
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('token')->nullable();
                $table->string('device')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->index('user_id', 'notifications_user_id_foreign');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['user_pass', 'android_token', 'tiktok', 'words_block'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            foreach (['post_is_fav', 'post_time'] as $column) {
                if (Schema::hasColumn('posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Do not drop production notifications table automatically.
        // Schema::dropIfExists('notifications');
    }
};
