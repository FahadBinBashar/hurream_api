<?php

namespace App\Support;

class DocumentUpload
{
    public function __construct(
        protected string $basePath = __DIR__ . '/../../storage/documents'
    ) {
    }

    public function store(array $file, string $folder, array $options = []): string
    {
        $allowed = $options['mimes'] ?? ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = $options['max_size'] ?? 5 * 1024 * 1024;

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed');
        }

        $mime = mime_content_type($file['tmp_name']);
        if ($mime === false || !in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Unsupported file type');
        }

        if (($file['size'] ?? 0) > $maxSize) {
            throw new \RuntimeException('File too large');
        }

        $folderPath = rtrim($this->basePath, '/') . '/' . trim($folder, '/');
        if (!is_dir($folderPath) && !mkdir($folderPath, 0775, true) && !is_dir($folderPath)) {
            throw new \RuntimeException('Unable to create storage directory');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');
        $targetPath = $folderPath . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            if (!rename($file['tmp_name'], $targetPath)) {
                throw new \RuntimeException('Failed to move uploaded file');
            }
        }

        return 'documents/' . trim($folder, '/') . '/' . $safeName;
    }
}
