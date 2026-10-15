<?php

namespace App\Http\Controllers;

use App\Services\DesktopInstaller;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DesktopDownloadController extends Controller
{
    public function __invoke(DesktopInstaller $installer): BinaryFileResponse|RedirectResponse
    {
        if ($url = $installer->hostedUrl()) {
            return redirect()->away($url);
        }

        abort_unless($path = $installer->localPath(), 404, 'The Windows installer is not available yet.');

        return response()->download($path, 'ClassCheck_Setup.exe', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }
}
