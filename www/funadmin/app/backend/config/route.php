<?php
/**
 * Created by FunAdmin.
 * Copyright FunAdmin.
 * Author: Yuege
 * Date: 2020/3/9
 * Time: 14:39
 */


return [
    'middleware' => [
        //节点
        app\backend\middleware\ViewNode::class,
        //角色权限
        app\backend\middleware\CheckRole::class,
        //日志
        app\backend\middleware\SystemLog::class,
    ],

//    'request_cache_key'	=>	'__URL__',
//    'request_cache_expire'	=>	3600,


];