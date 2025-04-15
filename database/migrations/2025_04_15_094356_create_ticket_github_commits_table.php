<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('ticket_github_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->string('sha');
            $table->string('author');
            $table->text('message');
            $table->timestamp('committed_at');
            $table->string('branch');
            $table->timestamps();

            // Add a unique constraint to prevent duplicate commits
            $table->unique(['ticket_id', 'sha']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticket_github_commits');
    }
};
