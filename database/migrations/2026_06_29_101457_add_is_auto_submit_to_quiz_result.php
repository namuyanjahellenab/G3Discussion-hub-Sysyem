<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('QuizResult', function (Blueprint $table) {
        $table->boolean('IsAutoSubmit')->default(0);
    });
}

public function down()
{
    Schema::table('QuizResult', function (Blueprint $table) {
        $table->dropColumn('IsAutoSubmit');
    });
}
};
