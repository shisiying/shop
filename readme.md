# 服务器环境安装

## 环境配置
```bash
    wget -qO- https://raw.githubusercontent.com/summerblue/laravel-ubuntu-init/master/download.sh - | bash
    
    source ~/.bash_aliases
    
```
记得记下mysql密码


## 初始化站点配置、目录

```bash

添加站点，按照指示进行操作
$ cd ~/laravel-ubuntu-init/
$ ./16.04/nginx_add_site.sh


删除默认站点

$ rm -f /etc/nginx/sites-enabled/default
$ systemctl restart nginx.service

```

## 安装 Elasticsearch

```bash
$ cd ~/laravel-ubuntu-init
$ ./16.04/install_elasticsearch.sh

查看是否成功

$ ps aux|grep elasticsearch
$ curl 127.0.0.1:9200

```

## 创建非root Mysql用户
```bash
$ cd ~/laravel-ubuntu-init/
$ ./16.04/mysql_add_user.sh
```
保存好登陆密码


# deployer单机部署
注意是在本地，关于deploer可参照这篇文章学习

还需要创建.env文件，在deploy目录下，也就是本地，因为脚本里面需要上传



```bash

$ sudowww 'cp .env.example .env'
$ sudowww 'php artisan key:generate'
$ vim .env

```


```bash
<?php
namespace Deployer;

require 'recipe/laravel.php';


// Project repository
set('repository', 'https://github.com/shisiying/shop');


// Shared files/dirs between deploys 
add('shared_files', []);
add('shared_dirs', []);

//把前一版本的目录进来，提升效率
add('copy_dirs', ['node_modules', 'vendor']);

// Writable dirs by web server 
set('writable_dirs', []);


// Hosts

host('服务器ip')
	->user('root') // 使用 root 账号登录
	->identityFile('秘钥文件路径') // 指定登录密钥文件路径
	->become('www-data') // 以 www-data 身份执行命令
	->set('deploy_path', '/var/www/laravel-shop-deployer'); // 指定部署目录
    
// Tasks

// 定义一个上传 .env 文件的任务
desc('Upload .env file');
task('env:upload', function() {
    // 将本地的 .env 文件上传到代码目录的 .env
    upload('.env', '{{release_path}}/.env');

});


// 定义一个前端编译的任务
desc('Yarn');
task('deploy:yarn', function () {
    // release_path 是 Deployer 的一个内部变量，代表当前代码目录路径
    // run() 的默认超时时间是 5 分钟，而 yarn 相关的操作又比较费时，因此我们在第二个参数传入 timeout = 600，指定这个命令的超时时间是 10 分钟
    run('cd {{release_path}} && SASS_BINARY_SITE=http://npm.taobao.org/mirrors/node-sass yarn && yarn production', ['timeout' => 600]);
});

// 定义一个 执行 es:migrate 命令的任务
desc('Execute elasticsearch migrate');
task('es:migrate', function() {
    // {{bin/php}} 是 Deployer 内置的变量，是 PHP 程序的绝对路径。
    run('{{bin/php}} {{release_path}}/artisan es:migrate');
});


desc('Restart Horizon');
task('horizon:restart', function() {
    run('{{bin/php}} {{release_path}}/artisan horizon:terminate');
});

// 在 deploy:symlink 任务之后执行 horizon:restart 任务
after('deploy:symlink', 'horizon:restart');


// 定义一个后置钩子，在 artisan:migrate 之后执行 es:migrate 任务
after('artisan:migrate', 'es:migrate');

// 定义一个后置钩子，在 deploy:shared 之后执行 env:update 任务
after('deploy:shared', 'env:upload');

// 定义一个后置钩子，在 deploy:vendors 之后执行 deploy:yarn 任务
after('deploy:vendors', 'deploy:yarn');


// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

// Migrate database before symlink new release.

before('deploy:symlink', 'artisan:migrate');

// 在 deploy:vendors 之前调用 deploy:copy_dirs
before('deploy:vendors', 'deploy:copy_dirs');

after('artisan:config:cache', 'artisan:route:cache');



```

执行完毕，需要的导入后台的数据，在项目目录下执行
```bash

    管理后台的菜单、权限、管理用户的数据导入到系统中，执行以下命令：
   
    $ mysql -uroot -p laravel-shop < database/admin.sql
```


配置可参考如下
``` bash

APP_NAME="Laravel Shop"
APP_ENV=production
APP_KEY={保持原本的值}
APP_DEBUG=false
APP_LOG_LEVEL=debug
APP_URL=http://{你的服务器 IP}

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel-shop
DB_USERNAME=laravel-shop
DB_PASSWORD={刚刚创建的 Mysql 用户密码}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120
QUEUE_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_DRIVER=log
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

ES_HOSTS=127.0.0.1:9200

```
# deployer多机部署

需要将之前配置的127.0.0.1修改成内网ip，方便内网的多台机器访问，前提是使用阿里云/腾讯云的服务

## 修改 Mysql 监听 IP
Mysql 的监听配置位于 /etc/mysql/mysql.conf.d/mysqld.cnf 的 bind-address 项:

/etc/mysql/mysql.conf.d/mysqld.cnf
```bash
.
.
.
bind-address            = {云服务器的内网 IP}
.
.
.

```

## 检查是否生效
```bash
    $ systemctl restart mysql.service
    $ netstat -anp|grep 3306
```

## 修改 Redis 监听 IP
同理修改为内网 IP：

/etc/redis/redis.conf

```bash

.
.
.
bind {云服务器的内网 IP}
.
.
.

```

重启redis

```bash
$ systemctl restart redis.service
$ netstat -anp|grep 6379

```

## 修改 Elasticsearch 监听 IP

需要把 network.host 前面的 # 去掉：

/etc/elasticsearch/elasticsearch.yml
```bash
.
.
.
network.host: {云服务器的内网 IP}
.
.
.

```

重启 Elasticsearch：
```bash
$ systemctl restart elasticsearch.service
```

确认修改成功
```bash
netstat -anp|grep 9200
```

## 修改.env文件

.env

```bash
.
.
.
DB_HOST={云服务器的内网 IP}
.
.
.
CACHE_DRIVER=redis
SESSION_DRIVER=redis
.
.
.
REDIS_HOST={云服务器的内网 IP}
.
.
.
ES_HOSTS={云服务器的内网 IP}:9200
```

## 多机deployer部署

```bash
<?php
namespace Deployer;

require 'recipe/laravel.php';


// Project repository
set('repository', 'https://github.com/shisiying/shop');


// Shared files/dirs between deploys 
add('shared_files', []);
add('shared_dirs', []);

//把前一版本的目录进来，提升效率
add('copy_dirs', ['node_modules', 'vendor']);

// Writable dirs by web server 
set('writable_dirs', []);


// Hosts

host('ip1')
	->user('root') // 使用 root 账号登录
	->identityFile('~/.ssh/laravel-shop-aliyun.pem') // 指定登录密钥文件路径
	->become('www-data') // 以 www-data 身份执行命令
	->set('deploy_path', '/var/www/laravel-shop-deployer'); // 指定部署目录


host('ip2')
    ->user('root')
    ->identityFile('~/.ssh/laravel-shop-aliyun.pem')
    ->become('www-data')
    ->set('deploy_path', '/var/www/laravel-shop');

// Tasks

// 定义一个上传 .env 文件的任务
desc('Upload .env file');
task('env:upload', function() {
    // 将本地的 .env 文件上传到代码目录的 .env
    upload('.env', '{{release_path}}/.env');

});


// 定义一个前端编译的任务
desc('Yarn');
task('deploy:yarn', function () {
    // release_path 是 Deployer 的一个内部变量，代表当前代码目录路径
    // run() 的默认超时时间是 5 分钟，而 yarn 相关的操作又比较费时，因此我们在第二个参数传入 timeout = 600，指定这个命令的超时时间是 10 分钟
    run('cd {{release_path}} && SASS_BINARY_SITE=http://npm.taobao.org/mirrors/node-sass yarn && yarn production', ['timeout' => 600]);
});

// 定义一个 执行 es:migrate 命令的任务
desc('Execute elasticsearch migrate');
task('es:migrate', function() {
    // {{bin/php}} 是 Deployer 内置的变量，是 PHP 程序的绝对路径。
    run('{{bin/php}} {{release_path}}/artisan es:migrate');
})->once();


desc('Restart Horizon');
task('horizon:restart', function() {
    run('{{bin/php}} {{release_path}}/artisan horizon:terminate');
})->once();

// 在 deploy:symlink 任务之后执行 horizon:restart 任务
after('deploy:symlink', 'horizon:restart');


// 定义一个后置钩子，在 artisan:migrate 之后执行 es:migrate 任务
after('artisan:migrate', 'es:migrate');

// 定义一个后置钩子，在 deploy:shared 之后执行 env:update 任务
after('deploy:shared', 'env:upload');

// 定义一个后置钩子，在 deploy:vendors 之后执行 deploy:yarn 任务
after('deploy:vendors', 'deploy:yarn');


// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

// Migrate database before symlink new release.

before('deploy:symlink', 'artisan:migrate');

// 在 deploy:vendors 之前调用 deploy:copy_dirs
before('deploy:vendors', 'deploy:copy_dirs');

after('artisan:config:cache', 'artisan:route:cache');


```
