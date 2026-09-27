<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'task_id',
        'file_name',
        'chunk_index',
        'total_chunks',
        'chunk_size',
        'chunk_path',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
