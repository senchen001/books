<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('books', function (Blueprint $table) {
            $table->string('approved', 1)->nullable()->after('ART');
            $table->text('izd')->nullable()->after('approved');
        });
    }

    public function down() {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['approved', 'izd']);
        });
    }
};
