<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\service;

use app\backend\model\Admin as AdminModel;
use app\backend\model\AuthGroup as AuthGroupModel;
use app\backend\model\AuthRule;
use app\common\traits\Jump;
use fun\helper\SignHelper;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Request;
use think\facade\Session;
use think\helper\Str;

class AuthService
{
    use Jump;

    /**
     * @var object 对象实例
     */

    /**
     * 当前请求实例
     * @var Request
     */
    protected $request;

    protected $app;

    protected $controller;

    protected $action;

    protected $requesturl;
    /**
     * @var array
     * config
     */
    protected $config = [];
    /**
     * @var $hrefId ;
     */
    protected $hrefId;

    public function __construct()
    {
        if ($auth = Config::get('auth')) {
            $this->config = array_merge($this->config, $auth);
        }
        // 初始化request
        $this->request = Request::instance();
        $this->app = app('http')->getName();
        $this->controller = Str::camel($this->request->controller());
        $this->action = $this->request->action();
        $this->action = $this->action ? $this->action : 'index';
        $url = $this->controller . '/' . $this->action;
        $pathurl = $this->request->baseUrl();
        $this->requesturl = $url;
        if (substr($pathurl, 0,7) === 'addons/') {
            $this->requesturl = $pathurl;
        }else{
            $this->requesturl = str_replace(Config::get('backend.backendEntrance'),'',$this->requesturl);
        }
        if(Str::endsWith($this->requesturl,'.'. config('view.view_suffix'))){
            $this->requesturl = Str::substr($this->requesturl,0,strlen($this->requesturl)-strlen(config('view.view_suffix'))-1);
        }
    }

    /**
     * 权限节点
     */
    public function nodeList()
    {
        $allAuthNode = [];
        if (session('admin')) {
            $cacheKey = 'allAuthNode_' . session('admin.id');
            $allAuthNode = Cache::get($cacheKey);
            if (empty($allAuthNode)) {
                $allAuthIds = $this->getRules(session('admin.group_id'));
                $allAuthNode = AuthRule::where('status', 1)->whereIn('id', $allAuthIds)->cache($cacheKey)->column('href', 'href');
                foreach ($allAuthNode as $k => $v) {
                    $allAuthNode[$k] = (parse_name($v, 1));
                }
                $allAuthNode = array_flip($allAuthNode);
            }
        }
        return $allAuthNode;

    }

    /*
    * 菜单排列
    */
    public function treemenu($cate, $lefthtml = '├─', $pid = 0, $lvl = 0, $leftpin = 0)
    {
        $arr = array();
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['lvl'] = $lvl + 1;
                $v['leftpin'] = $leftpin + 0;
                $v['lefthtml'] = str_repeat($lefthtml, $lvl);
                $v['ltitle'] = $v['lefthtml'] . $v['title'];
                $arr[] = $v;
                $arr = array_merge($arr, self::treemenu($cate, $lefthtml, $v['id'], $lvl + 1, $leftpin + 20));
            }
        }

        return $arr;
    }

    /*
     * 权限
     */
    public function auth($cate, $rules, $pid = 0)
    {
        $arr = array();
        $rulesArr = explode(',', $rules);
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                if (in_array($v['id'], $rulesArr)) {
                    $v['checked'] = true;
                }
                $v['open'] = true;
                $arr[] = $v;
                $arr = array_merge($arr, self::auth($cate, $v['id'], $rules));
            }
        }
        return $arr;
    }

    /**
     * 权限设置选中状态
     * @param $cate  栏目
     * @param int $pid 父ID
     * @param $rules 规则
     * @return array
     */
    public function authChecked(array $cate, int $pid, string $rules, int $group_id)
    {
        $list = [];
        $rulesArr = explode(',', $rules);
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                if(!in_array($v['module'],['addon','backend'])) $v['href'] = $v['module'].'/'.$v['href'];
                $v['title'] = lang($v['title']).' @ '.$v['href'];
                if (self::authChecked($cate, $v['id'], $rules, $group_id)) {
                    $v['children'] = self::authChecked($cate, $v['id'], $rules, $group_id);
                } else {
                    if (in_array($v['id'], $rulesArr) || $group_id == 1) {
                        $v['checked'] = true;
                    }
                }
                $list[] = $v;
            }
        }
        return $list;
    }
    /**
     * 权限多维转化为二维
     * @param $cate  栏目
     * @param int $pid 父ID
     * @param $rules 规则
     * @return array
     */
    public function authNormal($cate)
    {
        $list = [];
        foreach ($cate as $v) {
            $list[]['id'] = $v['id'];
//        $list[]['title'] = $v['title'];
//        $list[]['pid'] = $v['pid'];
            if (!empty($v['children'])) {
                $listChild = self::authNormal($v['children']);
                $list = array_merge($list, $listChild);
            }
        }
        return $list;
    }
    /**
     * 验证权限
     */
    public function checkNode()
    {
        $cfg = config('backend');
        if ($this->requesturl === '/') {
            $this->error(lang('Login again'), __u('login/index'));
        }
        $adminId = session('admin.id');
        if (!in_array($this->controller, $cfg['noLoginController']) && !in_array($this->requesturl, $cfg['noLoginNode'])) {
            if (!$this->isLogin()) {
                $this->error(lang('Please Login First'), __u('login/index'));
            }
            if ($adminId && $adminId != $cfg['superAdminId']) {
                if (!in_array($this->controller, $cfg['noRightController']) &&  !in_array($this->requesturl, $cfg['noRightNode'])) {
                    if ($this->request->isPost() && $cfg['isDemo'] == 1) $this->error(lang('Demo is not allow to change data'));
                    $map[] = ['href','=',$this->requesturl];
                    if($this->app!=='backend') {$map[] = ['module','=',$this->app];}
                    $this->hrefId = AuthRule::where($map)
                        ->where('status', 1)
                        ->value('id');
                    $hrefTemp = trim($this->requesturl,'/');
                    $menuid = 0;
                    if(Str::endsWith($hrefTemp,'/index')){
                        $where[] = ['href','=',substr($hrefTemp,0,strlen($hrefTemp)-6)];
                        if($this->app!=='backend') {$where[] = ['module','=',$this->app];}
                        $menuid =  AuthRule::where($where)
                            ->where('status', 1)
                            ->value('id');
                    }
                    if($menuid) $this->hrefId = $menuid;
                    //当前管理员权限
                    $rules = $this->getRules(session('admin.group_id'));
                    //用户权限规则id
                    $this->adminRules = array_unique( array_filter(explode(',', $rules)));
                    if ($this->hrefId) {
                        if (!in_array($this->hrefId, $this->adminRules)) $this->error(lang('Permission Denied'));

                    }else{
                        if (!in_array($this->requesturl, $cfg['noRightNode'])) $this->error(lang('Permission Denied'));
                    }
                }
            } else {
                if (!in_array($this->controller, $cfg['noRightController']) && !in_array($this->requesturl, $cfg['noRightNode'])) {
                    if ($this->request->isPost() && $cfg['isDemo'] == 1) {
                        $this->error(lang('Demo is not allow to change data'));
                    }
                }
            }
        } elseif (
            //不需要鉴权
            in_array($this->controller, $cfg['noLoginController'])
            //不需要登录
            && in_array($this->requesturl, $cfg['noLoginNode'])
        ) {
            if ($this->isLogin()) {
                $this->redirect(__u('index/index'));
            }
        }
    }

    /**
     * 前台权限节点
     */
    public function authNode($url)
    {
        $cfg = config('backend');
        $url = (string)$url;
        $this->requesturl  = str_replace($cfg['backendEntrance'],'',explode('.'.config('view.view_suffix'),$url)[0]);
        $this->requesturl = trim($this->requesturl,'/');
        $urlArr = explode('/',$this->requesturl);
        $this->controller =  strpos($this->requesturl,'addons/')===false?Str::camel($urlArr[0]):$urlArr[1].'/'.$urlArr[2].'/'.$urlArr[3];
        if ($this->requesturl === '/') {return false;}
        $adminId = session('admin.id');
        // 判断权限验证开关
        if (isset($cfg['auth_on']) && $cfg['auth_on'] == false) {
            return true;
        }
        if($this->app!=='backend' && Str::startsWith($this->requesturl,$this->app.'/')){
            $this->requesturl = Str::substr($this->requesturl,strlen($this->app)+1,strlen($this->requesturl));
        }
        if (!in_array($this->controller, $cfg['noLoginController']) && !in_array($this->requesturl, $cfg['noLoginNode'])) {
            //不在权限内
            if (!$this->isLogin()) return false;
            if ($adminId && $adminId != $cfg['superAdminId']) {
                if (!in_array($this->controller, $cfg['noRightController']) && !in_array($this->requesturl, $cfg['noRightNode'])) {
                    $hrefTemp = trim($this->requesturl,'/');
                    $map[] = ['href','=',$this->requesturl];
                    if($this->app!=='backend') {$map[] = ['module','=',$this->app];}
                    $this->hrefId = AuthRule::where($map)
                    ->where('status', 1)
                    ->value('id');
                    $menuid = 0;
                    if(Str::endsWith($hrefTemp,'/index')){
                        $where[] = ['href','=',substr($hrefTemp,0,strlen($hrefTemp)-6)];
                        if($this->app!=='backend') {$where[] = ['module','=',$this->app];}
                        $menuid =  AuthRule::where($where)
                            ->where('status', 1)->value('id');
                    }
                    if($menuid)  $this->hrefId = $menuid;
                    //当前管理员权限
                    $rules = $this->getRules(session('admin.group_id'));
                    //用户权限规则id
                    $this->adminRules = array_unique( array_filter(explode(',', $rules)));
                    if ($this->hrefId && in_array($this->hrefId, $this->adminRules))  return true;
                    if (in_array($this->requesturl, $cfg['noRightNode'])) return true;
                }elseif(in_array($this->controller, $cfg['noRightController']) && in_array($this->requesturl, $cfg['noRightNode'])){
                    return true;
                }
            } else {//超管
                return true;
            }
        } elseif (in_array($this->controller, $cfg['noLoginController'])
        && in_array($this->requesturl, $cfg['noLoginNode'])){//不需要登录也就不需要鉴权了权限最大化
            return true;
        } elseif (in_array($this->controller, $cfg['noRightController']) && in_array($this->requesturl, $cfg['noRightNode'])) {
            return true;
        }
        return false;
    }
    /**
     * @param $cate
     * @return string
     * 帅刷新菜单；
     */
    public function menuhtml($cate, $force = true)
    {
        if ($force) {
            Cache::delete('adminmenushtml' . session('admin.id'));
        }
        $list = $this->authMenuNode($cate);
        $html = '';
        $theme = syscfg('site', 'site_theme');
        if ($theme == 1 || $theme == 2){
            foreach ($list as $key => $val) {
                $html .= '<li class="layui-nav-item">';
                $badge = '';
                if (strtolower($val['title']) === 'addon') {
                    $badge = '<span class="layui-badge" style="text-align: right;float: right;position: absolute;right: 10%;">new</span>';
                }
                if ($val['child'] and count($val['child']) > 0) {
                    $html .= '<a href="javascript:;" lay-id="' . $val['id'] . '" data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                    $html = $this->childmenuhtml($html, $val['child']);
                } else {
                    $target = $val['target'] ? $val['target'] : '_self';
                    $html .= '<a href="javascript:;" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '" data-url="' . $val['href'] . '" target="' . $target . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                }
                $html .= '</li>';
            }
        }elseif($theme==3){
            $html = [];
            $hide = '';
            $html['nav'] = '';
            $html['menu'] = '';
            $html['navm'] = '<li class="layui-nav-item"  menu-id="'.$list[0]['id'].'">
                    <a href="javascript:;"><i class="fa fa-list-ul"></i> 请选择<span class="layui-nav-more"></span></a>
                    <dl class="layui-nav-child">';
            foreach ($list as $key => $val) {
                $laythis =$key==0? 'layui-this':'';
                $html['nav'] .= '<li class="layui-nav-item '.$laythis.'"  menu-id="'.$val['id'].'">';
                $html['navm'] .= '<dd><a href="javascript:;" menu-id="' . $val['id'] . '" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '"  data-tips="' . lang($val['title']) . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite></a></dd>';
                $badge = '';
                if (strtolower($val['title']) === 'addon') {
                    $badge = '<span class="layui-badge" style="text-align: right;float: right;position: absolute;right: -20px;">new</span>';
                }
                $hide = $key>0?'layui-hide':'';
                $html['menu'] .= '<ul class="layui-nav layui-nav-tree '.$hide.'" menu-id="'.$val['id'].'" lay-filter="menulist"  lay-shrink="all" id="layui-side-left-menu-ul">';
                if ($val['child'] and count($val['child']) > 0) {
                    $html['nav'] .= '<a href="javascript:;" menu-id="' . $val['id'] . '" lay-id="' . $val['id'] . '" data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                    foreach($val['child'] as $k=>$v){
                        if ($v['child'] and count($v['child']) > 0) {
                            $html['menu'] .= '<li class="layui-nav-item"  menu-id="'.$v['id'].'"><a href="javascript:;"  lay-id="' . $v['id'] . '" data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite>' . $badge . '</a>';
                            $html['menu'] .= $this->childmenuhtml('', $v['child']);
                            $html['menu'] .= '</li>';
                        }else{
                            $target = $val['target'] ? $val['target'] : '_self';
                            $html['menu'] .= '<li class="layui-nav-item"  lay-id="'.$v['id'].'"><a href="javascript:;" lay-id="' . $v['id'] . '"  data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '" data-url="' . $v['href'] . '" target="' . $target . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite>' . $badge . '</a></li>';
                        }
                    }
                    $html['menu'].='</ul>';
                } else {
                    $target = $val['target'] ? $val['target'] : '_self';
                    $html['nav'] .= '<a href="javascript:;" lay-event="tab" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '" data-url="' . $val['href'] . '" target="' . $target . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                    $html['menu'] .= '<li class="layui-nav-item"  menu-id="' . $val['id'] . '"  lay-id="'.$val['id'].'"><a href="javascript:;" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '" data-url="' . $val['href'] . '" target="' . $target . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a></li>';
                }
                $html['menu'].='</ul>';
                $html['nav'] .= '</li>';
            }
            $html['navm'].='</dl><li>';
        }
        return $html;
    }

    /**
     * @param $html
     * @param $child
     * @return string
     * 获取子菜单html
     */
    public function childmenuhtml($html, $child,$type=1)
    {
        if($type<3){
            $html .= '<dl class="layui-nav-child">';
            foreach ($child as $k => $v) {
                $html .= '<dd >';
                if ($v['child'] and count($v['child']) > 0) {
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"  data-id="' . $v['id'] . '" title="' . lang($v['title']) . '"  data-tips="' . lang($v['title']) . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite></a>';
                    $html = self::childmenuhtml($html, $v['child'],$type);
                } else {
                    $v['target'] = $v['target'] ? $v['target'] : '_self';
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"   data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '" data-url="' . $v['href'] . '" target="' . $v['target'] . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite></a>';
                }
                $html .= '</dd>';
            };
            $html .= '</dl>';
        }else{
            $html .= '<dl class="layui-nav-child">';
            foreach ($child as $k => $v) {
                $html .= '<dd >';
                if ($v['child'] and count($v['child']) > 0) {
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"  data-id="' . $v['id'] . '" title="' . lang($v['title']) . '"  data-tips="' . lang($v['title']) . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite></a>';
                    $html = self::childmenuhtml($html, $v['child'],$type);
                } else {
                    $v['target'] = $v['target'] ? $v['target'] : '_self';
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"   data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '" data-url="' . $v['href'] . '" target="' . $v['target'] . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite></a>';
                }
                $html .= '</dd>';
            };
            $html .= '</dl>';;
        }
        return $html;
    }

    /**
     * 检测是否登录
     * @return boolean
     */
    public function isLogin()
    {
        $admin = session('admin');
        if (!$admin) {
            return false;
        }
        //判断是否同一时间同一账号只能在一个地方登录// 要是备份还原的话，这里会有点问题
        $me = AdminModel::find($admin['id']);
//        if (!$me || $me['token'] != $admin['token']) {
        if (!$me) {
            $this->logout();
            return false;
        }
//        }
        //过期
        if (!session('admin.expiretime') || session('admin.expiretime') < time()) {
            $this->logout();
            return false;
        }
//判断管理员IP是否变动
        if (config('app.ip_check') && ( !isset($admin['lastloginip']) || $admin['lastloginip'] != request()->ip())) {
            $this->logout();
            return false;
        }
        return true;
    }

    /**
     * 根据用户名密码，验证用户是否能成功登陆
     * @param string $username
     * @param string $password
     * @return mixed
     * @throws \Exception
     */
    public function checkLogin($username, $password, $rememberMe)
    {
        try {
            $where['username|email'] = strip_tags(trim($username));
            $password = strip_tags(trim($password));
            $admin = AdminModel::where($where)->find();
            if (!$admin) {
                throw new \Exception(lang('Please check username or password'));
            }
            if ($admin['status'] == 0) {
                throw new \Exception(lang('Account is disabled'));
            }
            if (!password_verify($password, $admin['password'])) {
                throw new \Exception(lang('Please check username or password'));
            }
            if (!$admin['group_id']) {
                throw new \Exception(lang('You dont have permission'));
            }
            $ip = request()->ip();
            $admin->lastloginip = $ip;
            $admin->ip = $ip;
            $admin->token = SignHelper::authSign($admin);
            $admin->save();
            $admin = $admin->toArray();
            $rules = $this->getRules($admin['group_id']);
            $admin['rules'] = $rules;
            if ($rememberMe) {
                $admin['expiretime'] = 30 * 24 * 3600 + time();
            } else {
                $admin['expiretime'] = config('session.expire') +time();
            }
            unset($admin['password']);
            Session::set('admin', $admin);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        return true;
    }
    /**
     * 注销登录
     */
    public function logout()
    {
        $admin = AdminModel::find(intval(\session('admin.id')));
        if ($admin) {
            $admin->token = '';
            $admin->save();
        }
        Session::clear();
        Cookie::delete("rememberMe");
        return true;
    }



    /**
     * 获取rules
     * @param $groups
     * @return void
     */
    protected function getRules($groups){
        if($groups && in_array(1,explode(",",$groups))){
            $rules = AuthRule::where('status',1)->cache('superAdmin',24*3600)->column('id');
            $rules = implode(',',$rules);
        }else{
            $rules = AuthGroupModel::where('id', 'in', $groups)->where('status',1)->value('rules');
        }
        $norules = AuthRule::where('auth_verify',0)->column('id');
        $norules = $norules?implode(',',$norules):'';
        return $rules.','.$norules;
    }
    //获取左侧主菜单树形结构
    protected function authMenuNode($menu, $pid = 0, $rules = [])
    {
        $authrules = array_unique(explode(',',$this->getRules(session('admin.group_id'))));
        $list = array();
        foreach ($menu as $v) {
            $href = $v['href'];
            if ($v['menu_status'] == 1) {
                $v['href'] =  trim($v['href'], '/' );
                if(!Str::endsWith($v['href'],'/index')){
                    $v['href'] = $v['href']. '/index';
                }
            }
            if ($v['module'] === 'backend') {
                $v['href'] = (__u(trim($v['href'])));
            } elseif($v['module']=='addon') {
                $v['href'] = ('/' . trim($v['href'], '/'));
            }else{
                $v['href'] = ('/' .$v['module'].'/'. trim($v['href'], '/'));
            }
            if(preg_match("/^http(s)?:\\/\\/.+/",$href)) {
                $v['href'] = $href;
            }
            if ($v['pid'] == $pid) {
                if (session('admin.id') != 1) {
                    if (in_array($v['id'], $authrules)) {
                        $child = AuthRule::field('href,id')
                            ->where('status', 1)
                            ->where('pid', $v['id'])->find();
                        //删除下级没有list的菜单权限
                        if (!$child) {
                            $v['child'] = [];
                            $list[] = $v;
                        } else {
                            $v['child'] = self::authMenuNode($menu, $v['id']);
                            $list[] = $v;
                        }
                    }
                } else {
                    $v['child'] = self::authMenuNode($menu, $v['id']);
                    $list[] = $v;
                }
            }
        }
        return $list;
    }
    /**
     * 获取所有子id
     */
    protected function getallIdsBypid($pid)
    {
        $res = AuthRule::where('pid', $pid)->where('status', 1)->select();
        $str = '';
        if (!empty($res)) {
            foreach ($res as $k => $v) {
                $str .= "," . $v['id'];
                $str .= $this->getallIdsBypid($v['id']);
            }
        }
        return $str;
    }


}