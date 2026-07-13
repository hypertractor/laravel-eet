<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('fik_code', 64)->nullable()->after('uuid');
            $table->string('bkp_code', 64)->nullable()->after('fik_code');
            $table->text('pkp_code')->nullable()->after('bkp_code');
            $table->enum('eet_status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending')->after('pkp_code');
            $table->timestamp('eet_submitted_at')->nullable()->after('eet_status');
            $table->boolean('eet_first_send')->default(true)->after('eet_submitted_at');
            $table->boolean('eet_test_mode')->default(true)->after('eet_first_send');
            $table->char('eet_uuid', 36)->nullable()->after('eet_test_mode');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn([
                'fik_code',
                'bkp_code',
                'pkp_code',
                'eet_status',
                'eet_submitted_at',
                'eet_first_send',
                'eet_test_mode',
                'eet_uuid',
            ]);
        });
    }
};
