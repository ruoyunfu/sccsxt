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
namespace app\common\dao\delivery;

use app\common\dao\BaseDao;
use app\common\model\delivery\DeliveryOrder;

class DeliveryOrderDao extends BaseDao
{

    protected function getModel(): string
    {
        return DeliveryOrder::class;
    }

    public function search(array $where)
    {
        return DeliveryOrder::getDB()
            ->when(isset($where['service_ids']) && $where['service_ids'] !== '', function ($query) use ($where) {
                $query->whereIn('service_id', $where['service_ids']);
            })->when(isset($where['mer_ids']) && $where['mer_ids'] !== '', function ($query) use ($where) {
                $query->whereIn('mer_id', $where['mer_ids']);
            })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            });
    }
}
