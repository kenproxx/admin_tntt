<?php

namespace Tests\Admin\Feature;

use App\Http\Controllers\Admin\Controller;
use App\Models\SystemMedia;
use App\Models\SystemMediaCategory;
use Illuminate\Http\UploadedFile;
use Tests\Admin\AdminTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Admin\Traits\RequestActions;

class SystemMediaControllerTest extends AdminTestCase
{
    use RequestActions;
    use RefreshDatabase;

    protected $resourceName = 'system-media';

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
    }

    public function testDestroy()
    {
        $categoryId = SystemMediaCategory::factory()
            ->create([
                'folder' => 'tests',
            ])
            ->id;
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->storeResource(
            [
                'file' => $file,
            ],
            'system-media-categories.system-media',
            ['system_media_category' => $categoryId]
        );
        $mediaId1 = $this->getLastInsertId('system_media');

        $path = Controller::UPLOAD_FOLDER_PREFIX.'/tests/'.md5_file($file).'.jpg';
        $this->assertTrue($this->storage->exists($path));
        // 复制一条记录
        $mediaId2 = SystemMedia::factory()
            ->create(tap(SystemMedia::first())->makeHidden(['id'])->toArray())
            ->id;

        $res = $this->destroyResource($mediaId1);
        $res->assertStatus(204);

        // 有重复文件记录，所以不需要删除物理文件
        $this->assertTrue($this->storage->exists($path));
        $this->assertDatabaseMissing('system_media', [
            'id' => $mediaId1,
        ]);

        $res = $this->destroyResource($mediaId2);
        $res->assertStatus(204);
        // 没有重复文件记录，删除物理文件
        $this->assertNotTrue($this->storage->exists($path));
        $this->assertDatabaseMissing('system_media', [
            'id' => $mediaId2,
        ]);
    }

    public function testEdit()
    {
        $media = SystemMedia::factory()->create();
        $id = $media->id;

        $res = $this->editResource($id);
        $res->assertStatus(200)
            ->assertJsonFragment([
                'id' => $id,
                'category_id' => 0,
            ]);
    }

    public function testUpdate()
    {
        $categoryId = SystemMediaCategory::factory()->create()->id;
        $mediaId = SystemMedia::factory()->create()->id;

        // category_id exists
        $res = $this->updateResource($mediaId, [
            'category_id' => 999,
        ]);
        $res->assertJsonValidationErrors(['category_id']);

        $res = $this->updateResource($mediaId, [
            'category_id' => $categoryId,
        ]);
        $res->assertStatus(201);

        $this->assertDatabaseHas('system_media', [
            'id' => $mediaId,
            'category_id' => $categoryId,
        ]);
    }

    public function testBatchUpdate()
    {
        $categoryId = SystemMediaCategory::factory()->create()->id;
        $mediaIds = SystemMedia::factory(2)->create()->pluck('id')->toArray();

        $res = $this->put(route('admin.system-media.batch.update'), [
            'category_id' => $categoryId,
            'id' => $mediaIds,
        ]);
        $res->assertStatus(201);

        $this->assertDatabaseHas('system_media', [
            'id' => $mediaIds[0],
            'category_id' => $categoryId,
        ]);
        $this->assertDatabaseHas('system_media', [
            'id' => $mediaIds[1],
            'category_id' => $categoryId,
        ]);
    }

    public function testBatchDestroy()
    {
        SystemMedia::factory(2)->create();

        $res = $this->delete(route('admin.system-media.batch.destroy'), [
            'id' => [1, 2],
        ]);
        $res->assertStatus(204);

        $this->assertDatabaseMissing('system_media', ['id' => 1]);
        $this->assertDatabaseMissing('system_media', ['id' => 2]);
    }
}
