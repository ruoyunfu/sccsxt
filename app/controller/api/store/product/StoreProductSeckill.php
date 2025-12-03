<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2024 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\controller\api\store\product;

use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\StoreSeckillTimeRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository as repository;

class StoreProductSeckill extends BaseController
{
    use BindSpreadTrait;

    /**
     * @var repository
     */
    protected $repository;
    protected $userInfo;

    /**
     * StoreProduct constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 秒杀商品列表
     * FerryZhao 2024/4/23
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['mer_id'] = $this->request->param('mer_id', '');
        $where['start_time'] = $this->request->param('start_time', '');
        $where['end_time'] = $this->request->param('end_time', '');
        $data = $this->repository->getApiSeckill($where, $page, $limit);

        return app('json')->success($data);
    }

    /**
     * 秒杀商品详情
     *
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-08-04
     */
    public function detail($id)
    {
        $this->bindSpread($this->userInfo);
        $seckillTimeId = $this->request->param('seckill_time_id');
        $data = $this->repository->seckillDetail($id, $this->userInfo,$seckillTimeId);
        if (!$data) {
            app()->make(SpuRepository::class)->changeStatus($id, 1);
            return app('json')->fail('商品不存在');
        }
        return app('json')->success('',$data);
    }

    /**
     * 秒杀时间段
     * @return mixed
     * @author Qinii
     * @day 2020-08-04
     */
    public function select()
    {
        return app('json')->success(app()->make(StoreSeckillTimeRepository::class)->selectTime());
    }


}
