# docker-compose 快速运行调试项目

### 没有nginx镜像配置，只能启动后端接口服务，查看前端需要npm运行前端

## 1、安装docker
docker 官网下载
https://www.docker.com/products/docker-desktop

或命令安装
```
curl -sSL https://get.daocloud.io/docker | sh
```
## 2、安装docker-compose
https://www.runoob.com/docker/docker-compose.html

## 3、配置mysql和redis连接信息

在项目根目录中创建.env文件，把下面的配置放入.env文件中
```phpregexp
APP_DEBUG = false

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = 192.168.10.1
DATABASE = crmeb
USERNAME = root
PASSWORD = 123456
HOSTPORT = 3306
CHARSET = utf8
DEBUG = true

[LANG]
default_lang = zh-cn

[REDIS]
REDIS_HOSTNAME = 192.168.10.10
PORT = 6379
REDIS_PASSWORD = 123456
SELECT = 0

```

## 4、运行docker-compose

需要在本地创建如下文件夹。如果已经存在不需要重新创建

```phpregexp
/install/docker-compose/mysql/data
/install/docker-compose/mysql/log
/install/docker-compose/redis/data
```

进入install/docker-compose目录。执行如下命令

```
docker-compose up -d
```

## 5、导入安装SQL和创建安装文件

安装sql存放在/install/crmeb_merchant.sql文件，需要去手动执行。连接上MYSQL导入sql文件

创建/install/install.lock 文件内为空就可以

连接容器Mysql，按照以下配置来进行链接
```phpregexp
HOSTNAME = 127.0.0.1
DATABASE = crmeb
USERNAME = root
PASSWORD = 123456
HOSTPORT = 3366
```

## 6、调试访问服务

一定要携带端口进行访问
```phpregexp
http://127.0.0.1:8324
```
