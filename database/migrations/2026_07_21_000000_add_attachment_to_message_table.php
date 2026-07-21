cat > database/migrations/2026_07_21_000000_add_attachment_to_message_table.php << 'EOF'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('message', function (Blueprint $table) {
            $table->string('Attachment')->nullable()->after('body');
            $table->string('AttachmentType')->nullable()->after('Attachment');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message', function (Blueprint $table) {
            $table->dropColumn(['Attachment', 'AttachmentType']);
        });
    }
};
