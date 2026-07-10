<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Reply', function (Blueprint $table) {
            if (!Schema::hasColumn('Reply', 'QuotedReplyID')) {
                $table->unsignedBigInteger('QuotedReplyID')->nullable()->after('IsAccepted');
                $table->foreign('QuotedReplyID')->references('ReplyID')->on('Reply')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('Reply', function (Blueprint $table) {
            $table->dropForeign(['QuotedReplyID']);
            $table->dropColumn('QuotedReplyID');
        });
    }
};
