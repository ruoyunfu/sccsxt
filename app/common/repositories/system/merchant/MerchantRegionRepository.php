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

namespace app\common\repositories\system\merchant;

use think\facade\Route;
use FormBuilder\Factory\Elm;
use crmeb\traits\CategoresRepository;
use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\system\merchant\MerchantRegionDao;

class MerchantRegionRepository extends BaseRepository
{
    use CategoresRepository;
    public function __construct(MerchantRegionDao $dao)
    {
        $this->dao = $dao;
    }

    public function form(?int $id)
    {
        $formData = [];
        if ($id) {
            $data = $this->dao->get($id);
            if (!$data) {
                throw new ValidateException('数据不存在');
            }
            $formData = $data->toArray();
        }
        // 根据操作类型生成表单的动作URL
        $action = Route::buildUrl(is_null($id) ? Route::buildUrl('systemMerchantRegionCreate')->build() :
            Route::buildUrl('systemMerchantRegionUpdate', ['id' => $id])->build());

        // 返回生成的表单对象，设置表单的动作、标题和初始数据
        return Elm::createForm($action, [
            Elm::cascader('pid', '上级分类：')->options(function () use ($id) {
                $menus = $this->dao->getAllOptions(null);
                // 如果是编辑分类且当前分类有子分类，则不显示当前分类作为选项。
                if ($id && isset($menus[$id])) unset($menus[$id]);
                // 格式化分类数据为级联选择器需要的格式。
                $menus = formatCascaderData($menus, 'name');
                // 添加“顶级分类”选项。
                array_unshift($menus, ['label' => '顶级分类', 'value' => 0]);
                return $menus;
            })->placeholder('请选择上级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]])
                ->appendValidate(Elm::validateInt()->required()->message('请选择上级分类')),

            Elm::input('name', '分组名称：')->max(10)->placeholder('请输入分组名称,10字以内')->required(),
            Elm::input('info', '简介：')->placeholder('请输入简介'),
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关')->activeText('开'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ])->setTitle(is_null($id)? '添加商户分组' : '编辑商户分组')->formData($formData);
    }

    public function updateStatus($id, $data)
    {
        return $this->dao->update($id, $data);
    }
 }