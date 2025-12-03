<?php

namespace crmeb\jobs;

use crmeb\interfaces\JobInterface;
use crmeb\services\MiniProgramService;

/**
 * 小程序订单处理
 */
class MiniOrderShippingJob implements JobInterface
{
    /**
     * @param array $order_key
     * @param int $logistics_type
     * @param array $shipping_list
     * @param string $payer_openid
     * @param string $path
     * @param int $delivery_mode
     * @param bool $is_all_delivered
     * @return void
     */
    public function fire($job, $data)
    {
        try {
            MiniProgramService::create()->uploadShippingInfo($data['order_key'], $data['logistics_type'], $data['shipping_list'], $data['payer_openid'], $data['path'], $data['delivery_mode'], $data['is_all_delivered']);
        } catch (\Throwable $e) {
            \think\facade\Log::error('小程序订单处理失败，原因：' . $e->getMessage() . $e->getFile() . $e->getLine());
        }
        return $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
