<?php

return [
    // Use a hosted release URL on deployments, or place the installer at this path.
    'installer_url' => env('CLASSCHECK_INSTALLER_URL')
        ?: 'https://github.com/bmontalvossct/CC/releases/download/windows-2026.10.07/ClassCheck_Setup.exe',
    'installer_path' => storage_path('app/installers/ClassCheck_Setup.exe'),
];
