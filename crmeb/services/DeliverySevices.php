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

namespace crmeb\services;

use crmeb\interfaces\DeliveryInterface;
use crmeb\services\delivery\Delivery;
use crmeb\services\delivery\storage\Dada;
use crmeb\services\delivery\store\Uupt;

/**
 * Class BaseExpress
 * @package crmeb\basic
 */
class DeliverySevices
{
    const DELIVERY_TYPE_UU   = 2;
    const DELIVERY_TYPE_DADA = 1;

    public static function create($gateway = self::DELIVERY_TYPE_DADA, $merId = 0)
    {
        return \crmeb\basic\__cssdf2dfxalsdcbbdawww($gateway, $merId);
    }
}
