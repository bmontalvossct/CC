<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesktopDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.offline' => false,
            'desktop.installer_url' => null,
            'desktop.installer_path' => storage_path('app/installers/not-published.exe'),
        ]);
    }

    public function test_unpublished_installer_has_no_download_link_and_returns_404(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')->where('installerUrl', null));
        $this->get('/download/windows')->assertNotFound();
    }

    public function test_hosted_installer_is_available_to_guests(): void
    {
        $url = 'https://example.com/releases/ClassCheck_Setup.exe';
        config(['desktop.installer_url' => $url]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')->where('installerUrl', route('desktop.download')));
        $this->get('/download/windows')->assertRedirect($url);
        $this->assertGuest();
    }

    public function test_local_installer_is_served_as_an_attachment(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'classcheck-installer-');
        file_put_contents($path, 'installer test fixture');
        config(['desktop.installer_path' => $path]);

        try {
            $this->get('/download/windows')->assertOk()
                ->assertDownload('ClassCheck_Setup.exe')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        } finally {
            unlink($path);
        }
    }

    public function test_non_https_hosted_urls_are_not_exposed(): void
    {
        config(['desktop.installer_url' => 'javascript:alert(1)']);
        $this->get('/download/windows')->assertNotFound();

        config(['desktop.installer_url' => 'http://example.com/installer.exe']);
        $this->get('/download/windows')->assertNotFound();
    }
}
