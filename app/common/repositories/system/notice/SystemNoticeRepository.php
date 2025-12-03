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


namespace app\common\repositories\system\notice;


use app\common\dao\system\notice\SystemNoticeDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 通知公告
 */
class SystemNoticeRepository extends BaseRepository
{
    public function __construct(SystemNoticeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建通知
     * 根据传入的数据和管理员ID，创建相应的通知，并根据不同的条件筛选商户，将通知与商户关联。
     *
     * @param array $data 包含通知信息和筛选条件的数据数组
     * @param int $admin_id 管理员ID，用于标识通知的创建者
     * @return object 创建的通知对象
     * @throws ValidateException 如果商户类型错误或没有有效的商户信息，则抛出验证异常
     */
    public function create(array $data, $admin_id)
    {
        // 为通知数据添加管理员ID
        $data['admin_id'] = $admin_id;

        // 获取商户仓库实例
        $merchantRepository = app()->make(MerchantRepository::class);

        // 根据不同的商户类型，筛选商户ID并生成相应的type_str
        if ($data['type'] == 1) {
            // 多个指定商户
            $ids = (array)$data['mer_id'];
            $type_str = implode('/', $merchantRepository->names($ids));
        } else if ($data['type'] == 2) {
            // 按自营或非自营筛选
            $ids = $merchantRepository->search(['is_trader' => (int)$data['is_trader']])->column('mer_id');
            $type_str = $data['is_trader'] ? '自营' : '非自营';
        } else if ($data['type'] == 3) {
            // 按类别筛选
            $ids = $merchantRepository->search(['category_id' => (array)$data['category_id']])->column('mer_id');
            $type_str = implode('/', app()->make(MerchantCategoryRepository::class)->names((array)$data['category_id']));
        } else if ($data['type'] == 4) {
            // 所有商户
            $ids = $merchantRepository->search([])->column('mer_id');
            $type_str = '全部';
        } else {
            // 商户类型错误，抛出异常
            throw new ValidateException('商户类型有误');
        }

        // 如果没有有效的商户ID，抛出异常
        if (!count($ids)) throw new ValidateException('没有有效的商户信息');

        // 为通知数据添加type_str，并移除不需要的字段
        $data['type_str'] = $type_str;
        unset($data['is_trader'], $data['category_id'], $data['mer_id']);

        // 在事务中执行通知的创建和商户通知关联的插入
        return Db::transaction(function () use ($data, $ids) {
            // 创建通知
            $notice = $this->dao->create($data);
            // 获取系统通知日志仓库实例
            $systemNoticeLogRepository = app()->make(SystemNoticeLogRepository::class);
            // 准备商户通知关联的插入数据
            $inserts = [];
            foreach ($ids as $id) {
                if (!$id) continue;
                $inserts[] = [
                    'mer_id' => (int)$id,
                    'notice_id' => $notice->notice_id
                ];
            }
            // 批量插入商户通知关联数据
            $systemNoticeLogRepository->insertAll($inserts);
            // 返回创建的通知对象
            return $notice;
        });
    }

    /**
     * 获取通知列表
     *
     * 根据给定的条件和分页信息，从数据库中检索通知列表。
     * 这个方法用于处理数据的查询逻辑，包括条件查询、分页和排序。
     *
     * @param array $where 查询条件，以键值对形式提供，用于构建SQL查询的WHERE子句。
     * @param int $page 当前的页码，用于实现分页查询。
     * @param int $limit 每页的记录数，用于控制分页查询的结果数量。
     * @return array 返回包含通知列表和总数的数组，方便前端进行分页显示。
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据提供的条件进行查询
        $query = $this->dao->search($where);

        // 统计满足条件的通知总数
        $count = $query->count();

        // 进行分页查询，并按通知ID降序排序
        $list = $query->page($page, $limit)->order('notice_id DESC')->select();

        // 将总数和列表一起返回
        return compact('count', 'list');
    }
}
