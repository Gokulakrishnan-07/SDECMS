<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Secure uploads stored outside the public web root. The generic method keeps
 * the existing upload contract; sanction uploads use a stricter profile with
 * extension, MIME and file-signature checks.
 */
class UploadService
{
    /** @return array{0:string,1:string} relative path and original filename */
    public static function store(array $file, string $subdir = 'attachments'): array
    {
        $meta = self::storeValidated($file, $subdir, config('uploads.allowed_ext'), false);
        return [$meta['file_path'], $meta['original_filename']];
    }

    /**
     * Store a sanction attachment and return all metadata required by its
     * database record. Only PDF, Word and image formats are accepted.
     *
     * @return array{original_filename:string,stored_filename:string,file_path:string,file_extension:string,mime_type:string,file_size:int,full_path:string}
     */
    public static function storeSanctionAttachment(array $file): array
    {
        return self::storeValidated(
            $file,
            'sanctions',
            config('uploads.sanction_allowed_ext'),
            true
        );
    }

    /** Validate a prospective sanction attachment without moving it. */
    public static function validateSanctionAttachment(array $file): void
    {
        self::validate($file, config('uploads.sanction_allowed_ext'), true);
    }

    /** Remove a stored file only when it remains beneath the configured root. */
    public static function deleteStored(string $relativePath): void
    {
        $root = realpath(rtrim(config('uploads.path'), '/\\'));
        if ($root === false) {
            return;
        }
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $resolved = realpath($candidate);
        if ($resolved !== false && str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) && is_file($resolved)) {
            @unlink($resolved);
        }
    }

    /** @return array{original_filename:string,stored_filename:string,file_path:string,file_extension:string,mime_type:string,file_size:int,full_path:string} */
    private static function storeValidated(array $file, string $subdir, array $extensions, bool $strictSignature): array
    {
        [$original, $extension, $mime, $size] = self::validate($file, $extensions, $strictSignature);

        $safeSubdir = trim(str_replace(['..', '\\'], ['', '/'], $subdir), '/');
        if ($safeSubdir === '') {
            throw new \RuntimeException('Invalid upload destination.');
        }
        $dir = rtrim(config('uploads.path'), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safeSubdir);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the upload directory.');
        }

        $stored = bin2hex(random_bytes(24)) . '.' . $extension;
        $fullPath = $dir . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new \RuntimeException('Could not save the uploaded file.');
        }

        return [
            'original_filename' => $original,
            'stored_filename'   => $stored,
            'file_path'         => $safeSubdir . '/' . $stored,
            'file_extension'    => $extension,
            'mime_type'         => $mime,
            'file_size'         => $size,
            'full_path'         => $fullPath,
        ];
    }

    /** @return array{0:string,1:string,2:string,3:int} */
    private static function validate(array $file, array $extensions, bool $strictSignature): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed or no file was supplied.');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Invalid uploaded file.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('Empty files are not allowed.');
        }
        $maxSize = (int) config('uploads.max_size');
        if ($size > $maxSize) {
            throw new \RuntimeException('File exceeds the maximum size of ' . round($maxSize / 1048576, 1) . ' MB.');
        }

        $original = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($original === '' || $extension === '' || !in_array($extension, $extensions, true)) {
            throw new \RuntimeException('This file type is not allowed.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if ($mime === '') {
            throw new \RuntimeException('Could not verify the file content type.');
        }

        if ($strictSignature) {
            self::assertSanctionFileSignature($file['tmp_name'], $extension, $mime);
        } elseif (!in_array($mime, config('uploads.allowed_mimes'), true)) {
            throw new \RuntimeException('File content type is not allowed.');
        }

        return [$original, $extension, $mime, $size];
    }

    private static function assertSanctionFileSignature(string $path, string $extension, string $mime): void
    {
        $head = file_get_contents($path, false, null, 0, 8);
        if ($head === false) {
            throw new \RuntimeException('Could not inspect the uploaded file.');
        }

        if ($extension === 'pdf') {
            if ($mime !== 'application/pdf' || !str_starts_with($head, '%PDF-')) {
                throw new \RuntimeException('The uploaded PDF file is invalid.');
            }
            return;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $image = @getimagesize($path);
            $expected = $extension === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
            if ($image === false || ($image[2] ?? 0) !== $expected || ($image['mime'] ?? '') !== $mime) {
                throw new \RuntimeException('The uploaded image file is invalid or does not match its extension.');
            }
            return;
        }

        if ($extension === 'doc') {
            $oleHeader = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
            if ($head !== $oleHeader || !in_array($mime, ['application/msword', 'application/CDFV2', 'application/octet-stream'], true)) {
                throw new \RuntimeException('The uploaded DOC file is invalid.');
            }
            return;
        }

        if ($extension === 'docx') {
            // A DOCX is an Office Open XML ZIP container. When zip is
            // installed, also require its mandatory internal entries; on a
            // minimal PHP install the ZIP signature + MIME check still reject
            // renamed scripts and preserve DOCX support.
            if (!str_starts_with($head, "PK\x03\x04")) {
                throw new \RuntimeException('The uploaded DOCX file is invalid.');
            }
            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                if ($zip->open($path) !== true
                    || $zip->locateName('[Content_Types].xml') === false
                    || $zip->locateName('word/document.xml') === false) {
                    if ($zip->status === \ZipArchive::ER_OK) {
                        $zip->close();
                    }
                    throw new \RuntimeException('The uploaded DOCX file is invalid.');
                }
                $zip->close();
            }
            if (!in_array($mime, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/x-zip-compressed',
            ], true)) {
                throw new \RuntimeException('The uploaded DOCX file has an invalid content type.');
            }
        }
    }
}
