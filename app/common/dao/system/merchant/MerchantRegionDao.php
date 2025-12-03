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

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use crmeb\traits\CategoresDao;
use app\common\model\system\merchant\MerchantRegion;

class MerchantRegionDao extends BaseDao
{

    use CategoresDao;
    protected function getModel(): string
    {
        return MerchantRegion::class;
    }

    public function getAllOptions($mer_id = null,$status = 1,$level = null,$type = null)
    {
        $field = $this->getParentId().',name';
        $query = ($this->getModel()::getDB());
        $query->when(($mer_id !== null),function($query)use($mer_id){
            $query->where('mer_id', $mer_id);
        })
            ->when($status,function($query)use($status){
                $query->where($this->getStatus(),$status);
            })
            ->when(($level != '' && $level != null),function($query)use($level){
                $query->where($this->getLevel(),'<',$level);
            });
        $query->when(!is_null($type),function ($query) use($type){
            $query->where('type',$type);
        });
        return $query->order('sort DESC,'.$this->getPk().' DESC')->column($field, $this->getPk());
    }

    public function getLevel()
    {
        return 'lv';
    }

    public function getStatus(): string
    {
        return 'status';
    }
}