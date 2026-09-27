<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id', 64)->index();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('file_name');
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('total_chunks');
            $table->unsignedBigInteger('chunk_size');
            $table->string('chunk_path', 500);
            $table->timestamps();

            $table->unique(['upload_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_chunks');
    }
};
