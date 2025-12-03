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


namespace app\controller\merchant\system;

use app\validate\merchant\MerchantApplymentsValidate;
use think\App;
use think\facade\Config;
use think\facade\Queue;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantApplymentsRepository;

class MerchantApplyments extends BaseController
{
    /**
     * @var MerchantRepository
     */
    protected $repository;

    /**
     * Merchant constructor.
     * @param App $app
     * @param MerchantRepository $repository
     */
    public function __construct(App $app, MerchantApplymentsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 创建申请
     * @param MerchantApplymentsValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 6/22/21
     */
    public function create(MerchantApplymentsValidate $validate)
    {
        if(!systemConfig('open_wx_sub_mch')) return app('json')->fail('未开启子商户入驻');
        $data = $this->checkParams($validate);

        $this->repository->create($data,$this->request->merId());

        return app('json')->success('申请提交成功');
    }

    /**
     * 获取商家详情
     *
     * @return \think\response\Json
     */
    public function detail()
    {
        // 获取商家ID
        $merId = $this->request->merId();
        // 调用仓库方法获取商家详情
        $data = $this->repository->detail($merId);
        // 添加开通微信子商户配置项
        $data['open_wx_sub_mch'] = systemConfig('open_wx_sub_mch');
        // 返回JSON格式的成功响应
        return app('json')->success($data);
    }

    public function update($id, MerchantApplymentsValidate $validate)
    {
        if (!systemConfig('open_wx_sub_mch')) return app('json')->fail('未开启子商户入驻');
        // 校验参数
        $data = $this->checkParams($validate);
        // 删除ID字段
        unset($data['id']);
        $this->repository->edit($id, $data);

        return app('json')->success('编辑提交成功');
    }

    /**
     * 查询商家状态
     *
     * @return \think\response\Json
     */
    public function check()
    {
        $mer_id = $this->request->merId();
        // 调用仓库方法查询商家状态
        $this->repository->check($mer_id);
        return app('json')->success('查询状态已更新');
    }


    /**
     * 上传图片
     * @param string $field 上传图片的字段名
     * @return \think\response\Json
     */
    public function uploadImage($field)
    {
        // 获取上传的文件
        $file = $this->request->file($field);
        // 获取水印参数
        $water = $this->request->param('water');
        // 如果没有上传文件则返回错误信息
        if (!$file) return app('json')->fail('请上传图片');
        // 如果上传的是数组则只取第一个元素
        $file = is_array($file) ? $file[0] : $file;
        // 验证上传的文件是否符合要求
        validate(["$field|图片" => [
            'fileSize' => config('upload.filesize'),
            'fileExt' => 'jpg,jpeg,png,bmp,gif',
            'fileMime' => 'image/jpeg,image/png,image/gif',
            function ($file) {
                $ext = $file->extension();
                if ($ext != strtolower($file->extension())) {
                    return '图片后缀必须为小写';
                }
                return true;
            }
        ]])->check([$field => $file]);

        // 调用仓库的上传图片方法
        $res = $this->repository->uploadImage($field, $water);

        // 返回上传结果
        return app('json')->success($res);
    }


    /**
     * 校验参数
     * @param MerchantApplymentsValidate $validate 验证器实例
     * @return array 校验通过的参数数组
     */
    public function checkParams(MerchantApplymentsValidate $validate)
    {
        //'organization_cert_info',
        // 获取需要校验的参数
        $data = $this->request->params([
            'organization_type', 'business_license_info', 'id_doc_type', 'id_card_info', 'id_doc_info', 'need_account_info', 'account_info', 'contact_info', 'sales_scene_info', 'merchant_shortname', 'qualifications', 'business_addition_pics', 'business_addition_desc'
        ]);

        // 根据不同的身份证类型删除对应的参数
        if ($data['id_doc_type'] == 1) {
            unset($data['id_doc_info']);
        } else {
            unset($data['id_card_info']);
        }

        // 如果机构类型为2401或2500则删除商业营业执照和组织机构代码证
        if (in_array($data['organization_type'], ['2401', '2500'])) {
            unset($data['business_license_info']);
//            unset($data['organization_cert_info']);
        }

//        if(isset($data['organization_cert_info']) && !is_array($data['organization_cert_info'])) unset($data['organization_cert_info']);

        if (isset($data['qualifications']) && !$data['qualifications']) unset($data['qualifications']);

        if (isset($data['business_addition_pics']) && !$data['business_addition_pics']) unset($data['business_addition_pics']);
        if ($data['organization_type'] !== 2 && isset($data['id_card_info']['id_card_address'])) {
            unset($data['id_card_info']['id_card_address']);
        }
        $validate->check($data);
        return $data;
    }

}
