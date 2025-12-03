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

use app\common\repositories\user\UserRepository;

trait BindSpreadTrait
{
    protected function bindSpread($userInfo = null)
    {
        $pid = $this->request->param('pid');
        if(!$userInfo || !$pid) {
            return false;
        }

        return app()->make(UserRepository::class)->bindSpread($userInfo, intval($pid));
    }
}