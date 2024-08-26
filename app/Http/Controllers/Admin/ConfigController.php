<?php

namespace App\Http\Controllers\Admin;

use App\Http\Filters\ConfigFilter;
use App\Http\Requests\UpdateConfigValuesRequest;
use App\Http\Requests\ConfigRequest;
use App\Http\Resources\ConfigResource;
use App\Models\Config;
use App\Models\ConfigCategory;
use App\Models\VueRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ConfigController extends Controller
{
    public function vueRouters(VueRouter $vueRouter)
    {
        return $this->ok($vueRouter->treeWithAuth()->toTree());
    }

    public function destroy(Config $config)
    {
        $config->delete();
        return $this->noContent();
    }

    public function edit(Request $request, Config $config)
    {
        return $this->ok(ConfigResource::make($config)->additional($this->formData()));
    }

    public function update(ConfigRequest $request, Config $config)
    {
        $inputs = $request->validated();
        $config->update($inputs);
        return $this->created(ConfigResource::make($config));
    }

    public function index(ConfigFilter $filter)
    {
        $configs = Config::query()
            ->with('category')
            ->filter($filter)
            ->orderByDesc('id')
            ->paginate();

        return $this->ok(ConfigResource::collection($configs));
    }

    /**
     * 添加和编辑表单所需数据
     *
     * @return array
     */
    protected function formData()
    {
        return [
            'types_map' => Config::$typeMap,
            'categories' => ConfigCategory::query()
                ->orderByDesc('id')
                ->get(),
        ];
    }

    public function create()
    {
        return $this->ok($this->formData());
    }

    public function store(ConfigRequest $request)
    {
        $inputs = $request->validated();
        $config = Config::create($inputs);
        return $this->created(ConfigResource::make($config));
    }

    public function getByCategorySlug(string $categorySlug)
    {
        return $this->ok(ConfigResource::collection(Config::getByCategorySlug($categorySlug)));
    }

    public function updateValues(UpdateConfigValuesRequest $request)
    {
        $configs = $request->getConfigs();
        $configs = Config::updateValues($configs, $request->validated());
        return $this->created($configs);
    }

    public function getValuesByCategorySlug(string $categorySlug)
    {
        return $this->ok(config(Config::CONFIG_KEY.'.'.$categorySlug), []);
    }

    public function cache()
    {
        Artisan::call('admin:cache-config');
        return $this->noContent();
    }
}
