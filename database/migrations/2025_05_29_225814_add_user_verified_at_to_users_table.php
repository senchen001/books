<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserVerifiedAtToUsersTable extends Migration
{
    /**
     * Добавляет колонку user_verified_at в таблицу users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('user_verified_at')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Откатывает добавление колонки user_verified_at.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_verified_at');
        });
    }
}
