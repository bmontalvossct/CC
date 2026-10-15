<?php

namespace App\Services;

class DesktopInstaller
{
    public function localPath(): ?string
    {
        $paths = [config('desktop.installer_path')];

        if (app()->environment('local')) {
            $paths[] = base_path('dist/ClassCheck_Setup.exe');
        }

        foreach ($paths as $path) {
            if (is_string($path) && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function hostedUrl(): ?string
    {
        $url = config('desktop.installer_url');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }

    public function available(): bool
    {
        return $this->hostedUrl() !== null || $this->localPath() !== null;
    }
}
