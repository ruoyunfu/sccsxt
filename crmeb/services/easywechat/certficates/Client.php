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


namespace crmeb\services\easywechat\certficates;


use crmeb\exceptions\WechatException;
use crmeb\services\easywechat\BaseClient;
use EasyWeChat\Core\AbstractAPI;
use think\exception\InvalidArgumentException;
use think\facade\Cache;

class Client extends BaseClient
{
    public function get()
    {
        //自动分账
        if ($this->isService) {
            $pay_routine_public_id = $pay_routine_public_key = '';
        } else { //普通支付
            $pay_routine_public_id = $this->app['config']['payment']['pay_weixin_public_id'] ?? '';
            $pay_routine_public_key = $this->app['config']['payment']['pay_weixin_public_key'] ?? '';
        }

        if ($pay_routine_public_key && $pay_routine_public_id) {
            $certficates = [
                'serial_no' => $pay_routine_public_id,
                'certificates' => $pay_routine_public_key
            ];
        } else {
            $driver = Cache::store('file');
            $cacheKey = '_wx_v3' . ($this->isService ? $this->app['config']['service_payment']['serial_no'] : $this->app['config']['payment']['serial_no']);
            if ($driver->has($cacheKey)) {
                return $driver->get($cacheKey);
            }
            $certficates = $this->getCertficates();
            $driver->set($cacheKey, $certficates, 3600 * 24 * 30);
        }
        return $certficates;
    }

    /**
     * get certficates.
     *
     * @return array
     */
    public function getCertficates()
    {
        $response = $this->request('/v3/certificates', 'GET', [], false);
        if (isset($response['code']))  throw new WechatException($response['message']);
        $certificates = $response['data'][0];
        $certificates['certificates'] = $this->decrypt($certificates['encrypt_certificate']);
        unset($certificates['encrypt_certificate']);
        return $certificates;
    }
}
