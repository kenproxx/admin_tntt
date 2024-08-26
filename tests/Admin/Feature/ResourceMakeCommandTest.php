<?php

namespace Tests\Admin\Feature;

use Tests\Admin\AdminTestCase;
use Illuminate\Support\Facades\Storage;

class ResourceMakeCommandTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = Storage::fake('local');
        $this->app->instance('path', storage_path('framework/testing/disks/local/app'));
        $this->app->instance('path.resources', storage_path('framework/testing/disks/local/resources'));
        $this->app->instance('path.base', storage_path('framework/testing/disks/local'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testMake()
    {
        $this
            ->artisan('admin:make-resource', [
                'name' => 'admin-banner',
            ])
            ->assertExitCode(1);

        $files = [
            'app/Models/AdminBanner.php',
            'app/Http/Filters/AdminBannerFilter.php',
            'app/Http/Requests/AdminBannerRequest.php',
            'app/Http/Resources/AdminBannerResource.php',
            'app/Http/Controllers/Admin/AdminBannerController.php',
            'resources/admin/src/api/admin-banners.js',
            'resources/admin/src/views/admin-banners/Index.vue',
            'resources/admin/src/views/admin-banners/Form.vue',
        ];

        foreach ($files as $file) {
            $this->assertFileExists($this->storage->path($file));
        }
        $this->assertFileDoesNotExist($this->storage->path('app/Admin/Tests/Feature/AdminBannerControllerTest.php'));

        $controllerContent = file_get_contents($this->storage->path('app/Http/Controllers/Admin/AdminBannerController.php'));

        $this->assertEquals(substr_count($controllerContent, 'AdminBannerResource'), 5);
        $this->assertEquals(substr_count($controllerContent, 'AdminBannerRequest'), 3);
        $this->assertEquals(substr_count($controllerContent, 'AdminBannerFilter'), 2);

        $feApiContent = file_get_contents($this->storage->path('resources/admin/src/api/admin-banners.js'));
        $this->assertEquals(substr_count($feApiContent, 'getAdminBanners'), 1);
        $this->assertEquals(substr_count($feApiContent, 'AdminBanner'), 6);
    }

    public function testSpecifyModelAndWithTest()
    {
        $this
            ->artisan('admin:make-resource', [
                'name' => 'admin-banner',
                '--model' => '\\App\\Models\\AdminBanner',
                '--test' => true,
            ])
            ->assertExitCode(0);

        $this->storage->put(
            'app/Models/AdminBanner.php',
            '<?php namespace App\Models; class AdminBanner {}'
        );
        include_once $this->storage->path('app/Models/AdminBanner.php');

        $this
            ->artisan('admin:make-resource', [
                'name' => 'admin-banner',
                '--model' => '\\App\\Models\\AdminBanner',
                '--test' => true,
            ])
            ->assertExitCode(1);

        $this->assertFileExists($this->storage->path('tests/Admin/Feature/AdminBannerControllerTest.php'));
    }

    public function testFileExists()
    {
        $this->storage->put('app/Models/AdminBanner.php', '');
        $this
            ->artisan('admin:make-resource', [
                'name' => 'admin-banner',
            ])
            ->assertExitCode(0);
        $this
            ->artisan('admin:make-resource', [
                'name' => 'admin-banner',
                '--force' => true,
            ])
            ->assertExitCode(1);
    }

    public function testMakeWithFolder()
    {
        $this
            ->artisan('admin:make-resource', [
                'name' => 'api/admin/admin-banner',
                '--test' => true,
            ])
            ->assertExitCode(1);

        $files = [
            'app/Models/Api/Admin/AdminBanner.php',
            'app/Http/Filters/Api/Admin/AdminBannerFilter.php',
            'app/Http/Requests/Api/Admin/AdminBannerRequest.php',
            'app/Http/Resources/Api/Admin/AdminBannerResource.php',
            'app/Http/Controllers/Admin/Api/Admin/AdminBannerController.php',
            'resources/admin/src/api/api/admin/admin-banners.js',
            'resources/admin/src/views/api/admin/admin-banners/Index.vue',
            'resources/admin/src/views/api/admin/admin-banners/Form.vue',
        ];

        foreach ($files as $file) {
            $this->assertFileExists($this->storage->path($file));
        }
    }
}
