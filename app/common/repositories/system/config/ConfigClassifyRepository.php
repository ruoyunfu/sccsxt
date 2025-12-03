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


use app\common\dao\system\config\SystemConfigClassifyDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;

/**
 * 配置分类
 */
class ConfigClassifyRepository extends BaseRepository
{

    /**
     * ConfigClassifyRepository constructor.
     * @param SystemConfigClassifyDao $dao
     */
    public function __construct(SystemConfigClassifyDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取选项数据，格式化为级联选择器的数据格式。
     *
     * 本方法旨在从数据访问对象（DAO）获取预定义的选项数据，并对这些数据进行格式化，以适应级联选择器的数据显示格式。
     * 这种格式化包括但不限于将数据的某个字段作为标签显示，使得前端界面能够以更加直观的方式展示这些数据。
     *
     * @return array 返回格式化后的级联选择器数据。
     */
    public function options(): array
    {
        // 从DAO获取原始选项数据
        $options = $this->dao->getOptions();
        // 格式化原始选项数据，以'classify_name'字段作为标签显示
        return formatCascaderData($options, 'classify_name');
    }

    /**
     * 根据条件获取列表数据及总数
     *
     * 本函数用于根据提供的条件查询数据库，并返回查询结果的列表以及总数。
     * 这样做的目的是为了在前端页面上展示数据列表，并且提供分页功能。
     *
     * @param string|array $where 查询条件，可以是字符串或数组形式的SQL WHERE子句。
     * @return array 返回包含列表数据和总数的数组，数组键名为'list'和'count'。
     */
    public function lst($where)
    {
        // 使用DAO对象的search方法根据$where条件进行查询
        $query = $this->dao->search($where);
        // 从查询结果中获取数据列表
        $list = $query->select();
        // 从查询结果中获取数据总数
        $count = $query->count();

        // 将列表数据和总数一起返回
        return compact('list', 'count');
    }

    /**
     * 切换实体的状态。
     *
     * 本函数用于通过指定的ID和新的状态来更新数据库中相应实体的状态字段。
     * 它封装了与数据访问对象（DAO）的交互，使得业务逻辑层可以更方便地进行状态更新操作，
     * 而无需直接处理数据库层面的细节。
     *
     * @param int $id 实体的唯一标识符。用于在数据库中定位到特定的实体。
     * @param int $status 新的状态值。该值会被用于更新实体的状态字段。
     * @return mixed 返回DAO更新操作的结果。具体类型取决于DAO的实现。
     */
    public function switchStatus(int $id, int $status)
    {
        // 使用compact函数将$status变量打包成一个名为'status'的键值对数组，
        // 然后调用dao的update方法，通过$id更新数据库中的对应记录的状态。
        return $this->dao->update($id, compact('status'));
    }

    /**
     * 创建或编辑配置分类表单
     *
     * 该方法用于生成一个包含各种输入字段的表单，用于创建或编辑配置分类。
     * 表单字段包括上级分类选择、分类名称、分类键、分类说明、图标选择、排序和显示状态。
     *
     * @param int|null $id 分类的ID，如果为null，则表示创建新分类；否则，表示编辑已有的分类。
     * @param array $formData 表单的初始数据，用于填充表单字段。
     * @return Form 返回生成的表单对象。
     */
    public function form(?int $id = null, array $formData = []): Form
    {
        // 根据$id的值决定生成表单的URL，用于创建或更新分类。
        $formUrl = is_null($id) ? Route::buildUrl('configClassifyCreate')->build() : Route::buildUrl('configClassifyUpdate', ['id' => $id])->build();
        $form = Elm::createForm($formUrl);

        // 设置表单的验证规则和字段。
        $form->setRule([
            // 上级分类选择字段，使用下拉列表呈现，允许选择顶级分类。
            Elm::select('pid', '上级分类：', 0)->options(function () {
                $data = $this->dao->getTopOptions();
                $options = [['value' => 0, 'label' => '顶级分类']];
                foreach ($data as $value => $label) {
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->placeholder('请选择上级分类'),
            // 分类名称输入字段，必填。
            Elm::input('classify_name', '配置分类名称：')->placeholder('请输入配置分类名称')->required(),
            // 分类键输入字段，必填。
            Elm::input('classify_key', '配置分类key：')->placeholder('请输入配置分类key')->required(),
            // 分类说明输入字段，可选。
            Elm::input('info', '配置分类说明：')->placeholder('请输入配置分类说明'),
            // 图标选择字段，使用iframe嵌入图标选择界面。
            Elm::frameInput('icon', '配置分类图标：', '/' . config('admin.admin_prefix') . '/setting/icons?field=icon')->icon('el-icon-circle-plus-outline')->height('338px')->width('700px')->modal(['modal' => false]),
            // 排序数字输入字段，可选，默认值为0。
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            // 显示状态开关字段，默认开启。
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关')->activeText('开'),
        ]);

        // 设置表单标题，并根据$id的值决定是创建还是编辑分类。
        // 填充表单数据。
        return $form->setTitle(is_null($id) ? '添加配置分类' : '编辑配置分类')->formData($formData);
    }

    /**
     * 创建表单实例。
     *
     * 本方法旨在提供一个统一的入口，用于创建表单对象。通过调用此方法，可以避免直接与表单对象的构造函数交互，
     * 增加了代码的灵活性和可维护性。此方法的设计符合开闭原则，即对扩展开放，对修改关闭，
     * 当需要更换表单实现或者添加新的表单类型时，只需修改此方法即可，而不需要修改调用此方法的代码。
     *
     * @return FormInterface 返回一个表单对象。返回的对象将根据实际业务需求实现FormInterface接口，
     *         从而确保表单对象的统一性和可操作性。
     */
    public function createForm()
    {
        // 通过调用form方法来创建并返回表单实例
        return $this->form();
    }

    /**
     * 更新表单数据。
     * 该方法用于根据给定的ID获取数据库中的记录，并使用这些数据来构建一个表单，以便用户可以查看或编辑这些数据。
     *
     * @param int $id 表单记录的唯一标识符。
     * @return array 返回一个包含表单字段和值的数组。
     */
    public function updateForm($id)
    {
        // 通过ID从数据库获取记录，并转换为数组格式，用于填充表单
        return $this->form($id, $this->dao->get($id)->toArray());
    }
}
