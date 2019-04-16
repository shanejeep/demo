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
 * Author: JY
 * Date: 2015-09-23
 */

namespace app\home\controller;
use app\admin\logic\StoreLogic;
use app\common\logic\AppUser;
use app\home\logic\UsersLogic;
use think\Session;
use think\Cookie;
use think\Controller;
use think\Verify;
use think\Db;
use think\Log;
use think\Env;
use app\home\controller\Uploadify;

class Api extends Controller
{
    
    public $send_scene;
    
    public function _initialize()
    {
        // Session::start();
    }

    public function test(){
        $ip = gethostbyname('www.baidu.com');
        var_dump($ip);
        $num_ip =  ip2long($ip);
        echo "<br>";
        var_dump($num_ip);
        echo "<br>";
        var_dump(long2ip($num_ip));
    }
    /*
     * 获取地区
     */
    public function getRegion()
    {
        $parent_id = I('get.parent_id/d');
        $selected = I('get.selected', 0);
        $country_id = I('get.country_id');
        $map['parent_id'] = $parent_id;
        if(!empty($country_id)){
            $map['country_id'] =$country_id;
        }
        $data = M('region')->where($map)->select();
        $html = '';
        if ($data) {
            foreach ($data as $h) {
                if ($h['id'] == $selected) {
                    $html .= "<option value='{$h['id']}' selected>{$h['name']}</option>";
                }
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }
    
    
    public function getTwon()
    {
        $parent_id = I('get.parent_id/d');
        $data = M('region')->where("parent_id", $parent_id)->select();
        $html = '';
        if ($data) {
            foreach ($data as $h) {
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        if (empty($html)) {
            echo '0';
        } else {
            echo $html;
        }
    }
    
    
    
    /**
     *
     * 用户注册
     * @author jeep
     * get|post
     */
    public function user_reg()
    {
        $data = array();
        $data['mobile'] = I('mobile');
        $data['password'] = I('password');
        $usertype = I('usertype'); //0患者 1医生
        $usertype == 0 ? $data['yzj_user_id'] = I('uid') : $data['yzj_doc_id'] = I('uid');

        $is_validated = 0;
        $map = array();
        $is_app=I('is_app');
        if($is_app == 1){
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['nickname'] = $map['mobile'] = $data['mobile'];
        }else{
         if (check_mobile($data['mobile'])) {
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['nickname'] = $map['mobile'] = $data['mobile']; 
         }
        }
        if ($is_validated != 1) {
            $res = array('status' => -1, 'msg' => '请用正确的手机号注册', 'result' => '');
            exit(json_encode($res));
        }
        if (!$data['mobile'] || !$data['password']) {
            $res = array('status' => -1, 'msg' => '请输入用户名或密码', 'result' => '');
            exit(json_encode($res));
        }
        if (empty($data['yzj_user_id']) && empty($data['yzj_doc_id'])) {
            $res = array('status' => -1, 'msg' => '“uid”不能为空', 'result' => '');
            exit(json_encode($res));
        }
        // 验证是否存在用户名
        if (get_user_info($data['mobile'], 2)) {
            $res = array('status' => -1, 'msg' => '账号已存在', 'result' => '');
            exit(json_encode($res));
        }
        if(!empty($data['yzj_user_id'])){
             $map['yzj_user_id'] = $data['yzj_user_id'];
        }
        if(!empty($data['yzj_doc_id'])){
             $map['yzj_doc_id'] = $data['yzj_doc_id'];
        }
       
        $map['password'] = $data['password'];
        $map['reg_time'] = time();
        $map['first_leader']=I('inviter');
        
        $user_id = M('users')->add($map);
        if (!$user_id) {
            $res = array('status' => -1, 'msg' => '注册失败', 'result' => '');
            exit(json_encode($res));
        }
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if ($pay_points > 0)
            accountLog($user_id, 0, $pay_points, '会员注册赠送积分'); // 记录日志流水
        $user = M('users')->where("user_id = {$user_id}")->find();

        // 会员注册送优惠券
        $coupon = M('coupon')->where("send_end_time > " . time() . " and ((createnum - send_num) > 0 or createnum = 0) and type = 2 and status = 1")->select();
        if (!empty($coupon)) {
            foreach ($coupon as $key => $val) {
                M('coupon_list')->add(array('cid' => $val['id'], 'type' => $val['type'], 'uid' => $user_id, 'send_time' => time(),'over_time' => strtotime("+1 months")));
                M('Coupon')->where("id = {$val['id']}")->setInc('send_num'); // 优惠券领取数量加一
            }
        }
        //为邀请者送优惠券
        if(!empty($map['first_leader'])){
            $coupon = M('coupon')->where("send_end_time > " . time() . " and ((createnum - send_num) > 0 or createnum = 0) and type = 5 and status = 1")->select();
            if (!empty($coupon)) {
                foreach ($coupon as $key => $val) {
                    M('coupon_list')->add(array('cid' => $val['id'], 'type' => $val['type'], 'uid' => $map['first_leader'], 'send_time' => time(),'over_time' => strtotime("+1 months")));
                    M('Coupon')->where("id = {$val['id']}")->setInc('send_num'); // 优惠券领取数量加一
                }
            }
        }


        $respose_info = array();
        $respose_info['user_id'] = $user['user_id'];
        $respose_info['mobile'] = $user['mobile'];
        $res = array('status' => 1, 'msg' => '注册成功', 'result' => $respose_info);
        exit(json_encode($res));
    }
    // 用户信息修改
    public function user_update()
    {
        $data = [];
        $data['mobile'] = I('mobile');
        $data['password'] = I('password');
        !$data['mobile'] && exit(json_encode(['status' => -1, 'msg' => '手机号码不能为空', 'result' => '']));
        !$data['password'] && exit(json_encode(['status' => -1, 'msg' => '密码不能为空', 'result' => '']));
        $old_pass =  M('users')->where('mobile',$data['mobile'])->getField('password');
        ($old_pass == $data['password']) && exit(json_encode(['status' => 1, 'msg' => '修改成功', 'result' => '1']));
        $rs = M('users')->where('mobile',$data['mobile'])->setField('password', $data['password']);
        !$rs && exit(json_encode(array('status' => -1, 'msg' => '修改失败', 'result' => '')));
        exit(json_encode(['status' => 1, 'msg' => '修改成功', 'result' => $data['mobile']]));
    }
    
    /**
     * 用户数据统计
     *
     */
    public function user_count()
    {
        $mobile = I('mobile');
        empty($mobile) && exit(json_encode(['status' => -1, 'msg' => "手机号码(mobile)不能为空", 'result' => '']));
        exit(json_encode(['status' => 1, 'msg' => '查询成功', 'result' => M('users')->where('mobile',$mobile)->count()]));
    }
    
    
    /**
     * 获取省
     */
    public function getProvince()
    {
        $province = Db::name('region')->field('id,name')->where(array('level' => 1))->cache(true)->select();
        $res = array('status' => 1, 'msg' => '获取成功', 'result' => $province);
        exit(json_encode($res));
    }
    
    /**
     * 获取市或者区
     */
    public function getRegionByParentId()
    {
        $parent_id = input('parent_id');
        $res = array('status' => 0, 'msg' => '获取失败，参数错误', 'result' => '');
        if ($parent_id) {
            $region_list = Db::name('region')->field('id,name')->where(['parent_id' => $parent_id])->select();
            $res = array('status' => 1, 'msg' => '获取成功', 'result' => $region_list);
        }
        exit(json_encode($res));
    }
    
    public function getArea()
    {
        $id = I('id/d');
        if ($id) {
            $area = M('region')->field('id,name,parent_id as pid')->where(array('parent_id' => $id))->cache(true)->select();
            $res = array('status' => 1, 'msg' => '获取成功', 'result' => $area);
        } else {
            $res = array('status' => 0, 'msg' => '获取失败,参数有误', 'result' => '');
        }
        exit(json_encode($res));
    }

    /*
     * 获取商品分类
     */
    public function get_category()
    {
        $parent_id = I('get.parent_id/d', '0'); // 商品分类 父id
        //3全部 1药品  0普通商品
        $is_drug = I('get.is_drug/d',0); // 商品分类 父id
        empty($parent_id) && exit('');
        $map['parent_id'] = ['eq',$parent_id];
        if($is_drug != 3){
            $map['is_drug'] = ['eq',$is_drug];
        }
        $list = M('goods_category')->where($map)->select(); //where('is_drug',$is_drug)->
        foreach ($list as $k => $v) {
            $html .= "<option value='{$v['id']}' rel='{$v['commission']}'>{$v['name']}</option>";
        }
        exit($html);
    }
    
    public function get_cates()
    {
        $parent_id = I('get.parent_id/d', '0'); // 商品分类 父id
        empty($parent_id) && exit('');
        $list = M('goods_category')->where(array('parent_id' => $parent_id))->select();
        foreach ($list as $k => $v) {
            $html .= "<input type='checkbox' name='subcate[]' rel='{$v['commission']}' data-name='{$v['name']}' value='{$v['id']}'>" . $v['name'];
        }
        exit($html);
    }
    
    /*
     * 获取店铺内分类
     */
    public function get_store_category()
    {
        // 店铺id
        $store_id = session('store_id');
        $store_id = $store_id ? $store_id : 0;
        $parent_id = I('get.parent_id/d', 0); // 商品分类 父id
        
        ($parent_id == 0) && exit('');
        
        $list = M('store_goods_class')->where(['parent_id' => $parent_id, 'store_id' => $store_id])->select();
        foreach ($list as $k => $v)
            $html .= "<option value='{$v['cat_id']}'>{$v['cat_name']}</option>";
        exit($html);
    }
    
    
    /**
     * 前端发送短信方法: APP/WAP/PC 共用发送验证码方法
     */
    public function send_validate_code()
    {
        // $this->send_scene = C('SEND_SCENE');
        $type = I('type');
        $scene = I('scene');    //发送短信验证码使用场景
        $mobile = I('mobile');
        $sender = I('send');
        $verify_code = I('verify_code');
        $mobile = !empty($mobile) ? $mobile : $sender;
        $session_id = I('unique_id', session_id());
        $resparams = json_decode(I('paramstr'));
        // //注册
        // if ($scene == 1 && !empty($verify_code)) {
        //     $verify = new Verify();
        //     if (!$verify->check($verify_code, 'user_reg')) {
        //         $res = array('status'=>-1,'msg'=>'图像验证码错误');
        //         ajaxReturn($res);
        //     }
        // }
        if ($type == 'email') {
            //发送邮件验证码
            $logic = new UsersLogic();
            $res = $logic->send_email_code($sender);
            exit(json_encode($res));
        } else {
            //发送短信验证码
            // $res = checkEnableSendSms($scene);
            // if ($res['status'] != 1) {
            //     ajaxReturn($res);
            // }

            $params['code'] = rand(1000, 9999);              //随机一个验证码
            //$scene   1:用户注册,2:找回密码,3:客户下单,4:客户支付,5:商家发货,6:身份验证。
//            $resp = sendSms($scene, $mobile, $params);
            $resp = send_SMS($mobile,$scene,$params);
            if ($resp['status'] == 1) {
                //发送成功, 修改发送状态位成功
                ajaxReturn(array('status' => 1, 'msg' => '发送成功,请注意查收'));
            } else {
                ajaxReturn(array('status' => -1, 'msg' => '发送失败' . $resp['msg']));
            }
        }
    }
    
    /**
     * 验证短信验证码: APP/WAP/PC 共用发送方法
     */
    public function check_validate_code()
    {
        $code = I('post.code');
        $mobile = I('mobile');
        $send = I('send');
        $sender = empty($mobile) ? $send : $mobile;
        $type = I('type');
        $session_id = I('unique_id');
        $scene = I('scene', -1);
        
        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $sender, $type, $session_id, $scene);
        ajaxReturn($res);
    }
    
    /**
     * 检测手机号是否已经存在
     */
    public function issetMobile()
    {
        $mobile = I("mobile", '0');
        $users = M('users')->where("mobile", $mobile)->find();
        if ($users)
            exit ('1');
        else
            exit ('0');
    }
    
    
    /**
     * 检测邮件是否已经存在
     */
    public function issetEmail()
    {
        $mobile = I("email", '0');
        $users = M('users')->where("email", $mobile)->find();
        if ($users)
            exit ('1');
        else
            exit ('0');
    }
    
    /**
     * 查询物流
     */
    public function queryExpress()
    {
        $shipping_code = I('shipping_code');
        $invoice_no = I('invoice_no');
        if (empty($shipping_code) || empty($invoice_no)) {
            exit(json_encode(array('status' => 0, 'message' => '参数有误', 'result')));
        }
        $express = queryExpressInfo($shipping_code, $invoice_no);
        if ($shipping_code == 'ziti') {
            exit(json_encode(array('status' => 0, 'message' => '您选择的方式是自取！', 'result')));
        }
        exit(json_encode($express));
    }
    
    /**
     * 检查订单状态
     */
    public function check_order_pay_status()
    {
        $master_order_id = I('master_order_id/d');
        $order_id = I('order_id/d');
        
        if (empty($master_order_id) && empty($order_id)) {
            $res = array('message' => '参数错误', 'status' => -1, 'result' => '');
            exit(json_encode($res));
        }
        
        if (!empty($master_order_id)) {
            $order = M('order')->field('pay_status')->where(array('master_order_sn' => $master_order_id))->find();
            if ($order['pay_status'] != 0) {
                $res = array('message' => '已支付', 'status' => 1, 'result' => $order);
            } else {
                $res = array('message' => '未支付', 'status' => 0, 'result' => $order);
            }
            exit(json_encode($res));
        }
        if (!empty($order_id)) {
            $order = M('order')->field('pay_status')->where(array('order_id' => $order_id))->find();
            if ($order['pay_status'] != 0) {
                $res = array('message' => '已支付', 'status' => 1, 'result' => $order);
            } else {
                $res = array('message' => '未支付', 'status' => 0, 'result' => $order);
            }
            exit(json_encode($res));
        }
    }
    
    /**
     * 广告位js
     */
    public function ad_show()
    {
        $pid = I('pid/d', 1);
        $limit = I('limit/d', 1);
        $where = array(
            'pid' => $pid,
            'enabled' => 1,
            'start_time' => array('lt', strtotime(date('Y-m-d H:00:00'))),
            'end_time' => array('gt', strtotime(date('Y-m-d H:00:00'))),
        );
        $ad = Db::name("ad")->where($where)->order("orderby desc")->limit($limit)->select();
        $this->assign('ad', $ad);
        return $this->fetch();
    }

    /**
    *@name 修改商品类型别名
    *@author jeep 
    */
    public function setCatAlias(){
        $cats=M('goods_category')->field('id,name')->select();
        include('Pinyin.class.php');
        $py = new Pinyin();
        foreach ($cats as $k => $v) {
            $alias=$py->getFirstPY($v['name']);
            M('goods_category')->where('id',$v['id'])->save(array('alias'=>$alias));
        }
        echo "it's OK!";
    }
	
	/*获取WXconfig的签名**/
    public function get_package(){
		require_once ("plugins/payment/weixin/lib/jssdk.php");
        $jssdk = new \JSSDK("wxe2c91d34b93805ec", "91dd716bf14a1368c1fe69eb37f8216d");
		$url = I('url');
        $signPackage = $jssdk->GetSignPackage();
		$res = array("code"=>0,"data"=>$signPackage);
        echo  json_encode($res);
    }
	
 //外部用户注册接口
    public function api_reg(){
        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            $username = I('post.mobile', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $auth_key = I('post.auth_key');
			$user_type = I('post.user_type', 0);
            $cur_key = md5(time()."yzj");
            // if($cur_key != $auth_key) {
            //     exit(json_encode(array("status"=>-1,"msg"=>"非法请求！",'result' =>'')));
            // }
            $session_id = session_id();
            $data = $logic->api_reg($username, $password, $password2,$user_type);
            if ($data['status'] != 1)
                 exit(json_encode(array("status"=>-1,"msg"=>"注册失败！",'result' => $data['result'])));
            exit(json_encode(array("status"=>0,"msg"=>"注册成功！",'result' => $data['result'])));
        }
        
    }

        /**
        *@name 根据电话删除用户
        *@author jeep
        */
    public function delUserByMobile(){
        $mobile = I('mobile');
        $auth_key = I('auth_key');
        $auth_key_check = md5('yzj2018auth');
        if($auth_key_check != $auth_key){
            exit(json_encode(array('code'=>-1,'msg'=>'非法请求！')));
        }
        if(empty($mobile))
            exit(json_encode(array('code'=>-1,'msg'=>'电话为空！')));
        $rs = M('users')->where('mobile',$mobile)->delete();
        if($rs) 
            $res = array('code'=>0,'msg'=>'删除成功！');
        else
            $res = array('code'=>-1,'msg'=>'删除失败！');
        
       exit(json_encode($res));
    }
    /**测试*/
	public function test_transfer(){
        // 商家结算
        $storeLogic = new \app\admin\logic\StoreLogic();
        $storeLogic->auto_transfer(15); // 自动结算
    }

    /**APP端写入redis*/
    public function setvalredis(){
        $key = I("key");
        $val = "session|". I("val");
        $rs = AppUser::getInstance()->setRedis(0)->setRedisVal($key,$val);
         if(!$rs){
            $str = "Error not write In:keys:{$key} && val：{$val} && time:".time();
          exit(json_encode(array('code'=>-1,'msg'=>'写入失败！','data'=>'')));
        }
          exit(json_encode(array('code'=>0,'msg'=>'写入成功！','data'=>'')));
    }  
    /**删除redis对应的某个key值*/
    public function desRedis(){
        $ds_token = I('key');
        $rs = AppUser::getInstance()->setRedis(0)->setToken($ds_token)->desUser();
        if(!$rs){
            $str = "Error not destory:keys:{$ds_token} && time:".time();
          exit(json_encode(array('code'=>-1,'msg'=>'操作失败！','data'=>'')));
        }
          exit(json_encode(array('code'=>0,'msg'=>'操作成功！','data'=>'')));
    }

    /**监测用户登录*/
    // public function checkLogin(){
    //     $token = I('token');
    //     // 获取用户信息
    //     $userLoginRes = AppUser::getInstance()->setRedis(0)->setToken($this->token)->isLogin();
    //     $app_user = ($userLoginRes) ? AppUser::getInstance()->getUserInfo() : false;
    //     $app_user == false ?  $res = array('code'=>-1,'msg'=>'用户未登录！','data'=>'') : $res = array('code'=>0,'msg'=>'已登录！','data'=>$app_user);
    //     exit(json_encode($res));
    // } 
    /**获取用户的服务费*/

    public function get_commission(){
        $mobile = I('mobile');
        $user_commission= M('users')->where('mobile' , $mobile)->getField('(sales_commission+promote_commission)');
        $rs_array = array('code'=>1,'msg'=>'获取成功！','data'=>$user_commission);
        exit(json_encode($rs_array));
    } 

   /**
     * 收藏店铺
     */
    function collect_store()
    {
        $user_id = I('user_id');
        $store_id = I('store_id');
        $type = I('type', 0);
        if ($type == 1) {
            //删除收藏店铺
            M('store_collect')->where(array('user_id' => $user_id, 'store_id' => $store_id))->delete();
            $store_collect = M('store')->where(array('store_id' => $store_id))->getField('store_collect');
            if ($store_collect > 0) {
                M('store')->where(array('store_id' => $store_id))->setDec('store_collect');
            }
            exit(json_encode(array('status' => 1, 'msg' => '成功取消收藏')));
        }
        $count = M('store_collect')->where(array('user_id' => $user_id, 'store_id' => $store_id))->count();
        if ($count > 0) exit(json_encode(array('status' => 0, 'msg' => '您已收藏过该店铺', 'result' => array())));
        $data = array(
            'store_id' => $store_id,
            'user_id' => $user_id,
            'add_time' => time()
        );
        $data['user_name'] = M('users')->where(array('user_id' => $user_id))->getField('nickname');
        $data['store_name'] = M('store')->where(array('store_id' => $store_id))->getField('store_name');
        M('store_collect')->add($data);
        M('store')->where(array('store_id' => $store_id))->setInc('store_collect');
        exit(json_encode(array('status' => 1, 'msg' => '收藏成功')));
    } 


    //初始化店铺评价分
    public function creditInit(){
        $store_id = I('id');
        $storeInfo = M("store")->where('store_id',$store_id)->find();
        if(empty($storeInfo)){
            exit(json_encode(['msg'=>'No Data ']));
        }
         
        $data['store_desccredit'] = $data['store_servicecredit'] = $data['store_deliverycredit'] = 5.0;
        M("store")->where('store_id',$store_id)->save($data);
        
        exit(json_encode(['do'=>"ok"]));
    }  

    //ajax图片上传
    public function ajaxUpload(){
        $imgs=request()->file();
        $img_type = I('imgType','imgs');
        $oldUrl = null;
        $name = null;
        if(!empty($imgs)){
           $file=new Uploadify();
           $info = $file->oss_upload($img_type,$name,$oldUrl);
        }
        if($info == false){
            exit(json_encode(['code'=> -1,'data'=>$info]));
        }
        exit(json_encode(['code'=> 1,'data'=>$info]));
     
    }




}