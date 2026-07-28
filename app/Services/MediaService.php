<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

class MediaService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(Driver::class);
    }

    /**
     * Extensions we are willing to write to disk, keyed by the MIME type the
     * server itself detected from the file's contents.
     *
     * The client-supplied extension is never used: it is attacker-controlled,
     * and files land on the public disk where a dangerous extension could be
     * served as executable code or as active content (SVG/HTML).
     */
    private const ALLOWED_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    public function upload(UploadedFile $file, int $userId): Media
    {
        $mimeType = $file->getMimeType();
        $isImage = str_starts_with($mimeType, 'image/');

        if (! $isImage && ! isset(self::ALLOWED_EXTENSIONS[$mimeType])) {
            throw new InvalidArgumentException("Unsupported file type: {$mimeType}");
        }

        // Images are re-encoded to WebP below, which discards any embedded
        // payload; other types get an extension derived from the detected MIME.
        $extension = $isImage ? 'webp' : self::ALLOWED_EXTENSIONS[$mimeType];

        $fileName = Str::uuid().'.'.$extension;
        $folder = 'media/'.now()->format('Y/m');

        if ($isImage) {
            return $this->uploadImage($file, $fileName, $folder, $userId);
        }

        return $this->uploadFile($file, $fileName, $folder, $userId);
    }

    private function uploadImage(UploadedFile $file, string $fileName, string $folder, int $userId): Media
    {
        $image = $this->manager->decode($file->getRealPath());

        // Resize if too large (max 1920px wide)
        if ($image->width() > 1920) {
            $image->scale(width: 1920);
        }

        $width = $image->width();
        $height = $image->height();

        // Convert to WebP and save
        $path = $folder.'/'.$fileName;
        $encoded = $image->encode(new WebpEncoder(quality: 85));
        Storage::disk('public')->put($path, (string) $encoded);

        // Generate thumbnails
        $this->generateThumbnails($file, $folder, $fileName);

        $fileSize = Storage::disk('public')->size($path);

        return Media::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_url' => Storage::disk('public')->url($path),
            'mime_type' => 'image/webp',
            'file_type' => 'image',
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'uploaded_by' => $userId,
        ]);
    }

    private function generateThumbnails(UploadedFile $file, string $folder, string $fileName): void
    {
        $sizes = ['sm' => 150, 'md' => 400, 'lg' => 800];

        foreach ($sizes as $size => $width) {
            $thumb = $this->manager->decode($file->getRealPath());
            $thumb->scale(width: $width);
            $encoded = $thumb->encode(new WebpEncoder(quality: 80));
            $thumbPath = $folder.'/thumbs/'.$size.'_'.$fileName;
            Storage::disk('public')->put($thumbPath, (string) $encoded);
        }
    }

    private function uploadFile(UploadedFile $file, string $fileName, string $folder, int $userId): Media
    {
        $path = $file->storeAs($folder, $fileName, 'public');

        return Media::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_url' => Storage::disk('public')->url($path),
            'mime_type' => $file->getMimeType(),
            'file_type' => $this->getFileType($file->getMimeType()),
            'file_size' => $file->getSize(),
            'width' => null,
            'height' => null,
            'uploaded_by' => $userId,
        ]);
    }

    public function delete(Media $media): void
    {
        Storage::disk('public')->delete($media->file_path);

        if ($media->file_type === 'image') {
            $folder = dirname($media->file_path);
            $fileName = basename($media->file_path);
            foreach (['sm', 'md', 'lg'] as $size) {
                Storage::disk('public')->delete($folder.'/thumbs/'.$size.'_'.$fileName);
            }
        }

        $media->delete();
    }

    public function getThumbnailUrl(Media $media, string $size = 'md'): string
    {
        if ($media->file_type !== 'image') {
            return $media->file_url;
        }

        $folder = dirname($media->file_path);
        $fileName = basename($media->file_path);
        $thumbPath = $folder.'/thumbs/'.$size.'_'.$fileName;

        return Storage::disk('public')->exists($thumbPath)
            ? Storage::disk('public')->url($thumbPath)
            : $media->file_url;
    }

    private function getFileType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            $mimeType === 'application/pdf' => 'document',
            default => 'other',
        };
    }
}
