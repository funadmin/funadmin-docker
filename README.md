FunAdmin docker版本 默认使用 PHP8+mysql8+nginx

###  安装docker  和docker-compose 
### 修改 .env 数据库密码 默认为root  123456 
###  进入 funadmin-docker 目录 并使用docker-compose up 安装  
###  进入services/nginx/conf.d/funadmin.conf 修改域名配置 即可安装 
### 域名+8080 即可访问数据库 
###  访问域名进行安装 主机名字请填写mysql 切记不要填写127.0.0.1

### Host中使用php命令行（php-cli）

1. 参考[bash.alias.sample](bash.alias.sample)示例文件，将对应 php cli 函数拷贝到主机的 `~/.bashrc`文件。
2. 让文件起效：
    ```bash
    source ~/.bashrc
    ```
3. 然后就可以在主机中执行php命令了：
    ```bash
    ~ php -v
    PHP 7.2.13 (cli) (built: Dec 21 2018 02:22:47) ( NTS )
    Copyright (c) 1997-2018 The PHP Group
    Zend Engine v3.2.0, Copyright (c) 1998-2018 Zend Technologies
        with Zend OPcache v7.2.13, Copyright (c) 1999-2018, by Zend Technologies
        with Xdebug v2.6.1, Copyright (c) 2002-2018, by Derick Rethans
    ```
### 使用composer
**方法1：主机中使用composer命令**
1. 确定composer缓存的路径。比如，我的funadmin-docker下载在`~/funadmin-docker`目录，那composer的缓存路径就是`~/funadmin-docker/data/composer`。
2. 参考[bash.alias.sample](bash.alias.sample)示例文件，将对应 php composer 函数拷贝到主机的 `~/.bashrc`文件。
    > 这里需要注意的是，示例文件中的`~/funadmin-docker/data/composer`目录需是第一步确定的目录。
3. 让文件起效：
    ```bash
    source ~/.bashrc
    ```
4. 在主机的任何目录下就能用composer了：
    ```bash
    cd ~/funadmin-docker/www/
    composer create-project funadmin/funadmin project --no-dev
    ```
5. （可选）第一次使用 composer 会在 `~/funadmin-docker/data/composer` 目录下生成一个**config.json**文件，可以在这个文件中指定国内仓库，例如：
    ```json
    {
        "config": {},
        "repositories": {
            "packagist": {
                "type": "composer",
                "url": "https://mirrors.aliyun.com/composer/"
            }
        }
    }

    ```
**方法二：容器内使用composer命令**

还有另外一种方式，就是进入容器，再执行`composer`命令，以PHP7容器为例：
```bash
docker exec -it php /bin/sh
cd /www/localhost
composer update
```
    
## 4.管理命令
### 4.1 服务器启动和构建命令
如需管理服务，请在命令后面加上服务器名称，例如：
```bash
$ docker-compose up                         # 创建并且启动所有容器
$ docker-compose up -d                      # 创建并且后台运行方式启动所有容器
$ docker-compose up nginx php mysql         # 创建并且启动nginx、php、mysql的多个容器
$ docker-compose up -d nginx php  mysql     # 创建并且已后台运行的方式启动nginx、php、mysql容器


$ docker-compose start php                  # 启动服务
$ docker-compose stop php                   # 停止服务
$ docker-compose restart php                # 重启服务
$ docker-compose build php                  # 构建或者重新构建服务

$ docker-compose rm php                     # 删除并且停止php容器
$ docker-compose down                       # 停止并删除容器，网络，图像和挂载卷
```

###  添加快捷命令
在开发的时候，我们可能经常使用`docker exec -it`进入到容器中，把常用的做成命令别名是个省事的方法。

首先，在主机中查看可用的容器：
```bash
$ docker ps           # 查看所有运行中的容器
$ docker ps -a        # 所有容器
```
输出的`NAMES`那一列就是容器的名称，如果使用默认配置，那么名称就是`nginx`、`php`、`php56`、`mysql`等。

然后，打开`~/.bashrc`或者`~/.zshrc`文件，加上：
```bash
alias dnginx='docker exec -it nginx /bin/sh'
alias dphp='docker exec -it php /bin/sh'
alias dphp56='docker exec -it php56 /bin/sh'
alias dphp54='docker exec -it php54 /bin/sh'
alias dmysql='docker exec -it mysql /bin/bash'
alias dredis='docker exec -it redis /bin/sh'
```
下次进入容器就非常快捷了，如进入php容器：
```bash
$ dphp
```

###  查看docker网络
```sh
ifconfig docker0
```
用于填写`extra_hosts`容器访问宿主机的`hosts`地址

## 5.使用Log

Log文件生成的位置依赖于conf下各log配置的值。

### 5.1 Nginx日志
Nginx日志是我们用得最多的日志，所以我们单独放在根目录`log`下。

`log`会目录映射Nginx容器的`/var/log/nginx`目录，所以在Nginx配置文件中，需要输出log的位置，我们需要配置到`/var/log/nginx`目录，如：
```
error_log  /var/log/nginx/nginx.localhost.error.log  warn;
```


### PHP-FPM日志
大部分情况下，PHP-FPM的日志都会输出到Nginx的日志中，所以不需要额外配置。

另外，建议直接在PHP中打开错误日志：
```php
error_reporting(E_ALL);
ini_set('error_reporting', 'on');
ini_set('display_errors', 'on');
```

如果确实需要，可按一下步骤开启（在容器中）。

1. 进入容器，创建日志文件并修改权限：
    ```bash
    $ docker exec -it php /bin/sh
    $ mkdir /var/log/php
    $ cd /var/log/php
    $ touch php-fpm.error.log
    $ chmod a+w php-fpm.error.log
    ```
2. 主机上打开并修改PHP-FPM的配置文件`conf/php-fpm.conf`，找到如下一行，删除注释，并改值为：
    ```
    php_admin_value[error_log] = /var/log/php/php-fpm.error.log
    ```
3. 重启PHP-FPM容器。

###  MySQL日志
因为MySQL容器中的MySQL使用的是`mysql`用户启动，它无法自行在`/var/log`下的增加日志文件。所以，我们把MySQL的日志放在与data一样的目录，即项目的`mysql`目录下，对应容器中的`/var/lib/mysql/`目录。
```bash
slow-query-log-file     = /var/lib/mysql/mysql.slow.log
log-error               = /var/lib/mysql/mysql.error.log
```
以上是mysql.conf中的日志文件的配置。



## .数据库管理
本项目默认在`docker-compose.yml`中不开启了用于MySQL在线管理的*phpMyAdmin*，以及用于redis在线管理的*phpRedisAdmin*，可以根据需要修改或删除。

###  phpMyAdmin
phpMyAdmin容器映射到主机的端口地址是：`8080`，所以主机上访问phpMyAdmin的地址是：
```
http://localhost:8080
```

MySQL连接信息：
- host：(本项目的MySQL容器网络)
- port：`3306`
- username：（手动在phpmyadmin界面输入）
- password：（手动在phpmyadmin界面输入）

###  phpRedisAdmin
phpRedisAdmin容器映射到主机的端口地址是：`8081`，所以主机上访问phpMyAdmin的地址是：
```
http://localhost:8081
```

Redis连接信息如下：
- host: (本项目的Redis容器网络)
- port: `6379`


## .在正式环境中安全使用
要在正式环境中使用，请：
1. 在php.ini中关闭XDebug调试
2. 增强MySQL数据库访问的安全策略
3. 增强redis访问的安全策略


