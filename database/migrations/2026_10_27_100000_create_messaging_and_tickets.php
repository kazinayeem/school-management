<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->string('subject', 200);
            $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('last_read_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->boolean('marked_unread')->default(false);
            $t->unique(['conversation_id', 'user_id']);
            $t->index('user_id');
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->longText('body');
            $t->string('body_text', 300)->nullable();
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->string('title', 200);
            $t->string('category', 40)->index();
            $t->string('priority', 10)->default('normal')->index();
            $t->string('status', 20)->default('submitted')->index();
            $t->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('recipient_role', 20)->default('admin');
            $t->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $t->longText('description');
            $t->longText('resolution')->nullable();
            $t->timestamp('last_activity_at')->nullable()->index();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->boolean('escalated')->default(false);
            $t->timestamps();
            $t->index(['requester_id', 'status']);
            $t->index(['assigned_to', 'status']);
        });

        Schema::create('ticket_replies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->longText('body');
            $t->boolean('is_internal')->default(false);
            $t->timestamps();
            $t->index(['ticket_id', 'id']);
        });

        Schema::create('ticket_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 30);
            $t->string('from_value')->nullable();
            $t->string('to_value')->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['ticket_id', 'id']);
        });

        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->string('attachable_type', 20);
            $t->unsignedBigInteger('attachable_id');
            $t->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $t->string('name', 255);
            $t->string('path');
            $t->string('mime', 100);
            $t->unsignedInteger('size');
            $t->timestamps();
            $t->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 30);
            $t->string('title', 200);
            $t->string('body', 300)->nullable();
            $t->string('url', 200);
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['app_notifications', 'attachments', 'ticket_events', 'ticket_replies', 'tickets', 'messages', 'conversation_participants', 'conversations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
