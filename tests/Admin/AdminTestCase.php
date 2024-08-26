<?php

namespace Tests\Admin;

use App\Http\Middleware\AdminPermission;
use App\Models\AdminUser;
use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    /**
     * @var string
     */
    protected $token;

    protected $routePrefix = 'admin';

    /**
     * @var AdminUser
     */
    protected $user;

    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Contracts\Filesystem\Cloud
     */
    protected $storage;

    protected $filesystem = 'uploads';

    protected function login(AdminUser $user = null)
    {
        $user = $user ?: AdminUser::factory()->create(['username' => 'admin']);
        $this->actingAs($user, 'admin');

        $this->user = $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkPermission(false);

        $this->storage = Storage::disk($this->filesystem);
    }

    /**
     * 设置是否需要检测权限
     *
     * @param bool $check
     */
    protected function checkPermission($check)
    {
        if ($check) {
            $ins = new class extends AdminPermission {
                protected $urlWhitelist = [
                    '/test-resources/pass-through',
                    'get:/test-resources/pass-through-get-put',
                ];
            };
        } else {
            $ins = new class extends AdminPermission {
                public function handle(Request $request, \Closure $next, ...$args)
                {
                    return $next($request);
                }
            };
        }

        $this->app->singleton(AdminPermission::class, function () use ($ins) {
            return $ins;
        });
    }

    /**
     * 获取数据库最新插入的 id
     *
     * @param string|null $table 指定表名，或者整个数据库
     *
     * @return mixed|string
     */
    protected function getLastInsertId(string $table = null)
    {
        if (is_null($table)) {
            return DB::getPdo()->lastInsertId();
        } else {
            return DB::table($table)->orderByDesc('id')->value('id');
        }
    }

    protected function reloadAdminConfig()
    {
        Config::clearConfigCache();
        Config::loadToConfig();
    }
}
