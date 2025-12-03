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


use app\common\dao\system\config\SystemConfigDao;
use app\common\model\system\config\SystemConfigClassify;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\CacheRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Route;

/**
 * 系统配置
 */
class ConfigRepository extends BaseRepository
{
    const TYPES = ['input' => '文本框', 'number' => '数字框', 'textarea' => '多行文本框', 'radio' => '单选框', 'switches' => '开关', 'checkbox' => '多选框', 'select' => '下拉框', 'file' => '文件上传', 'image' => '图片上传', 'images' => '多图片上传', 'color' => '颜色选择框'];

    /**
     * ConfigRepository constructor.
     * @param SystemConfigDao $dao
     */
    public function __construct(SystemConfigDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据商家ID、配置分类和配置信息，生成表单规则。
     * 该方法主要用于构建表单的规则和结构，根据不同的商家ID和配置分类，动态生成表单用于数据的录入和修改。
     *
     * @param int $merId 商家ID，用于区分是商家配置还是平台配置。
     * @param SystemConfigClassify $configClassify 配置分类对象，包含分类的键和名称。
     * @param array $configs 配置信息数组，包含所有的配置项。
     * @param array $formData 已填写的表单数据数组，用于预填充表单。
     * @return Elm|Form
     */
    public function formRule(int $merId, SystemConfigClassify $configClassify, array $configs, array $formData = [])
    {
        // 根据配置信息和商家ID获取表单组件规则
        $components = $this->getRule($configs, $merId);

        // 创建表单，设置表单的提交URL和组件
        $form = Elm::createForm(Route::buildUrl($merId ? 'merchantConfigSave' : 'configSave', ['key' => $configClassify->classify_key])->build(), $components);

        // 设置表单的标题为配置分类的名称，并过滤掉表单数据中的空值
        return $form->setTitle($configClassify->classify_name)->formData(array_filter($formData, function ($item) {
            return $item !== '' && !is_null($item);
        }));
    }

    /**
     * 根据配置数组和商家ID获取组件规则
     *
     * 本函数通过遍历给定的配置数组，针对每个配置项调用getComponent方法，
     * 并将结果收集到一个数组中返回。这样做的目的是为了根据不同的配置和商家ID，
     * 组装出一系列的组件规则。
     *
     * @param array $configs 配置数组，包含需要生成组件的配置信息
     * @param string $merId 商家ID，用于生成特定商家的组件规则
     * @return array 返回包含所有组件的数组
     */
    public function getRule(array $configs, $merId)
    {
        // 初始化一个空数组，用于存放遍历过程中生成的组件
        $components = [];

        // 遍历配置数组，针对每个配置生成一个组件
        foreach ($configs as $config) {
            // 调用getComponent方法生成组件，并将结果添加到组件数组中
            $component = $this->getComponent($config, $merId);
            $components[] = $component;
        }

        // 返回包含所有组件的数组
        return $components;
    }

    /**
     * 根据配置信息获取组件实例
     *
     * 该方法根据传入的配置信息和商家ID，动态生成并返回相应的组件实例。
     * 支持的组件类型包括图片、图片组、文件上传、下拉选择、复选框、单选框、开关等。
     * 对于不同的组件类型，会根据配置规则设置相应的属性和选项。
     *
     * @param array $config 配置信息，包含组件的类型、键、名及其他配置项
     * @param int $merId 商家ID，用于判断是商家后台还是管理员后台的配置
     * @return \think\component\Elm 组件实例
     */
    public function getComponent($config, $merId)
    {
        // 根据配置的类型动态处理
        switch ($config['config_type']) {
            case 'image':
                // 创建图片组件，设置图片地址和一些属性
                $component = Elm::frameImage($config['config_key'], $config['config_name'], '/' . config('admin.' . ($merId ? 'merchant' : 'admin') . '_prefix') . '/setting/uploadPicture?field=' . $config['config_key'] . '&type=1')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px')->props(['footer' => false]);
                break;
            case 'images':
                // 创建图片组组件，设置图片地址和一些属性
                $component = Elm::frameImage($config['config_key'], $config['config_name'], '/' . config('admin.' . ($merId ? 'merchant' : 'admin') . '_prefix') . '/setting/uploadPicture?field=' . $config['config_key'] . '&type=2')->maxLength(5)->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px')->props(['footer' => false]);
                break;
            case 'file':
                // 创建文件上传组件，设置上传地址和头部令牌
                $component = Elm::uploadFile($config['config_key'], $config['config_name'], rtrim(systemConfig('site_url'), '/') . Route::buildUrl('configUpload', ['field' => 'file'])->build())->headers(['X-Token' => request()->token()]);
                break;
            case 'select':
            case 'checkbox':
                // 处理下拉选择和复选框，根据配置规则生成选项
                $options = array_map(function ($val) {
                    [$value, $label] = explode(':', $val, 2);
                    return compact('value', 'label');
                }, explode("\n", $config['config_rule']));
                $component = Elm::{$config['config_type']}($config['config_key'], $config['config_name'])->options($options);
                break;
            case 'radio':
                // 处理单选框，包括联动效果
                $options = array_map(function ($val) {
                    [$value, $label] = explode(':', $val, 2);
                    return compact('value', 'label');
                }, explode("\n", $config['config_rule']));
                $component = Elm::{$config['config_type']}($config['config_key'], $config['config_name'])->options($options);
                //处理联动显示
                $controls = $this->dao->search([])->where('linked_status', 1)->where('linked_id', $config['config_id'])->column('config_id,config_key,linked_value');
                $restructuredArray = [];
                foreach ($controls as $c) {
                    $restructuredArray[$c['linked_value']][] = $c['config_key'];
                }
                $restructuredArray = array_map(function($k, $v) {
                    return ['value' => (string) $k, 'rule' => $v];
                }, array_keys($restructuredArray), $restructuredArray);
                if (!empty($restructuredArray)) {
                    $component->control($restructuredArray);
                }
                break;
            case 'switches':
                // 创建开关组件，并处理联动效果
                $component = Elm::{$config['config_type']}($config['config_key'], $config['config_name'])->activeText('开')->inactiveText('关');

                //处理联动显示
                $controls = $this->dao->search([])->where('linked_status', 1)->where('linked_id', $config['config_id'])->column('config_id,config_key,linked_value');
                $restructuredArray = [];
                foreach ($controls as $c) {
                    $restructuredArray[$c['linked_value']][] = $c['config_key'];
                }
                $restructuredArray = array_map(function($k, $v) {
                    return ['value' => (string) $k, 'rule' => $v];
                }, array_keys($restructuredArray), $restructuredArray);
                if (!empty($restructuredArray)) {
                    $component->control($restructuredArray);
                }
                break;
            default:
                // 默认情况下，直接创建对应类型的组件
                $component = Elm::{$config['config_type']}($config['config_key'], $config['config_name']);
                break;
        }
        // 设置组件的必填属性
        if ($config['required']) $component->required();
        // 处理额外的配置属性
        if ($config['config_props'] ?? '') {
            $props = @parse_ini_string($config['config_props'], false, INI_SCANNER_TYPED);
            if (is_array($props)) {
                $guidance_uri = $props['guidance_uri'] ?? '';
                $guidance_image = $props['guidance_image'] ?? '';
                if ($guidance_image) {
                    $config['guidance'] = [
                        'uri' => $guidance_uri,
                        'image' => $guidance_image,
                    ];
                }
                if (isset($props['required']) && $props['required']) {
                    $component->required();
                }
                if (isset($props['defaultValue'])) {
                    $component->value($props['defaultValue']);
                }
                unset($props['guidance_image'], $props['guidance_uri']);
                $component->props($props);
            }
        }
        // 添加额外的规则信息，如帮助信息
        if ($config['info']) {
            $component->appendRule('suffix', [
                'type' => 'guidancePop',
                'props' => [
                    'info' => $config['info'],
                    'url' => $config['guidance']['uri'] ?? '',
                    'image' => $config['guidance']['image'] ?? '',
                ]
            ]);
        }
        return $component;
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
     * 根据表单规则获取配置ID
     *
     * 本函数主要用于根据给定的系统配置分类和商家ID，通过特定的逻辑
     * 获取相应的配置ID。此过程涉及查询配置数据库并根据条件组装返回的数据。
     *
     * @param SystemConfigClassify $configClassify 系统配置分类对象，包含配置分类ID
     * @param int $merId 商家ID，用于确定是系统配置还是商家配置
     * @return mixed 返回根据表单规则处理后的配置数据
     */
    public function cidByFormRule(SystemConfigClassify $configClassify, int $merId)
    {
        // 根据配置分类ID和商家ID（0代表系统配置）查询配置信息
        $config = $this->dao->cidByConfig($configClassify->config_classify_id, $merId == 0 ? 0 : 1);
        // 获取查询到的配置项的键名集合
        $keys = $config->column('config_key');

        // 组装并返回表单规则数据，包括配置ID、分类、配置项及对应的值
        $formData = app()->make(ConfigValueRepository::class)->more($keys, $merId);
        return $this->formRule($merId, $configClassify, $config->toArray(), $formData);
    }

    /**
     * 创建或编辑配置项的表单
     *
     * 该方法用于生成一个包含各种输入字段的表单，用于创建或编辑配置项。表单字段包括配置的分类、类型、名称、键值等必需信息，
     * 以及一些可选信息如配置的说明、规则、属性等。表单的动作URL根据配置项是否有ID来决定，没有ID时创建新配置项，有ID时编辑已有的配置项。
     *
     * @param int|null $id 配置项的ID，如果为null则表示创建新配置项
     * @param array $formData 表单的初始数据，用于填充表单字段
     * @return Form 返回生成的表单对象
     */
    public function form($cid, ?int $id = null, array $formData = []): Form
    {
        $configClassifyRepository = app()->make(ConfigClassifyRepository::class);
        $linkData = $this->linkData($cid);
        if ($cid) {
            $formData['config_classify_id'] = (int)$cid;
        }
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('configSettingCreate')->build() : Route::buildUrl('configSettingUpdate', ['id' => $id])->build());
        $rule = [
            Elm::cascader('config_classify_id', '上级分类：')->options(function () use
            ($configClassifyRepository){
                return array_merge([['value' => 0, 'label' => '请选择']], $configClassifyRepository->options());
            })->placeholder('请选择上级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]]),
            Elm::select('user_type', '后台类型：', 0)->options([
                ['label' => '总后台配置', 'value' => 0],
                ['label' => '商户后台配置', 'value' => 1],
            ])->requiredNum(),
            Elm::input('config_name', '配置名称：')->placeholder('请输入配置名称')->required()->placeholder('请选择后台类型')->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => 'red'],
                'domProps' => [
                    'innerHTML' =>'配置联动显示选项，需从[ 配置分类 ]菜单中的[ 配置列表 ]进入配置页',
                ]
            ]),
        ];
        if ($cid) {
            $rule[] = Elm::radio('linked_status', '联动显示：', 0)->options([
                ['label' => '否', 'value' => 0],
                ['label' => '是', 'value' => 1],
            ])->control([
                // 当选择未通过状态时，显示未通过原因的文本区域
                ['value' => 1, 'rule' => [
                    Elm::cascader('linked_data', '上级联动：')
                        ->options($linkData)
                        ->props(['props' => ['multiple' => false, 'checkStrictly' => false, 'emitPath' => true]])
                        ->placeholder('请选择上级分类')
                ]]
            ])->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' =>'否：默认正常展示此配置；是：此配置默认隐藏，当选中下方对应配置的值时，此配置才会显示',
                ]
            ]);
        }
        $rule = array_merge($rule, [
            Elm::input('config_key', '配置key：')->placeholder('请输入配置key')->required(),
            Elm::textarea('info', '说明：')->placeholder('请输入说明'),
            Elm::select('config_type', '配置类型：')->options(function () {
                $options = [];
                foreach (self::TYPES as $value => $label) {
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->placeholder('请选择配置类型')->required(),
            Elm::textarea('config_rule', '选择项：')->placeholder('请输入选择项'),
            Elm::textarea('config_props', '配置：')->placeholder('请输入配置'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            Elm::switches('required', '必填：', 0)->activeValue(1)->inactiveValue(0)->inactiveText('关')->activeText('开'),
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关')->activeText('开'),
        ]) ;
        $form->setRule($rule);
        return $form->setTitle(is_null($id) ? '添加配置' : '编辑配置')->formData($formData);
    }

    /**
     *  获取联动表单数据
     * @param $tab_id
     * @return mixed
     * @author Qinii
     */
    public function linkData($cid)
    {
        $linkData = $this->dao->getSearch([])
            ->where('config_classify_id', $cid)
            ->whereIn('config_type',['switches','radio'])
            ->field('config_id value,config_name label,config_rule parameter,config_type')
            ->select()->toArray();

        foreach ($linkData as &$item) {
            $parameter = [];
            $parameter = explode("\n", $item['parameter']);
            if (count($parameter) > 1) {
                foreach ($parameter as $pv) {
                    $pvArr = explode(':', $pv);
                    $item['children'][] = ['label' => $pvArr[1], 'value' => (int)$pvArr[0]];
                }
            } else {
                $item['children'] = [
                    ['label' => '开', 'value' => 1],
                    ['label' => '关', 'value' => 0],
                ];
            }
            unset($item['parameter'], $item['config_type']);
        }
        return $linkData;
    }

    /**
     * 更新表单数据。
     * 该方法通过指定的ID获取表单数据，并使用这些数据来更新表单。
     * 主要用于在前端展示已存在数据的表单，以便用户可以查看并修改这些数据。
     *
     * @param int $id 表单数据的唯一标识ID。
     * @return array 返回包含表单数据的数组。
     */
    public function updateForm(int $id)
    {
        // 通过ID获取表单数据，并转换为数组格式，用于更新表单
        $formData = $this->dao->get($id)->append(['linked_data'])->toArray();
        return $this->form($formData['config_classify_id'],$id, $formData);
    }

    /**
     * 根据条件获取配置列表
     *
     * 本函数用于根据给定的条件数组$where，从数据库中检索配置列表。它支持分页查询，每页返回$limit条记录，从$page页开始。
     * 查询结果包括配置项的列表和总数，方便前端进行分页显示。
     *
     * @param array $where 查询条件，以键值对形式表示，用于构建SQL的WHERE子句。
     * @param int $page 当前页码，用于确定要返回哪一页的记录。
     * @param int $limit 每页返回的记录数，用于控制分页大小。
     * @return array 返回一个包含两个元素的数组，'count'表示记录总数，'list'表示当前页的配置项列表。
     */
    public function lst(array $where, int $page, int $limit)
    {
        // 根据$where条件搜索配置项
        $query = $this->dao->search($where);

        // 计算满足条件的配置项总数
        $count = $query->count();

        // 对查询结果进行分页，每页返回$limit条记录，并处理配置项的'typeName'属性
        // 通过闭包函数动态获取'types'数组中对应'config_type'的值作为'typeName'
        // 隐藏'config_classify_id'字段，并附加'typeName'字段后进行查询
        $list = $query->page($page, $limit)
                      ->withAttr('typeName', function ($value, $data) {
                          return self::TYPES[$data['config_type']];
                      })
                      ->hidden(['config_classify_id'])
                      ->append(['typeName'])
                      ->select();

        // 返回记录总数和分页后的配置项列表
        return compact('count', 'list');
    }

    /**
     * 根据分类组和商家ID生成表单选项
     * 该方法主要用于生成基于不同分类的配置表单。它通过遍历分类列表，并为每个分类生成相应的表单字段。
     * @param object $group 分类组对象，包含配置分类ID等信息。
     * @param int $merId 商家ID，用于确定表单数据的保存路径。
     * @param int $tab_id 当前选中的标签页ID，用于设置默认选中的标签页。
     * @return object 返回生成的表单对象，设置了标题和表单内容。
     */
    public function tabForm($group, $merId, $tab_id = 0)
    {

        $make = app()->make(ConfigClassifyRepository::class);
        $list = $make->children($group->config_classify_id, 'config_classify_id,classify_key,classify_name,info');
        $children = [];
        $name = '';
        foreach ($list as $item) {
            $_children = $this->cidByFormRule($make->keyByData($item['classify_key']), $merId)->formRule();
            if ($tab_id && $tab_id == $item['config_classify_id']) {
                $name = $item['classify_key'];
            }
            if ($item['info']) {
                array_unshift($_children, [
                    'type' => 'el-alert',
                    'props' => [
                        'type' => 'warning',
                        'closable' => false,
                        'title' => $item['info']
                    ]
                ], ['type' => 'div', 'style' => ['height' => '20px', 'width' => '100%']]);
            }
            $children[] = [
                'type' => 'el-tab-pane',
                'props' => [
                    'label' => $item['classify_name'],
                    'name' => $item['classify_key']
                ],
                'children' => $_children
            ];
        }
        if ($group['classify_key'] === 'distribution_tabs') {
            $action = Route::buildUrl('configOthersSettingUpdate')->build();
        } else {
            $action = Route::buildUrl($merId ? 'merchantConfigSave' : 'configSave', ['key' => $group['classify_key']])->build();
        }
        $form = Elm::createForm($action, [
            [
                'type' => 'el-tabs',
                'native' => true,
                'props' => [
                    'value' => $name ?: $list[0]['classify_key'] ?? ''
                ],
                'children' => $children
            ]
        ]);

        return $form->setTitle($group['classify_name']);
    }

    /**
     * 创建上传配置表单
     * 该方法用于生成上传配置的表单，根据不同的上传类型（如本地、七牛云、阿里云OSS等），
     * 动态配置表单的规则，以便用户可以根据需求配置不同的上传方式。
     *
     * @return \EasyWeChat\Kernel\Messages\ElementForm|Form
     */
    public function uploadForm()
    {
        // 获取上传类型配置
        $config = $this->getWhere(['config_key' => 'upload_type']);

        // 根据上传类型配置，获取对应的组件规则
        $rule = $this->getComponent($config, 0)->value(systemConfig('upload_type'));

        // 实例化配置分类仓库
        $make = app()->make(ConfigClassifyRepository::class);

        // 配置不同的上传类型对应的表单规则
        $rule->control([
            [
                'value' => '1',
                'rule' => $this->cidByFormRule($make->keyByData('local'), 0)->formRule()
            ],
            [
                'value' => '2',
                'rule' => $this->cidByFormRule($make->keyByData('qiniuyun'), 0)->formRule()
            ],
            [
                'value' => '3',
                'rule' => $this->cidByFormRule($make->keyByData('aliyun_oss'), 0)->formRule()
            ],
            [
                'value' => '4',
                'rule' => $this->cidByFormRule($make->keyByData('tengxun'), 0)->formRule()
            ],
            [
                'value' => '5',
                'rule' => $this->cidByFormRule($make->keyByData('huawei_obs'), 0)->formRule()
            ],
            [
                'value' => '6',
                'rule' => $this->cidByFormRule($make->keyByData('ucloud'), 0)->formRule()
            ],
            [
                'value' => '7',
                'rule' => $this->cidByFormRule($make->keyByData('jdoss'), 0)->formRule()
            ],
            [
                'value' => '8',
                'rule' => $this->cidByFormRule($make->keyByData('ctoss'), 0)->formRule()
            ],
        ]);
        return Elm::createForm(Route::buildUrl('systemSaveUploadConfig')->build(), [$rule])->setTitle('上传配置');
    }

    /**
     * 保存上传配置
     *
     * 该方法用于根据传入的上传类型数据，保存相应的上传配置。它支持多种上传方式，
     * 通过switch语句根据上传类型确定具体的上传配置键名。然后，在数据库事务中，
     * 先更新上传类型配置，如果上传类型有具体的配置键名，则进一步保存该上传方式的详细配置。
     * 这样做的目的是确保上传配置的完整性和一致性。
     *
     * @param array $data 包含上传类型等信息的数据数组
     */
    public function saveUpload($data)
    {
        // 实例化配置值仓库，用于后续保存配置数据
        $configValueRepository = app()->make(ConfigValueRepository::class);
        // 默认上传类型为1，代表本地上传
        $uploadType = $data['upload_type'] ?? '1';
        // 根据上传类型确定具体的上传方式键名
        $key = '';
        switch ($uploadType) {
            case 1:
                $key = 'local';
                break;
            case 2:
                $key = 'qiniuyun';
                break;
            case 3:
                $key = 'aliyun_oss';
                break;
            case 4:
                $key = 'tengxun';
                break;
            case 5:
                $key = 'huawei_obs';
                break;
            case 6:
                $key = 'ucloud';
                break;
            case 7:
                $key = 'jdoss';
                break;
            case 8:
                $key = 'ctoss';
                break;
        }

        // 使用数据库事务来确保配置更新和保存操作的一致性
        Db::transaction(function () use ($data, $key, $uploadType, $configValueRepository) {
            // 更新上传类型配置
            $configValueRepository->setFormData([
                'upload_type' => $uploadType
            ], 0);
            // 如果上传方式有具体的配置键名，则进一步保存上传方式的详细配置
            if ($key) {
                // 实例化配置分类仓库，用于获取配置分类ID
                $make = app()->make(ConfigClassifyRepository::class);
                // 根据上传方式键名获取对应的配置分类ID，如果获取失败则返回错误信息
                if (!($cid = $make->keyById($key))) return app('json')->fail('保存失败');
                // 保存上传方式的详细配置
                $configValueRepository->save($cid, $data, 0);
            }
        });
    }

    /**
     * 创建微信配置上传校验文件的表单
     *
     * 本函数用于生成一个表单，该表单旨在上传一个校验文件以配置微信相关功能。
     * 它首先尝试从缓存中获取之前可能已上传的校验文件路径，如果文件不存在，则清空该路径。
     * 接着，利用ElementUI的表单构建器创建表单，并设置表单的验证规则，包括一个文件上传字段。
     * 最后，返回构造好的表单，表单标题为“上传校验文件”，并包含之前获取的校验文件路径数据。
     *
     * @return \Illuminate\Http\Response 返回构造好的表单视图。
     */
    public function wechatForm()
    {
        // 从缓存中获取微信校验文件的路径
        $formData['wechat_chekc_file'] = app()->make(CacheRepository::class)->getWhere(['key' => 'wechat_chekc_file']);
        // 如果文件路径存在但文件实际不存在，则清空文件路径
        if ($formData['wechat_chekc_file'] && !is_file($formData['wechat_chekc_file'])) {
            $formData['wechat_chekc_file'] = '';
        }

        // 创建表单实例，表单提交地址为配置微信上传设置的路由
        $form = Elm::createForm(Route::buildUrl('configWechatUploadSet')->build());

        // 设置表单验证规则，包括一个文件上传字段
        $form->setRule([
            // 文件上传字段，用于上传微信校验文件，设置文件上传的URL地址和安全令牌
            Elm::uploadFile('wechat_chekc_file', '上传校验文件：', rtrim(systemConfig('site_url'), '/') . Route::buildUrl('configUploadName', ['field' => 'file'])->build())->headers(['X-Token' => request()->token()]),
        ]);

        // 设置表单标题并返回表单实例，包含之前获取的微信校验文件路径数据
        return $form->setTitle('上传校验文件')->formData($formData);
    }

    /**
     * 替换appid
     * @param string $appid
     * @param string $projectanme
     */
    public function updateConfigJson($appId = '', $projectName = '', $path = '')
    {
        $fileUrl = $path . "/download/project.config.json";
        $string = file_get_contents($fileUrl); //加载配置文件
        // 替换appid
        $appIdOld = '/"appid"(.*?),/';
        $appIdNew = '"appid"' . ': ' . '"' . $appId . '",';
        $string = preg_replace($appIdOld, $appIdNew, $string); // 正则查找然后替换
        // 替换小程序名称
        $projectNameOld = '/"projectname"(.*?),/';
        $projectNameNew = '"projectname"' . ': ' . '"' . $projectName . '",';
        $string = preg_replace($projectNameOld, $projectNameNew, $string); // 正则查找然后替换
        $newFileUrl = $path . "/download/project.config.json";
        @file_put_contents($newFileUrl, $string); // 写入配置文件
    }

    /**
     * 替换url
     * @param $url
     */
    public function updateUrl($url, $path)
    {
        $fileUrl = $path . "/download/common/vendor.js";

        $string = file_get_contents($fileUrl); //加载配置文件
        $string = str_replace('https://mer.crmeb.net', $url, $string); // 正则查找然后替换

        $ws = str_replace('https', 'wss', $url);
        $string = str_replace('wss://mer.crmeb.net', $ws, $string); // 正则查找然后替换

        $newFileUrl = $path . "/download/common/vendor.js";
        @file_put_contents($newFileUrl, $string); // 写入配置文件
    }

    /**
     * 关闭直播
     * @param int $iszhibo
     */
    public function updateAppJson($path)
    {
        $fileUrl = $path . "/download/app.json";
        $string = file_get_contents($fileUrl); //加载配置文件
        $pats = '/,
      "plugins": \{
        "live-player-plugin": \{
          "version": "(.*?)",
          "provider": "(.*?)"
        }
      }/';
        $string = preg_replace($pats, '', $string); // 正则查找然后替换
        $newFileUrl = $path . "/download/app.json";
        @file_put_contents($newFileUrl, $string); // 写入配置文件
    }

    /**
     * 去掉菜单
     * @param int $iszhibo
     */
    public function updateRouteJson($path)
    {
        $fileUrl = $path . "/download/app.json";
        $string = file_get_contents($fileUrl); //加载配置文件
        $pats = '/
      {
        "pagePath": "pages\/plant_grass\/index",
        "iconPath": "static\/images\/5-001.png",
        "selectedIconPath": "static\/images\/5-002.png",
        "text": "逛逛"
      },/';
        $string = preg_replace($pats, '', $string); // 正则查找然后替换
        $newFileUrl = $path . "/download/app.json";
        @file_put_contents($newFileUrl, $string); // 写入配置文件
    }

    /**
     * 请求方式
     * @param $path
     * @param bool $plant
     * @author Qinii
     * @day 1/4/22
     */
    public function updatePlantJson(string $path, int $plant)
    {
        $fileUrl = $path . "/download/common/vendor.js";
        $string = file_get_contents($fileUrl); //加载配置文件
        $string = str_replace('"-openPlantGrass-"', $plant ? 'true' : 'false', $string); // 正则查找然后替换
        $newFileUrl = $path . "/download/common/vendor.js";
        @file_put_contents($newFileUrl, $string); // 写入配置文件
    }

    /**
     *  根据配置分类的key获取配置项key
     * @param string $key
     * @return array
     * @author Qinii
     */
    public function getConfigKey(string $key)
    {
        $repository = app()->make(ConfigClassifyRepository::class);
        $config_classify = $repository->getSearch(['classify_key' => $key])->find();
        $config_keys = [];
        if ($config_classify) {
            $config_keys = $this->dao->search(['config_classify_id' => $config_classify['config_classify_id']])->column('config_name,config_key');
        }
        $config_value = [];
        if ($config_keys) {
            $config_value = systemConfig(array_column($config_keys,'config_key'));
        }
       return compact('config_keys','config_value');
    }
}
