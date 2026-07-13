<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eet_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->onDelete('cascade');
            $table->char('uuid_zpravy', 36);
            $table->string('fik_code', 64)->nullable();
            $table->string('bkp_code', 64)->nullable();
            $table->text('pkp_code')->nullable();
            $table->enum('eet_status', ['pending', 'sent', 'failed', 'cancelled']);
            $table->integer('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->string('endpoint_url')->nullable();
            $table->boolean('test_mode')->default(true);
            $table->text('request_xml')->nullable();
            $table->text('response_xml')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('receipt_id');
            $table->index('uuid_zpravy');
            $table->index('eet_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eet_submissions');
    }
};
