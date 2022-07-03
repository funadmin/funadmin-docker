<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/21
 */

namespace app\common\controller;
use app\backend\service\AuthService;
use app\BaseController;
use app\common\traits\Jump;
use fun\addons\Controller;
use think\App;
use think\exception\ValidateException;
use think\facade\Cookie;
use think\facade\Lang;
use think\facade\View;

class Frontend extends BaseController
{
    use Jump;
    /**
     * 主键 id
     * @var string
     */
    protected $primaryKey = 'id';
    /**
     * @var
     * 入口
     */
    protected $entrance;

    /**
     * @var
     * 模型
     */
    protected $modelClass;
    /**
     * @var
     * 模块
     */
    protected $module;
    /**
     * 控制器
     * @var
     */
    protected $controller;
    /**
     * 方法
     * @var
     */
    protected $action;
    /**
     * @var
     * 页面大小
     */
    protected $pageSize;
    /**
     * @var
     * 页数
     */
    protected $page;

    /**
     * 模板布局, false取消
     * @var string|bool
     */
    protected $layout = 'layout/main';

    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';
    /**
     * 下拉选项条件
     * @var string
     */
    protected $selectMap =[];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    protected $allowModifyFields = [
        'status',
        'sort',
        'title',
    ];
    /**
     * 关联join搜索
     * @var array
     */
    protected $joinSearch = [];

    public function __construct(App $app)
    {
        parent::__construct($app);
        //过滤参数
        $this->pageSize = input('limit', 15);
        //加载语言包
        $this->loadlang(strtolower($this->controller));
        $this->initialize();
    }

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->module = app('http')->getName();
        $this->controller = request()->controller();
        $this->action = request()->action();
        $this->entrance = '/';
        [$modulename, $controllername, $actionname] = [$this->module, $this->controller, $this->action];
        $controllername = str_replace('\\','.',$controllername);
        $controllers = explode('.', $controllername);
        $jsname = '';
        foreach ($controllers as $vo) {
            empty($jsname) ? $jsname = parse_name($vo) : $jsname .= '/' . parse_name($vo);
        }
        $autojs = file_exists(app()->getRootPath()."public".DS."static".DS."{$modulename}".DS."js".DS."{$jsname}.js") ? true : false;
        $jspath ="{$modulename}/js/{$jsname}.js";
        $config = [
            'entrance'    => $this->entrance,//入口
            'modulename'    => $modulename,
            'addonname'    => '',
            'moduleurl'    => rtrim(url("/{$modulename}", [], false), '/'),
            'controllername'       => parse_name($controllername),
            'actionname'           => parse_name($actionname),
            'requesturl'          => parse_name("/{$modulename}/{$controllername}/{$actionname}"),
            'jspath' => "{$jspath}",
            'autojs'           => $autojs,
            'superAdmin'           => session('member.id')==1,
            'lang'           =>  strip_tags( Lang::getLangset()),
            'site'           =>  syscfg('site'),
            'upload'           =>  syscfg('upload'),
            'editor'           =>  syscfg('editor'),

        ];
        // 如果有使用模板布局 可以更换布局
        if($this->layout=='layout/main'){
            $this->layout && app()->view->engine()->layout(trim($this->layout,'/'));
        }

        View::assign('config',$config);
    }


    //自动加载语言
    protected function loadlang($name)
    {
        $lang = cookie(config('lang.cookie_var'));
        Lang::load([
            app()->getAppPath(). 'lang' . DS . $lang . DS . str_replace('.', '/', $name) . '.php'
        ]);
    }

    /**
     * @param array $data
     * @param array|string $validate
     * @param array $message
     * @param bool $batch
     * @return array|bool|string|true
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        try {
            parent::validate($data, $validate, $message, $batch);
            $this->checkToken();
        } catch (ValidateException $e) {
            $this->error($e->getMessage());
        }
        return true;
    }

    /**
     * 检测token 并刷新
     */
    protected function checkToken()
    {
        $check = $this->request->checkToken('__token__', $this->request->param());
        if (false === $check) {
            $this->error(lang('Token verify error'), '', ['__token__' => $this->request->buildToken()]);
        }
    }
    /**
     * 刷新Token
     */
    protected function token()
    {
        return $this->request->buildToken();
    }


    //是否登录
    protected function isLogin()
    {
        if (session('member.id')) {
            return session('member');
        } else {
            return false;
        }
    }


}