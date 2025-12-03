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


namespace crmeb\services\easywechat\batches;


use think\facade\Route;
use crmeb\exceptions\PayException;
use crmeb\services\wechat\Payment;
use crmeb\services\easywechat\BaseClient;
use think\exception\ValidateException;
use think\facade\Log;

class Client extends BaseClient
{
    protected $isService = false;

    //发起转账
    const API_TRANSFER_BILLS_URL = '/v3/fund-app/mch-transfer/transfer-bills';

    /**
     * 商家转账到零钱
     * https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter4_3_1.shtml
     * @param $type
     * @param array $order
     * @return mixed
     */
    public function send(array $order)
    {
        $params = [
            'appid'        => $this->app['config']['app_id'],
            'out_batch_no' => $order['out_batch_no'],
            'batch_name'   => $order['batch_name'],
            'batch_remark' => $order['batch_remark'],
            'total_amount' => $order['total_amount'],
            'total_num'    => $order['total_num'],
            'transfer_detail_list' => $order['transfer_detail_list'],
        ];
        $content = json_encode($params, JSON_UNESCAPED_UNICODE);

        $res = $this->request('/v3/transfer/batches', 'POST', ['sign_body' => $content]);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }
    /**
     * 发起转账新接口(2025年1月15日升级)
     *
     * @param string $outBatchNo
     * @param string $amount
     * @param string $openid
     * @param string $userName
     * @param string $remark
     * @param array $transferDetailList
     * @param string $transferSceneId
     * @param string $type
     * @param string $perception
     * @return void
     */
    public function transferBills(
        string $outBatchNo,
        string $amount,
        string $openid,
        string $userName,
        string $remark,
        array $transferDetailList,
        string $transferSceneId = '1000', 
        string $type = 'wechat'
    ) {
        $appId = $this->app['config']['app_id'];
        $data = [
            'appid' => $appId,
            'out_bill_no' => $outBatchNo,
            'transfer_scene_id' => $transferSceneId,
            'openid' => $openid,
            'transfer_amount' => (int)bcmul($amount, 100, 0),
            'transfer_remark' => $remark,
            'notify_url' => systemConfig('site_url') . Route::buildUrl('mchNotify',['type' => $type])->build(),
            'transfer_scene_report_infos' => $transferDetailList
        ];

        if ($amount >= 200000) {
            if (empty($userName)) {
                throw new ValidateException('明细金额大于等于2000时,收款人姓名必须填写');
            }
            $data['user_name'] = $this->encryptSensitiveInformation($userName);
        }
        Log::info('发起转账 data :' . var_export($data, 1));
        $res = $this->request(self::API_TRANSFER_BILLS_URL, 'POST', ['sign_body' => json_encode($data)]);
        if (!$res || isset($res['code'], $res['message'])) {
            throw new ValidateException('微信商家转账：'.$res['message'] ?? '发起商家转账失败');
        }

        $res['app_id'] = $appId;
        $res['mch_id'] = $this->app['config']['payment']['merchant_id'];

        return $res;
    }

    public function handleNotify($callback)
    {
        $request = request();
        $data = $request->post('resource', []);
        $data = $this->decrypt($data);

        $handleResult = call_user_func_array($callback, [json_decode($data, true)]);
        if (is_bool($handleResult) && $handleResult) {
            $response = [
                'code' => 'SUCCESS',
                'message' => 'OK',
            ];
        } else {
            $response = [
                'code' => 'FAIL',
                'message' => $handleResult,
            ];
        }

        return response($response, 200, [], 'json');
    }
}
