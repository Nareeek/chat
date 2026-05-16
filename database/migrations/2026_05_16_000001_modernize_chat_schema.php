<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModernizeChatSchema extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('message')->change();
            $table->index(['chat_user_id', 'created_at'], 'messages_chat_user_created_at_idx');
        });

        DB::table('chat_user')
            ->select('chat_id', 'user_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('chat_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate) {
                DB::table('chat_user')
                    ->where('chat_id', $duplicate->chat_id)
                    ->where('user_id', $duplicate->user_id)
                    ->where('id', '<>', $duplicate->keep_id)
                    ->delete();
            });

        Schema::table('chat_user', function (Blueprint $table) {
            $table->unique(['chat_id', 'user_id'], 'chat_user_chat_id_user_id_unique');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->index(['type', 'name'], 'chats_type_name_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('type')->default('human');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
            $table->dropUnique(['email']);
            $table->dropColumn('email');
            $table->dropColumn('type');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('chats_type_name_idx');
        });

        Schema::table('chat_user', function (Blueprint $table) {
            $table->dropUnique('chat_user_chat_id_user_id_unique');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_chat_user_created_at_idx');
            $table->string('message')->change();
        });
    }
}
