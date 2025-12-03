#!/bin/base
PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH

#宝塔代码路径
panel_path="/www/server/panel"
bt_path=''

#php的路径
php_path=''

#当前项目路径
root_path=$(pwd);

#脚本当前使用的 php 版本；
version=''
_php=0.0

#获取项目类型
projct=''
#各项目可用的 php 版本
declare -A projct_allow_phps=(
  ["mer"]="71 72 73 74"
  ["mul"]="71 72 73 74"
  ["pro"]="80"
)

#各项目的label
declare -A crmeb_label=(
  ["mer"]="10"
  ["mul"]="3"
  ["pro"]="42"
)

api='http://authorize.crmeb.net/api/auth_cert_query'

#获取宝塔的调用地址
function get_bt_parot(){
    port=$(head -n +1 "$panel_path"'/data/port.pl')
    bt_path='https://0.0.0.0:'$port
}

##
#获取 php 版本
##
function get_php_version(){
    # 允许 PHP 的版本
    phps_string="${projct_allow_phps[$projct]}"
    echo
    echo -e "\033[0;32m ｜ -------------------------------------------- \033[0m"
    echo -e "\033[0;32m ｜ 如果需要指定PHP版本请输入 "$phps_string"        \033[0m"
    echo -e "\033[0;32m ｜ 不指定 PHP 版本号将直接获取命令行 PHP 版本号       \033[0m"
    echo -e "\033[0;32m ｜ -------------------------------------------- \033[0m"
    echo -e "\033[0;32m ｜ 是否需要指定PHP版本，不指定直接回车 ？             \033[0m"
	read u_php
	if [ ! $u_php ] ; then
	    is_success "未指定 PHP 版本号将直接获取命令行 PHP 版本号"
	    #获取当前服务器命令行的 php 版本
        _php=$(php -v | head -n 1 | cut -d " " -f 2|cut -d "." -f 1,2);
        u_php="${_php//./}"
        is_success "您当前的命令行PHP 版本为：$u_php"
    else
        is_success "指定 PHP 版本为 $u_php"
    fi
    IFS=" " read -r -a allow_phps <<< "$phps_string"
    for element in "${allow_phps[@]}"
    do
        # 检查当前元素是否与在循序的 php 版本中
        [ "$element" = "$u_php" ] && version=$u_php
    done
    if [ ! $version ]; then
        is_error "PHP 版本为：$u_php,暂不支持该版本"
    fi
    php_path='/www/server/php/'$version
}

##
#检查 php 扩展
##
function check_php_extension(){
    [ ! $php_path ] && is_error '未获取到 PHP 版本号，停止执行'
    need_extensions=('swoole' 'swoole_loader' 'fileinfo' 'redis' 'zip')
    install=false
    for extension in "${need_extensions[@]}";
    do
        if php -m | grep -q "^$extension$" ; then
            is_success "$extension 扩展存在"
        else
            if [ $extension == 'swoole_loader' ] ; then
                install=true
            else
                is_error "$extension扩展不存在,请在宝塔界面操作安装;参考文档：https://doc.crmeb.com/mer/mer2/7314"
            fi
        fi
    done
    [ $install == 'true' ] && install_swoole_laoder
}

##
# 安装 swoole_loader
##
function install_swoole_laoder(){
    echo '需要安装 swoole_loader，开始处理'
    echo '>>>>>>>>>>>>>>>>>>>>>>'
    #sleep 3
    if [ $version == 'false' ]; then
        get_php_version
    fi
    # 使用php-config命令获取PHP的配置信息
    php_config=$(/www/server/php/$version/bin/php-config --configure-options)
    # 在配置信息中搜索线程安全选项
    if grep -q -- '--enable-maintainer-zts' <<< "$php_config"; then
        name='swoole_loader'$version'_zts.so';
        name1='swoole_loader'$version'.so';
    else
        name='swoole_loader'$version'.so';
        name1='swoole_loader'$version'_zts.so';
    fi
    mv_swoole_loader $name
	if php -m | grep -q "swoole_loader" ; then
        is_success "$extension 扩展已安装成功"
    else
        echo "swoole_loader安装失败，开始尝试重新安装"
        echo '>>>>>>>>>>>>>>>>>>>>>>'
        mv_swoole_loader $name1
       	! php -m | grep -q "swoole_loader" && is_error '重新安装尝试失败，请尝试其他方法'
    fi
	is_success '操作完成'
}


##
# 获取swooel—loader 原文件地址
##
function get_loader_path(){
    if [ $projct == 'mer' ] ; then
        #swoole_loader存放地址
        swoole_loader_path=$root_path'/install/swoole-loader'
    else
        #swoole_loader存放地址
        swoole_loader_path=$root_path'/help/swoole_loader'
    fi
}

##
# 移动扩展
##
function mv_swoole_loader(){
    get_loader_path
    swoole_loader_name=$1
    swoole_loader_fiel=$swoole_loader_path"/$swoole_loader_name"
    [ ! -f $swoole_loader_fiel ] && is_error "$swoole_loader_fiel,文件不存在,请讲当前脚本放置项目代码根目录，在重新执行。"
    ext_name=$(ls $php_path"/lib/php/extensions")
    if [ ! -f $php_path"/lib/php/extensions/$ext_name/$swoole_loader_name" ]; then
        /bin/cp $swoole_loader_fiel $php_path"/lib/php/extensions/"$ext_name
    fi

    sed -i '/swoole_loader/d' $php_path/etc/php.ini
    if [ -f $php_path/etc/php-cli.ini ]; then
      sed -i '/swoole_loader/d' $php_path/etc/php-cli.ini
    fi

    echo -e "\nextension = $swoole_loader_name\n" >>$php_path/etc/php.ini
    if [ -f $php_path/etc/php-cli.ini ]; then
      echo -e "\nextension = $swoole_loader_name\n" >>$php_path/etc/php-cli.ini
    fi
    service php-fpm-$version reload
}

##
# 替换加密文件
##
function change_encypt(){
    [ ! $php_path ] && is_error '未获取到 PHP 版本号，停止执行'
    is_success '开始处理加密文件'
    echo '尝试关闭 swoole'
    php$version think swoole stop
    if [ $projct == 'mer' ] ;then
        encypt_name="compiled$version.zip"
        encypt=$root_path/install/compiled/$encypt_name
        [ ! -f $encypt ] && is_error "加密文件不存在:$encypt"
        cp -f $encypt "$root_path/crmeb"
        unzip -q -o  $root_path/crmeb/$encypt_name  -d $root_path/crmeb && mv $root_path/crmeb/crmeb.php $root_path/config
        is_success '加密文件已替换，尝试重启 swoole'
        [ ! -d $root_path'/runtime' ] && mkdir $root_path'/runtime'
        php$version think swoole restart
    else
        encypt=$root_path/help/$_php
        [ ! -d $encypt ] && is_error "没有符合版本的加密文件【$_php】"
        cp -Rf "$encypt"/* $root_path
        php$version think swoole
    fi
}


function is_success(){
    echo -e "\033[0;32m ｜ $1 \033[0m"
}

function is_error(){
    echo -e "\033[0;31m -------------------------------------------- \033[0m"
    echo -e "\033[0;31m ｜错误｜：$1 \033[0m"
    echo -e "\033[0;31m -------------------------------------------- \033[0m"
    exit 0 ;
}

##
# 获取当前项目所属产品
##
function __init(){
    [ ! -f "$panel_path"'/data/port.pl' ] && is_error "当前脚本仅限于在宝塔环境中使用"
    if [ ! -f "./.version" ];then
       		echo -e "\033[0;31m
未读取到.version文件;
检查是否目录错误：请将此脚本移动到项目目录下，例如：/www/wwwroot/crmeb.net；
是否删除了.version文件：请将源码中的.version文件复制到项目根目录即可;
重新执行脚本，命令：bash auto.sh;
 \033[0m"
        exit 0
    fi
    code_version=$(head -n +1 ./.version)
    string=${code_version##*=}  # 去掉等号及其前面的内容
    # 判断版本类型
    if [[ $string == *"CRMEB-PRO-S"* ]]; then
        projct="pro"
    elif [[ $string == *"CRMEB-MER-M"* ]]; then
        projct="mul"
    elif [[ $string == *"CRMEB-MER"* ]]; then
        projct="mer"
    else
        is_error "无法确定 CRMEB 项目类型或未安装指定版本"
    fi
    is_success "获取当前项目为："$projct
}

##
# swoole_loader安装
##
function swoole_loader(){
    __init
    get_php_version
    check_php_extension
    is_success "完成"
    exit 0;
}

##
# 加密文件替换
##
function encypt(){
    __init
    get_php_version
    change_encypt
    exit 0;
}

##
# 授权域名失败检测
##
function auth(){
    __init
    echo
    echo -e "\033[0;32m ｜ -------------------------------------------- \033[0m"
    echo -e "\033[0;32m ｜ 请输入授权域名，例如：crmeb.net                  \033[0m"
    echo -e "\033[0;32m ｜ 如果当前文件夹【目录名】就是【授权域名】直接回车 ？   \033[0m"
    if [ ! -f "$root_path/cert_public.pam" ] ; then
        echo "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCu8tEgg4uBv72HX7/24YNJIuCs
pcYHOemMx2wyh72Ke9uRs36pQaSF7IvrVjXc1AL5GeFzQRGi80hcNu46tTPSNKlt
cakkPgFkanVNjkTkhdxrcOUSEce1WxdMSaM7rZFm3CfK0vGWQSVUZvIgUxjlCcqS
EyMvmfS9o4kGAVlBLQIDAQAB
-----END PUBLIC KEY-----
" >> $root_path/cert_public.pam
    fi
    read host
    [ ! $host ] && host=$(basename "$(pwd)")
    label="${crmeb_label[$projct]}"
    [ ! $label ] && is_error "未找到对应项目label，请检查版本信息"
    param="domain_name=$host&label=$label"
    [ -f "$root_path/crmeb_cert_key.json" ] && rm -f "$root_path/crmeb_cert_key.json"
    request $api 'POST' "$param" 'crmeb_cert_key.json' $host
    exit 0
}

##
# 发起请求
##
function request(){
    url=$1
    methon=$2
    param=$3
    fiel_name=$4
    host=$5
    curl -s -X $methon -d $param $url > $fiel_name
    python_script=$(cat <<EOF
import json
# 读取 JSON 文件
with open('$fiel_name', 'r') as f:
    data = json.load(f)
    # 获取 data 字段下的内容
    data_content = data['data']
if data_content['status'] == -1 :
    msg = data_content['msg']
else:
    auto_content = data_content['auto_content']
    auth_code = data_content['auth_code']
    msg = auto_content + ',' + auth_code
# 输出处理结果
print(msg)
EOF
)
    _command=python
    if python3 --version &> /dev/null; then
        _command=python3
    fi
    msg=$($_command -c "$python_script")
    if [ $msg == '您尚未提交授权申请!' ]; then
        is_error "$msg:$host"
    else
        echo $msg > cert_crmeb.key
        is_success "已重新获取证书"
    fi
}




echo "
+----------------------------------------------------------------------
| 此脚本仅适用于:宝塔面板所安装的PHP 7.1，7.2，7.3，7.4 8.0版本
+----------------------------------------------------------------------
| 脚本执行目录：请将脚本放在需要运行的项目根目录，例如：/www/wwwroot/crmeb.com
+----------------------------------------------------------------------
| 远程下载执行： wget -O auto.sh https://mer.crmeb.net/auto.sh && /bin/bash auto.sh
+----------------------------------------------------------------------
| 请选择操作内容：
| 1 检查替换加密文件
| 2 检查安装swoole_loader
| 3 授权失败验证，重新获取授权证书
+----------------------------------------------------------------------
"
read -p "请输入数字：" num
if [ $num == 1 ]; then
  encypt
elif [ $num == 2 ]; then
  swoole_loader
elif [ $num == 3 ]; then
  auth
else
  echo "输入错误"
  exit 0;
fi
