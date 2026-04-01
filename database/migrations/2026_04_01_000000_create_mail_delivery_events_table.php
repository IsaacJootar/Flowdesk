<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('event_type', 100)->index();
            $table->string('message_id')->nullable()->index();
            $table->string('recipient_email')->nullable()->index();
            $table->json('tags')->nullable();
            $table->json('payload');
            $table->unsignedBigInteger('flowdesk_log_id')->nullable()->index();
            $table->string('log_source', 20)->nullable()->index();
            $table->timestamp('event_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_delivery_events');
    }
};
