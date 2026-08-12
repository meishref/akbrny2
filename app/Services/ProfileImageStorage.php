<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class ProfileImageStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk('profile_images');
    }

    public function storeFromDataUri(string $dataUri, int $userId): ?string
    {
        $segments = explode(';', $dataUri, 2);
        if (count($segments) < 2) {
            return null;
        }

        $payload = explode(',', $segments[1], 2);
        if (count($payload) < 2) {
            return null;
        }

        $base64 = base64_decode($payload[1], true);

        if ($base64 === false || $base64 === '') {
            return null;
        }

        $filename = 'img_'.time().$userId.'.png';

        if (! $this->disk()->put($filename, $base64)) {
            return null;
        }

        return $filename;
    }

    public function delete(?string $filename): void
    {
        if ($filename && $this->disk()->exists($filename)) {
            $this->disk()->delete($filename);
        }
    }
}
