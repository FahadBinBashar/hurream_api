<?php

namespace App\Core;

use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\UploadedFile;

class Request
{
    public function __construct(private IlluminateRequest $baseRequest)
    {
    }

    public static function capture(): self
    {
        return new self(IlluminateRequest::capture());
    }

    public static function fromIlluminate(IlluminateRequest $request): self
    {
        return new self($request);
    }

    public function method(): string
    {
        return strtoupper($this->baseRequest->getMethod());
    }

    public function path(): string
    {
        $path = $this->baseRequest->path();

        return '/' . ltrim($path, '/');
    }

    public function input(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->all();
        }

        return $this->baseRequest->input($key, $default);
    }

    public function all(): array
    {
        return $this->baseRequest->all();
    }

    public function header(string $key, $default = null)
    {
        return $this->baseRequest->header($key, $default);
    }

    public function files(): array
    {
        return array_map(function ($file) {
            if ($file instanceof UploadedFile) {
                return $this->normalizeUploadedFile($file);
            }

            if (is_array($file)) {
                return array_map(fn($inner) => $inner instanceof UploadedFile ? $this->normalizeUploadedFile($inner) : $inner, $file);
            }

            return $file;
        }, $this->baseRequest->allFiles());
    }

    public function file(string $key): mixed
    {
        $file = $this->baseRequest->file($key);

        if ($file instanceof UploadedFile) {
            return $this->normalizeUploadedFile($file);
        }

        if (is_array($file)) {
            return array_map(fn($inner) => $inner instanceof UploadedFile ? $this->normalizeUploadedFile($inner) : $inner, $file);
        }

        return $file;
    }

    public function hasFile(string $key): bool
    {
        return $this->baseRequest->hasFile($key);
    }

    public function ip(): ?string
    {
        return $this->baseRequest->ip();
    }

    public function userAgent(): ?string
    {
        return $this->baseRequest->userAgent();
    }

    public function getIlluminate(): IlluminateRequest
    {
        return $this->baseRequest;
    }

    protected function normalizeUploadedFile(UploadedFile $file): array
    {
        return [
            'name' => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
            'size' => $file->getSize(),
            'error' => $file->getError(),
            'type' => $file->getClientMimeType(),
        ];
    }
}
