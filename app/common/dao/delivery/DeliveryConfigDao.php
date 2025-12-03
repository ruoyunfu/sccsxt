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
use app\common\model\delivery\DeliveryConfig;

class DeliveryConfigDao extends BaseDao
{

    protected function getModel(): string
    {
        return DeliveryConfig::class;
    }

    public function getDeliveryConfig(int $merId)
    {
        return $this->getModel()::getModel()->with(['merchant'])->where('mer_id', $merId)->find() ?: [];
    }

    public function saveConfig(int $id, array $params)
    {
        $info = $this->get($id);
        if (!$info) {
            return $this->create($params);
        }

        return $this->update($id, $params);
    }
}
