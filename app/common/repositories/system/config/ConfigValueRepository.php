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


namespace app\common\repositories\system\config;


use app\common\dao\system\config\SystemConfigValueDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\system\groupData\GroupRepository;
use crmeb\jobs\SyncProductTopJob;
use crmeb\services\DownloadImageService;
use crmeb\services\RedisCacheService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;

/**
 * Class ConfigValueRepository
 * @package app\common\repositories\system\config
 * @mixin SystemConfigValueDao
 */
class ConfigValueRepository extends BaseRepository
{

    public $special = [
        'serve_account'
    ];

    const CONFIG_KEY_PREFIX = 'merchant_sys_config_';

    /**
     * ConfigValueRepository constructor.
     * @param SystemConfigValueDao $dao
     */
    public function __construct(SystemConfigValueDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据指定的键获取配置信息，并为未找到的键设置默认值。
     *
     * 此方法用于从数据库中检索特定商户的配置信息。它接受一个键的数组和一个商户ID，
     * 返回一个包含所请求配置键值对的数组。如果某个键在数据库中不存在，
     * 则该键的值将被设置为空字符串。
     *
     * @param array $keys 需要获取配置值的键的数组。
     * @param int $merId 商户的唯一标识ID。
     * @return array 返回一个包含配置键值对的数组，对于未找到的键，值为空字符串。
     */
    public function more(array $keys, int $merId): array
    {
        // 通过指定的键和商户ID从数据库中获取配置信息
        $config = $this->dao->fields($keys, $merId);

        // 遍历键数组，为在数据库中未找到的键设置默认值为空字符串
        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                $config[$key] = '';
            }
        }

        // 返回处理后的配置信息数组
        return $config;
    }

    /**
     * 根据键和商家ID获取值。
     *
     * 本函数旨在通过指定的键和商家ID从数据存储中检索相应的值。如果找到值，则返回该值；
     * 如果未找到值或检索操作失败，则返回空字符串。
     *
     * @param string $key 需要检索的键。此键用于唯一标识数据存储中的特定项。
     * @param int $merId 商家ID。用于限定检索范围，仅返回与该商家相关的数据。
     * @return string 返回与键和商家ID对应的值。如果未找到相应的值，则返回空字符串。
     */
    public function get(string $key, int $merId)
    {
        // 通过键和商家ID从数据存储中获取值
        $value = $this->dao->value($key, $merId);
        // 如果值存在则返回，否则返回空字符串
        return $value ?? '';
    }

    /**
     * 保存配置数据
     *
     * 该方法用于处理配置项的保存操作。它首先确定哪些配置键是有效的，然后处理这些键对应的值，
     * 包括类型转换和值的验证，最后保存处理后的数据。
     *
     * @param int $cid 配置分类ID，用于确定配置项的分类。
     * @param array $formData 用户提交的表单数据，包含所有的配置项及其值。
     * @param int $merId 商家ID，用于区分不同商家的配置数据（如果适用）。
     * @throws ValidateException 如果配置项的值不合法，例如小于0的数字，将抛出验证异常。
     */
    public function save($cid, array $formData, int $merId)
    {
        $this->saveBefore($cid, $formData, $merId);
        // 获取表单数据的所有键
        $keys = array_keys($formData);
        // 确定哪些键是有效的配置键，通过与配置仓库中的键进行交集运算
        $keys = app()->make(ConfigRepository::class)->intersectionKey($cid, $keys);
        // 如果没有有效的键，则直接返回，不进行后续操作
        if (!count($keys)) return;

        // 遍历有效的键，对每个键对应的值进行处理
        foreach ($keys as $key => $info) {
            // 如果表单数据中不存在当前键，则从表单数据中移除该键
            if (!isset($formData[$key]))
                unset($formData[$key]);
            else {
                // 如果当前配置项的类型为数字，则进行数值验证和类型转换
                if ($info['config_type'] == 'number') {
                    // 如果值为空或小于0，则抛出验证异常
                    if ($formData[$key] === '' || $formData[$key] < 0)
                        throw new ValidateException($info['config_name'] . '不能小于0');
                    // 将值转换为浮点数
                    $formData[$key] = floatval($formData[$key]);
                }
                // 对当前配置项进行进一步的处理，例如存储或转换
                $this->separate($key,$formData[$key],$merId);
            }
        }
        // 保存处理后的表单数据
        $this->setFormData($formData, $merId);
    }

    public function saveBefore($cid, array $formData, int $merId)
    {
        return ;
    }

    /**
     * 需要做特殊处理的配置参数
     * @param $key
     * @author Qinii
     * @day 2022/11/17
     */
    public function separate($key,$value,$merId)
    {
        switch($key) {
            case 'mer_svip_status':
                //修改商户的会员状态
                app()->make(ProductRepository::class)->getSearch([])->where(['mer_id' => $merId,'product_type' => 0])->update([$key => $value]);
                break;
                //热卖排行
            case 'hot_ranking_switch':
                if ($value) {
                    Queue::push(SyncProductTopJob::class, []);
                }
                break;
            case 'margin_remind_day':
                if ($value && floor($value) != $value) throw new ValidateException('时间不可为小数');
                break;
            case 'svip_switch_status':
                if ($value == 1) {
                    $groupDataRepository = app()->make(GroupDataRepository::class);
                    $groupRepository = app()->make(GroupRepository::class);
                    $group_id = $groupRepository->getSearch(['group_key' => 'svip_pay'])->value('group_id');
                    $where['group_id'] = $group_id;
                    $where['status'] = 1;
                    $count = $groupDataRepository->getSearch($where)->field('group_data_id,value,sort,status')->count();
                    if (!$count)
                        throw new ValidateException('请先添加会员类型');
                }
                break;
            default:
                break;
        }
        return ;
    }

    /**
     * 设置商家配置数据
     * 该方法用于批量更新或插入商家的配置信息。它首先检查每个配置项是否已存在，如果存在，则更新其值；如果不存在，则创建新的配置项。
     * 使用数据库事务确保操作的原子性。
     *
     * @param array $formData 商家配置的数据数组，键值对形式，其中键是配置项的键，值是配置项的值。
     * @param int $merId 商家的ID，用于标识商家。
     */
    public function setFormData(array $formData, int $merId)
    {
        // 开启数据库事务处理
        Db::transaction(function () use ($merId, $formData) {
            // 遍历配置数据数组
            foreach ($formData as $key => $value) {
                // 检查当前配置项是否已存在
                if ($this->dao->merExists($key, $merId)) {
                    // 如果已存在，则更新配置项的值
                    $this->dao->merUpdate($merId, $key, ['value' => $value]);
                } else {
                    // 如果不存在，则创建新的配置项
                    $this->dao->create([
                        'mer_id' => $merId,
                        'value' => $value,
                        'config_key' => $key
                    ]);
                }
            }
        });
        // 同步配置信息到其他系统或缓存
        $this->syncConfig();
    }


    /**
     * 同步配置到Redis缓存中。
     * 该方法从数据库中查询当前的配置信息，然后将这些配置同步到Redis缓存中。
     * 同时，它也会清理掉已经不存在于数据库中的旧配置项，以保持缓存的同步和清洁。
     * 最后，它会更新一个配置标志，标记配置的同步时间。
     */
    public function syncConfig()
    {
        // 从数据库中查询所有的配置项，包括值、配置键和商家ID。
        $list = $this->query([])->column('value,config_key,mer_id');

        // 实例化Redis缓存服务类。
        $make = app()->make(RedisCacheService::class);

        // 获取当前Redis中所有以CONFIG_KEY_PREFIX开头的键，这些可能是旧的配置键。
        $oldKeys = $make->keys(self::CONFIG_KEY_PREFIX.'*') ?: [];
        // 将键名和键值设为相同的值，以便后续操作。
        $oldKeys = array_combine($oldKeys, $oldKeys);

        // 准备一个数组，用于存储要设置到Redis中的新配置项。
        $mset = [];
        foreach ($list as $item) {
            // 构建新的配置键，并将配置值存储到$mset数组中。
            $key = self::CONFIG_KEY_PREFIX . $item['mer_id'] . '_' . $item['config_key'];
            $mset[$key] = $item['value'];
            // 从旧的键中删除已经存在于新配置中的键，以便后续清理。
            unset($oldKeys[$key]);
        }
        // 更新配置标志，记录配置的同步时间。
        $mset[self::CONFIG_KEY_PREFIX.'configFlag'] = time();

        // 通过Redis缓存服务类设置新的配置项。
        $make->mset($mset);

        // 如果还有剩余的旧键，说明这些键已经不存在于新的配置中，需要从Redis中删除。
        if (count($oldKeys)) {
            $make->handler()->del(...array_values($oldKeys));
        }
        // 删除API配置的缓存，以便下次请求时重新从Redis获取最新配置。
        Cache::delete('get_api_config');
    }


    /**
     *  清除缓存后需要重新增加缓存的配置
     * @author Qinii
     * @day 2023/10/20
     */
    public function special()
    {
        $list = $this->query([])->column('value,config_key,mer_id');
        foreach ($list as $item) {
            if (in_array($item['config_key'], $this->special)) {
                Cache::set($item['config_key'], json_decode($item['value']));
            }
        }
    }

    /**
     * 根据商家ID和配置名称获取配置信息。
     * 该方法支持批量获取配置，如果配置信息存储在Redis中，则直接从Redis中读取；
     * 如果Redis中不存在配置信息，则从数据库同步配置到Redis。
     *
     * @param int $merId 商家ID，用于区分不同商家的配置。
     * @param string|array $name 配置名称，可以是单个配置名称的字符串，也可以是多个配置名称的数组。
     * @return array|mixed 返回配置值，如果请求的是单个配置，则返回对应的配置值对象；
     * 如果请求的是多个配置，则返回一个包含多个配置值的数组。
     */
    public function getConfig(int $merId, $name)
    {
        // 创建Redis缓存服务实例
        $make = app()->make(RedisCacheService::class);

        // 处理配置名称为数组的情况
        if (is_array($name)) {
            if (!count($name)) {
                // 如果配置名称数组为空，则直接返回空数组
                return [];
            }
            $names = $name;
        } else {
            // 如果配置名称不是数组，则将其转换为数组
            $names = [$name];
        }
        $configFlag = $make->mGet([self::CONFIG_KEY_PREFIX.'configFlag']);

        // 从Redis中获取配置标志，用于判断是否需要同步配置到Redis
        if (empty($configFlag) || $configFlag[0] == false) {
            // 如果Redis中没有配置标志，则调用同步配置方法
            $this->syncConfig();
        }

        // 准备配置键名数组
        $keys = [];
        foreach ($names as $item) {
            $keys[] = self::CONFIG_KEY_PREFIX . $merId . '_' . $item;
        }

        // 从Redis中批量获取配置值
        $values = $make->mGet($keys) ?: [];

        if (!is_array($name)) {
            // 如果请求的是单个配置，则直接返回对应的配置值
            return ($values[0] ?? '') ? json_decode($values[0]) : '';
        }

        // 处理批量获取配置的情况
        $data = [];
        if (!count($values)) {
            // 如果没有获取到任何配置值，则初始化一个空数组，每个配置对应一个空字符串值
            foreach ($names as $v) {
                $data[$v] = '';
            }
            return $data;
        }

        // 遍历获取到的配置值，将其按照配置名称放入$data数组中
        foreach ($values as $i => $value) {
            $data[$names[$i]] = ($value !== '') ? json_decode($value) : '';
        }

        return $data;
    }

}
