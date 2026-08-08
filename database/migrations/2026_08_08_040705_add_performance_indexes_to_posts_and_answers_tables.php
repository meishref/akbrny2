<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive performance indexes (Optimization #5).
     *
     * posts (user_id, is_read, type):
     *   - AppServiceProvider view composer unread COUNT
     *   - UsersController dashboard mark-as-read UPDATE
     *
     * answers (user_id, post_id):
     *   - AjaxController message reply duplicate check (D2)
     *   - AjaxController profileSendVote duplicate vote check (D12)
     *   - Authenticated profile poll viewer vote lookup
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'is_read', 'type'], 'posts_user_id_is_read_type_index');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->index(['user_id', 'post_id'], 'answers_user_id_post_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_user_id_is_read_type_index');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex('answers_user_id_post_id_index');
        });
    }
};
