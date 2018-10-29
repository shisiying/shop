<?php

namespace Sevenshi\Eshelper\Commands\SyncComands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\ProductProperty;
use App\Models\ProductSku;

class SyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eshelper:sync-products {--index=products} {--type=_doc}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '将商品数据同步到 Elasticsearch';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 获取 Elasticsearch 对象
        $es = app('es');

        Product::query()
            // 预加载 SKU 和 商品属性数据，避免 N + 1 问题
            ->with(['skus', 'properties'])
            // 使用 chunkById 避免一次性加载过多数据
            ->chunkById(100, function ($products) use ($es) {
                $this->info(sprintf('正在同步 ID 范围为 %s 至 %s 的商品', $products->first()->id, $products->last()->id));

                // 初始化请求体
                $req = ['body' => []];
                // 遍历商品
                foreach ($products as $product) {
                    // 将商品模型转为 Elasticsearch 所用的数组
                    $data = $this->producttoEsArray($product);

                    $req['body'][] = [
                        'index' => [
                            '_index' => $this->option('index'),
                            '_type'  => $this->option('type'),
                            '_id'    => $data['id'],
                        ],
                    ];
                    $req['body'][] = $data;
                }
                try {
                    // 使用 bulk 方法批量创建
                    $es->bulk($req);
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
            });
        $this->info('同步完成');
    }


    //定义需要同步的数据字段
    private function producttoEsArray($object)
    {

        // 只取出需要的字段
        $arr = array_only($object->toArray(), [
            'id',
            'type',
            'title',
            'category_id',
            'long_title',
            'on_sale',
            'rating',
            'sold_count',
            'review_count',
            'price',
        ]);

        // 如果商品有类目，则 category 字段为类目名数组，否则为空字符串
        $arr['category'] = $object->category ? explode(' - ', $object->category->full_name) : '';
        // 类目的 path 字段
        $arr['category_path'] = $object->category ? $object->category->path : '';
        // strip_tags 函数可以将 html 标签去除
        $arr['description'] = strip_tags($object->description);
        // 只取出需要的 SKU 字段
        $arr['skus'] = $object->skus->map(function (ProductSku $sku) {
            return array_only($sku->toArray(), ['title', 'description', 'price']);
        });

        $arr['properties'] = $object->properties->map(function (ProductProperty $property) {
            return array_merge(array_only($property->toArray(),['name','value']),[
                'search_value'=>$property->name.':'.$property->value,
            ]);
        });

        return $arr;
    }
}
