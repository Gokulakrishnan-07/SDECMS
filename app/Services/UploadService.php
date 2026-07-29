<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Secure file upload handling: extension + MIME whitelist, size limit,
 * randomized filenames, storage outside the web root.
 */
class UploadService
{
    /**
     * Store an uploaded file and return [path, originalName], or throw.
     *
     * @param array $file entry from $_FILES
     * @throws \RuntimeException on validation failure
     */
    public static function store(array $file, string $subdir = 'attachments'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed (error code ' . ($file['error'] ?? '?') . ').');
        }

        $maxSize = (int) config('uploads.max_size');
        if (($file['size'] ?? 0) > $maxSize) {
            throw new \RuntimeException('File exceeds the maximum size of ' . round($maxSize / 1048576, 1) . ' MB.');
        }

        $original = basename((string) $file['name']);
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, config('uploads.allowed_ext'), true)) {
            throw new \RuntimeException('File type .' . $ext . ' is not allowed.');
        }

        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($mime, config('uploads.allowed_mimes'), true)) {
            throw new \RuntimeException('File content type is not allowed.');
        }

        $dir = rtrim(config('uploads.path'), '/\\') . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the upload directory.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name)) {
            throw new \RuntimeException('Could not save the uploaded file.');
        }

        return [$subdir . '/' . $name, $original];
    }
}
