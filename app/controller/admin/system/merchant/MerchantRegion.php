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

namespace app\controller\admin\system\merchant;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRegionRepository;

class MerchantRegion extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantRegionRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function createForm()
    {
        $data = $this->repository->form(null);
        return app('json')->success(formToData($data));
    }

    public function create()
    {
        $data = $this->request->params(['name','status','sort',['pid',0],'info']);
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }


    public function editForm($id)
    {
        $data = $this->repository->form($id);
        return app('json')->success(formToData($data));
    }

    public function update($id)
    {
        $data = $this->request->params(['name','status','sort','pid','info']);
        $this->repository->update($id,$data);
        return app('json')->success('编辑成功');
    }


    public function delete($id)
    {
        if ($this->repository->hasChild($id)){
            return app('json')->fail('请先删除子集');
        }
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    public function lst ()
    {
        $status = $this->request->param('status',null);
        $data = $this->repository->getFormatList(0,$status);
        return app('json')->success($data);
    }

    public function switchStatus($id)
    {
        $status = $this->request->param('status',0) == 1 ? 1 : 0;
        $data = $this->repository->updateStatus($id,['status' => $status]);
        return app('json')->success('编辑成功');
    }

    public function options ()
    {
        $data = $this->repository->getAllOptions(0,1);
        $data = formatCascaderData($data,'name',0,'pid',0,0,[],$this->request->regionIds());
        return app('json')->success($data);
    }
}