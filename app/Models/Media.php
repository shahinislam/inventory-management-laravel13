<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'file_name', 'file_path', 'file_url', 'mime_type',
    'file_type', 'file_size', 'width', 'height',
    'alt_text', 'title', 'uploaded_by',
])]
class Media extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width'     => 'integer',
            'height'    => 'integer',
        ];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeImages($q)    { return $q->where('file_type', 'image'); }
    public function scopeDocuments($q) { return $q->where('file_type', 'document'); }

    public function isImage(): bool { return $this->file_type === 'image'; }

    public function formattedSize(): string
    {
        $kb = $this->file_size / 1024;
        if ($kb < 1024) return round($kb, 2) . ' KB';
        return round($kb / 1024, 2) . ' MB';
    }
}
