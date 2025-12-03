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


namespace app\common\repositories\system\admin;


//附件
use app\common\dao\system\admin\AdminDao;
use app\common\model\system\admin\Admin;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\auth\RoleRepository;
use crmeb\exceptions\AuthException;
use crmeb\services\JwtTokenService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Route;
use think\Model;
use app\common\repositories\system\merchant\MerchantRegionRepository;


/**
 * 后台管理员
 */
class AdminRepository extends BaseRepository
{
    public function __construct(AdminDao $dao)
    {
        /**
         * @var AdminDao
         */
        $this->dao = $dao;
    }

    /**
     * 获取角色列表
     *
     * 根据给定的条件数组$where，分页获取角色列表。每页包含$limit个角色。
     * 返回包含角色列表和总角色数量的数据数组。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页的角色数量
     * @return array 包含角色列表和总角色数量的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件查询角色信息
        $query = $this->dao->search($where);

        // 计算满足条件的角色总数量
        $count = $query->count();

        // 获取当前页的角色列表，隐藏某些字段，并进行分页
        $list = $query->page($page, $limit)->hidden(['pwd', 'is_del', 'update_time'])->select()->append(['region_name']);

        // 遍历角色列表，为每个角色添加角色名
        foreach ($list as $k => $role) {
            $list[$k]['rule_name'] = $role->roleNames();
        }

        // 返回角色列表和总数量
        return compact('list', 'count');
    }

    /**
     * 对密码进行加密处理
     *
     * 本函数使用BCrypt算法对密码进行加密。BCrypt是一种基于口令的加密算法，设计用于存储和验证用户密码。
     * 选择BCrypt算法是因为其在安全性和抗暴力破解方面的优势。
     *
     * @param string $password 需要加密的原始密码
     * @return string 返回加密后的密码hash值
     */
    public function passwordEncode($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 更新
     * @param int $id id
     * @param array $data 数组
     * @return int
     * @throws DbException
     * @author 张先生
     * @date 2020-03-26
     */
    public function update(int $id, array $data)
    {
        if (isset($data['roles']))
            $data['roles'] = implode(',', $data['roles']);
        return $this->dao->update($id, $data);
    }

    /**
     * 创建修改密码的表单
     *
     * 该方法用于生成一个用于修改密码的表单。表单的提交URL根据$isSelf参数的不同而变化，
     * 用于区分是修改当前管理员的密码还是其他管理员的密码。表单包含两个密码字段，
     * 用于输入新密码和确认新密码。
     *
     * @param int $id 管理员ID，当$isSelf为false时，用于指定修改哪个管理员的密码。
     * @param bool $isSelf 指示是否修改当前管理员的密码。如果为true，则修改当前管理员的密码；
     *                     如果为false，则修改指定ID的管理员的密码。
     * @return Form|\think\form\Form
     */
    public function passwordForm(int $id, $isSelf = false)
    {
        // 根据$isSelf参数构建表单的提交URL
        $url = Route::buildUrl($isSelf ? 'systemAdminEditPassword' : 'systemAdminPassword', $isSelf ? [] : compact('id'))->build();

        // 创建表单，并定义表单包含的字段：密码和确认密码
        $form = Elm::createForm($url, [
            $rules[] = Elm::password('pwd', '密码：')->placeholder('请输入密码')->required(),
            $rules[] = Elm::password('againPassword', '确认密码：')->placeholder('请输入确认密码')->required(),
        ]);

        // 设置表单的标题
        return $form->setTitle('修改密码');
    }

    /**
     * 创建编辑管理员信息的表单
     *
     * 该方法通过Elm库构建一个表单，用于编辑管理员的个人信息。表单中包含了管理员ID、姓名和电话等必填项。
     * 表单的提交地址是通过路由系统生成的管理员编辑地址。
     *
     * @param array $formData 管理员的当前数据，用于填充表单
     * @return Form|\think\form\Form
     */
    public function editForm(array $formData)
    {
        // 创建表单对象，并设置表单的提交URL
        $form = Elm::createForm(Route::buildUrl('systemAdminEdit')->build());

        // 设置表单的验证规则和字段
        $form->setRule([
            // 设置管理员姓名字段，必填，带有占位提示
            Elm::input('admin_id', '管理员ID：')->disabled(true),
            Elm::input('real_name', '管理员姓名：')->placeholder('请输入管理员姓名')->required(),
            // 设置联系电话字段，非必填，带有占位提示
            Elm::input('phone', '联系电话：')->placeholder('请输入联系电话')
        ]);

        // 设置表单标题，并加载传入的管理员数据
        return $form->setTitle('修改信息')->formData($formData);
    }

    /**
     * 创建或编辑管理员表单
     *
     * 该方法用于生成一个包含各种输入字段的表单，用于创建或编辑系统管理员。
     * 表单字段包括选择角色、管理员姓名、账号、电话，以及在创建时的密码和确认密码。
     * 表单的URL和提交动作根据是否在编辑现有管理员的情况下动态确定。
     *
     * @param int|null $id 管理员ID，如果为null，则表示正在创建新管理员；否则，表示正在编辑现有管理员。
     * @param array $formData 表单数据数组，用于预填充表单字段。
     * @return Form 返回生成的表单对象。
     */
    public function form(?int $id = null, array $formData = []): Form
    {
        // 根据$id的值决定表单提交的URL，如果是新建则指向create路由，否则指向update路由，并带上$id。
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemAdminCreate')->build() : Route::buildUrl('systemAdminUpdate', ['id' => $id])->build());

        // 定义表单的验证规则和字段。
        $rules = [
            // 选择角色字段，使用多重选择，选项从RoleRepository获取。
            Elm::select('roles', '身份：', [])->options(function () {
                $data = app()->make(RoleRepository::class)->getAllOptions(0);
                $options = [];
                foreach ($data as $value => $label) {
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->multiple(true)->required(),

            // 管理员姓名输入字段。
            Elm::input('real_name', '管理员姓名：')->placeholder('请输入管理员姓名'),
            // 账号输入字段，必需。
            Elm::input('account', '账号：')->placeholder('请输入账号')->required(),
            // 电话输入字段。
            Elm::input('phone', '联系电话：')->placeholder('请输入联系电话'),
            Elm::cascader('region_ids', '选择管理分组：')->options(function (){
                $data =  app()->make(MerchantRegionRepository::class)->getAllOptions(null);
                return formatCascaderData($data,'name');
            })->props(['props' => ['checkStrictly' => true, 'emitPath' => false, 'multiple' => true]]),
        ];
        // 如果是新建管理员，则添加密码和确认密码字段。
        if (!$id) {
            $rules[] = Elm::password('pwd', '密码：')->placeholder('请输入密码')->required();
            $rules[] = Elm::password('againPassword', '确认密码：')->placeholder('请输入确认密码')->required();
        }
        // 开启/关闭状态开关字段。
        $rules[] = Elm::switches('status', '账号状态：', 1)->width(60)->inactiveValue(0)->activeValue(1)->inactiveText('停用')->activeText('正常');
        // 设置表单的验证规则。
        $form->setRule($rules);
        // 设置表单标题和初始数据。
        return $form->setTitle(is_null($id) ? '添加管理员' : '编辑管理员')->formData($formData);
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
        return $this->form($id, $this->dao->get($id)->toArray());
    }

    /**
     * 管理员登录方法
     *
     * 本方法用于处理管理员的登录逻辑。首先，它触发一个登录前的事件，允许任何监听者对此过程进行干预。
     * 接着，它验证管理员账号和密码是否正确。如果登录信息不正确，会记录登录失败次数，并抛出一个验证异常。
     * 如果账号存在但被禁用，也会抛出一个验证异常。如果账号验证成功，更新管理员的登录信息，如最后登录时间、IP和登录次数，
     * 并触发一个登录成功的事件。最后，返回管理员信息。
     *
     * @param string $account 管理员账号
     * @param string $password 管理员密码
     * @return array|object
     * @throws ValidateException 如果登录失败或账号被禁用
     */
    public function login(string $account, string $password)
    {
        // 触发登录前的事件，允许进行额外的验证或操作
        event('admin.login.before',compact('account','password'));

        // 根据管理员账号查询管理员信息
        $adminInfo = $this->dao->accountByAdmin($account);

        // 验证管理员信息是否存在且密码是否正确
        if (!$adminInfo || !password_verify($password, $adminInfo->pwd)){
            // 记录登录失败次数，防止恶意登录尝试
            $key = 'sys_login_failuree_'.$account;
            $numb = Cache::get($key) ?? 0;
            $numb++;
            Cache::set($key,$numb,15*60);
            throw new ValidateException('账号或密码错误');
        }

        // 检查管理员账号是否被禁用
        if ($adminInfo['status'] != 1)
            throw new ValidateException('账号已关闭');

        // 更新管理员的登录信息
        $adminInfo->last_time = date('Y-m-d H:i:s');
        $adminInfo->last_ip = app('request')->ip();
        $adminInfo->login_count++;
        $adminInfo->save();

        // 触发登录成功的事件，允许进行额外的操作，如记录日志等
        event('admin.login',compact('adminInfo'));

        // 返回管理员信息
        return $adminInfo;
    }

    /**
     * 登录尝试次数限制
     * @param $account
     * @param int $number
     * @param int $n
     * @author Qinii
     * @day 7/6/21
     */
    public function loginFailure($account,$number = 5,$n = 3)
    {
        $key = 'sys_login_failuree_'.$account;
        $numb = Cache::get($key) ?? 0;
        $numb++;
        if($numb >= $number){
            $fail_key = 'sys_login_freeze_'.$account;
            Cache::tag('sys_login_freeze')->set($fail_key,1,15*60);
            throw new ValidateException('账号或密码错误次数太多，请稍后在尝试');
        }else{
            Cache::tag('sys_login_freeze')->set($key,$numb,5*60);
            $msg = '账号或密码错误';
            $_n = $number - $numb;
            if($_n <= $n){
                $msg .= ',还可尝试'.$_n.'次';
            }
            throw new ValidateException($msg);
        }
    }

    /**
     * 缓存管理员令牌
     *
     * 本函数用于缓存管理员的登录令牌，以便在后续请求中验证管理员的身份。
     * 令牌的缓存时间由$exp参数指定，单位为秒。缓存机制能够减少数据库的访问频率，
     * 提高系统性能，尤其是在高并发的场景下。
     *
     * @param string $token 管理员的登录令牌
     * @param int $exp 令牌的过期时间，单位为秒
     */
    public function cacheToken(string $token, int $exp)
    {
        // 通过键名'admin_'和当前时间加上过期时间来设置令牌的缓存
        Cache::set('admin_' . $token, time() + $exp, $exp);
    }

    /**
     * 检查管理员的令牌是否有效。
     *
     * 本函数通过验证令牌是否存在以及是否过期来确保管理员的会话仍然有效。
     * 如果令牌不存在或已过期，则抛出一个授权异常。
     *
     * @param string $token 管理员的令牌。这是用于验证管理员身份的唯一字符串。
     * @throws AuthException 如果令牌无效或已过期，则抛出此异常。
     */
    public function checkToken(string $token)
    {
        // 检查令牌是否存在于缓存中
        $has = Cache::has('admin_' . $token);
        // 如果令牌不存在，则抛出授权异常
        if (!$has)
            throw new AuthException('无效的token');

        // 获取令牌的最后活动时间
        $lastTime = Cache::get('admin_' . $token);
        // 检查令牌是否过期，如果过期，则抛出授权异常
        if (($lastTime + (intval(Config::get('admin.token_valid_exp', 15))) * 60) < time())
            throw new AuthException('token 已过期，请重新登录');
    }

    /**
     * 更新管理员令牌的缓存
     *
     * 本函数用于更新管理员令牌的缓存时间。令牌的缓存时间基于配置文件中设定的令牌有效时长，
     * 默认为15分钟。通过将令牌的缓存时间设置为当前时间，可以确保令牌在一段时间内保持有效。
     * 这对于维护会话状态和确保安全性非常重要。
     *
     * @param string $token 管理员的令牌字符串。这是用于标识和验证管理员身份的唯一令牌。
     */
    public function updateToken(string $token)
    {
        // 根据管理员令牌生成缓存键，并设置缓存时间为配置的令牌有效时长（默认为15分钟）。
        Cache::set('admin_' . $token, time(), intval(Config::get('admin.token_valid_exp', 15)) * 60);
    }

    /**
     * 清除指定的管理员令牌
     *
     * 本函数用于从缓存中删除指定的管理员令牌，以实现登出功能或令牌失效。
     * 通过删除令牌，可以确保之前的登录状态被正确终止，防止未授权的访问。
     *
     * @param string $token 需要清除的管理员令牌
     */
    public function clearToken(string $token)
    {
        // 根据管理员令牌生成缓存键，并删除该缓存项
        Cache::delete('admin_' . $token);
    }

    /**
     * 创建管理员令牌
     *
     * 本函数用于生成管理员的JWT令牌，该令牌用于管理员的身份验证。
     * 它通过JwtTokenService创建令牌，并将令牌及其过期时间存储到缓存中。
     *
     * @param Admin $admin 管理员对象，包含管理员的身份信息。
     * @return array 返回包含令牌和过期时间的数组。
     */
    public function createToken(Admin $admin)
    {
        // 实例化JWT令牌服务类
        $service = new JwtTokenService();

        // 从配置中获取管理员令牌的过期时间，默认为3小时
        $exp = intval(Config::get('admin.token_exp', 3));

        // 使用JwtTokenService创建令牌，指定管理员ID、类型为'admin'，以及过期时间
        $token = $service->createToken($admin->admin_id, 'admin', strtotime("+ {$exp}hour"));

        // 将生成的令牌及其过期时间存储到缓存中
        $this->cacheToken($token['token'], $token['out']);

        // 返回生成的令牌信息
        return $token;
    }

    /**
     * 检测验证码
     * @param string $key key
     * @param string $code 验证码
     * @author 张先生
     * @date 2020-03-26
     */
    public function checkCode(string $key, string $code)
    {
        if (!env('DEVELOPMENT',false)) {
            $_code = Cache::get('am_captcha' . $key);
            if (!$_code) {
                throw new ValidateException('验证码过期');
            }

            if (strtolower($_code) != strtolower($code)) {
                throw new ValidateException('验证码错误');
            }

            //删除code
            Cache::delete('am_captcha' . $key);
        }
    }

    /**
     * 创建登录验证码键
     *
     * 本函数用于生成一个唯一的登录验证码键，并将该验证码与键关联起来存储在缓存中。
     * 验证码键的生成结合了微秒时间和随机数，以确保唯一性。
     * 验证码在缓存中的有效期通过配置文件设定，以分钟为单位。
     *
     * @param string $code 登录验证码
     * @return string 生成的验证码键
     */
    public function createLoginKey(string $code)
    {
        // 生成一个唯一的验证码键，基于当前微秒时间戳和随机数
        $key = uniqid(microtime(true), true);

        // 将验证码与键关联，存储到缓存中，并设定过期时间
        // 缓存有效期通过配置文件获取，默认为5分钟
        Cache::set('am_captcha' . $key, $code, Config::get('admin.captcha_exp', 5) * 60);

        // 返回生成的验证码键
        return $key;
    }
}
