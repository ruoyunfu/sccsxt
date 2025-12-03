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
namespace app\common\repositories\delivery;

use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\delivery\DeliveryConfigDao;
use app\common\repositories\system\config\ConfigValueRepository;

class DeliveryConfigRepository extends BaseRepository
{
    use DeliveryTrait;

    public function __construct(DeliveryConfigDao $dao)
    {
        $this->dao = $dao;
    }

    protected function getDao()
    {
        return $this->dao;
    }

    public function update(int $id, int $merId, array $basicSettingsParams, array $deliveryParams)
    {
        // 基本信息保存
        $configValueRepository = app()->make(ConfigValueRepository::class);
        $configValueRepository->setFormData($basicSettingsParams, $merId);
        // 配送信息
        $deliveryParams['mer_id'] = $merId;
        $deliveryParams['status'] = 1;
        $deliveryParams['distance_premium_config'] = json_encode($deliveryParams['distance_premium_config']);
        $deliveryParams['weight_premium_config'] = json_encode($deliveryParams['weight_premium_config']);

        return $this->getDao()->saveConfig($id, $deliveryParams);
    }

    public function deliverySettings(int $merId, array $keys = [])
    {
        if (empty($keys)) {
            $keys = [
                'mer_delivery_type',
                'mer_delivery_order_status'
            ];
        }
        // 基本信息
        $basicSettings = merchantConfig($merId, $keys);
        // 配送信息
        $deliverySettings = $this->dao->getDeliveryConfig($merId);

        return compact('basicSettings', 'deliverySettings');
    }
    /**
     * 配送费用计算
     *
     * @param int $takeId
     * @param int $merId
     * @param float $totalPrice
     * @param float $totalWeight
     * @param array $address
     * @return float
     */
    public function deliveryFee(int $takeId, int $merId, float $totalPrice, float $totalWeight, array $address): float
    {
        $deliverySettings = $this->dao->getDeliveryConfig($merId);
        if (!$deliverySettings) {
            return 0;
        }
        // 是否到达起送价
        if ($totalPrice < $deliverySettings['min_delivery_amount']) {
            throw new ValidateException('商户：【' . $deliverySettings['merchant']['mer_name'] . '】，订单金额不能低于起送价，起送价：¥' . $deliverySettings['min_delivery_amount']);
        }
        // 是否到达免配送费金额
        if ($totalPrice >= $deliverySettings['free_shipping_amount']) {
            return 0;
        }
        // 是否开启溢价叠加,未开启溢价按基础运费计算
        $fee = $deliverySettings['base_shipping_fee'];
        if ($deliverySettings['is_premium_stack_enabled']) {
            $distanceStackFee = $this->distanceStackFee($takeId, $deliverySettings['mer_id'], $address, $deliverySettings['distance_premium_config']);
            $weightStackFee = $this->weightStackFee($totalWeight, $deliverySettings['weight_premium_config']);
            $stackFee = bcadd($distanceStackFee, $weightStackFee);
            $fee = bcadd($fee, $stackFee);
        }

        return $fee;
    }
}
