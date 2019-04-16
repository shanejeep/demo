<?php

/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 当燃
 * Date: 2016-06-09
 */

namespace app\seller\controller;

use app\admin\logic\UpgradeLogic;
use think\Controller;
use think\Session;
use think\Db;
use app\home\controller\Uploadify;
use OSS\OssClient;
use OSS\Core\OssException;

class Base extends Controller
{
    public $sys_config = array();

    /**
     * 析构函数
     */
    function __construct()
    {
        // Session::start();
        ini_set('session.gc_maxlifetime',21600);
        header("Cache-control: private"); 
        parent::__construct();
        $upgradeLogic = new UpgradeLogic();
        $upgradeMsg = $upgradeLogic->checkVersion(); //升级包消息        
        $this->assign('upgradeMsg', $upgradeMsg);
        //用户中心面包屑导航
        $seller = session('seller');
    /*    tpversion();*/
        $this->assign('seller', $seller);
        //$sys_config = M('config')->cache(true, TPSHOP_CACHE_TIME)->select();
        $sys_config = M('config')->select();
        foreach ($sys_config as $k => $v) {
            $this->sys_config[$v['name']] = $v['value'];
        }
    }

    /*
     * 初始化操作
     */
    public function _initialize()
    {
        $this->assign('action', ACTION_NAME);
        //过滤不需要登陆的行为
        if (in_array(ACTION_NAME, array('login','pwd_verify', 'update_user_pwd','logout', 'vertify')) || in_array(CONTROLLER_NAME, array('Ueditor', 'Uploadify'))) {
            //return;
        } else {
            if (session('seller_id') > 0) {
                define('STORE_ID', session('store_id')); //将当前的session_id保存为常量，供其它方法调用
                $this->check_priv();//检查管理员菜单操作权限
                $menuArr = include APP_PATH . 'seller/conf/menu.php';
                $this->assign('menuArr', $menuArr);//所有菜单
                $this->assign('leftMenu', get_left_menu($menuArr));//左侧导航菜单
                if(is_array($_SESSION['seller_quicklink'])){
                    $this->assign('quicklink',array_keys($_SESSION['seller_quicklink']));//快捷操作菜单
                }
                $store = M('store')->where(array('store_id' => STORE_ID))->find();
                $storeMsgNoReadCount = Db::name('store_msg')->where(['store_id'=>STORE_ID,'open'=>0])->count();

                // 商家二维码，文件名输出
                $server_name = 'http://' . $_SERVER['SERVER_NAME'];
                $filename = "public/upload/seller/qrcode_".STORE_ID.".png";
                $aliyun_oss = C('aliyun_oss');
                Vendor('.aliyuncs.oss-sdk-php.autoload');
                $ossClient= new OssClient($aliyun_oss['accessKeyId'],$aliyun_oss['accessKeySecret'],$aliyun_oss['endpoint'],false);
                $object  = "upload/seller/qrcode/qrcode_".STORE_ID.".png";
                $exist = $ossClient->doesObjectExist($aliyun_oss['bucket'], $object);
                if(!$exist){
                    vendor('phpqrcode.phpqrcode');
                    error_reporting(E_ERROR);
                    $url = $server_name.U('Mobile/Store/index',['store_id'=>STORE_ID]);
                    $url = urldecode($url);
                    $errorCorrectionLevel = 'L';
                    $matrixPointSize = 6;
                    \QRcode::png($url, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
                    $ossClient->uploadFile($aliyun_oss['bucket'], $object, $filename);
                    $fileurl = str_replace('application/seller/controller/Base.php', $filename, __FILE__);
                    unlink($fileurl);
                }

                $this->assign('qrcode_seller', $aliyun_oss['ossurl'].$object);
				$this->assign("dis_seller",$store['dis']);

				
                $this->assign('storeMsgNoReadCount', $storeMsgNoReadCount);
                $this->assign('store', $store);
            } else {
                $this->error('请先登录', U('Admin/login'), 1);
            }
        }
        $this->public_assign();
    }

    /**
     * 保存公告变量到 smarty中 比如 导航
     */
    public function public_assign()
    {
        $tpshop_config = array();
        $tp_config = M('config')->cache(true)->select();
        foreach ($tp_config as $k => $v) {
            $tpshop_config[$v['inc_type'] . '_' . $v['name']] = $v['value'];
        }
        $this->assign('tpshop_config', $tpshop_config);
    }

    public function check_priv()
    {
        $seller = session('seller');
        if ($seller['is_admin'] == 0) {
            $ctl = request()->controller();
            $act = request()->action();
            $act_list = $seller['act_limits'];
            //无需验证的操作
            $uneed_check = array('login', 'logout', 'vertifyHandle', 'vertify', 'imageUp', 'upload', 'login_task');
            if ($ctl == 'Index' || $act_list == 'all') {
                //后台首页控制器无需验证,超级管理员无需验证
                return true;
            } elseif (strpos($act, 'ajax') || in_array($act, $uneed_check)) {
                //所有ajax请求不需要验证权限
                return true;
            } else {
                $right = Db::name('system_menu')->where("id", "in", $act_list)->cache(true)->getField('right', true);
                $role_right = '';
                if (count($right) > 0) {
                    foreach ($right as $val) {
                        $role_right .= $val . ',';
                    }
                }
                $role_right = explode(',', $role_right);
                //检查是否拥有此操作权限
                if (!in_array($ctl.'@'.$act, $role_right)) {
                    $this->error('您没有操作权限,请联店铺超级管理员分配权限', U('Index/index'));
                }
            }
        }
        return true;
    }

    public function ajaxReturn($data, $type = 'json')
    {
        exit(json_encode($data));
    }

    //图片上传 已修改成oss
    public function up_img($img_type,$name=null,$oldurl=null){
        $imgs=request()->file();
        if(!empty($imgs)){
           $file=new Uploadify();
           if(empty($name)){
                $info = $file->oss_upload($img_type,null,$oldurl); 
           }else{
                 $info = $file->oss_upload($img_type,$name,$oldurl); 
           }
           
        }
        return  $info;
    }
}
