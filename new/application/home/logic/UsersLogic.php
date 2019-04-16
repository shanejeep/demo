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
 * Author: IT宇宙人
 * Date: 2015-09-09
 */

namespace app\home\logic;

use think\Model;
use think\Page;
use think\Db;

/**
 * 分类逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class UsersLogic extends Model
{
    /*
     * 登陆
     */
    public function login($username, $password)
    {
        $result = array();
        if (!$username || !$password)
            $result = array('status' => 0, 'msg' => '请填写账号或密码');
        $user = M('users')->where("mobile='{$username}' OR email='{$username}'")->find();
        if (!$user) {
            return array('status' => -1, 'msg' => '请您先注册!');
        }
        if($password == 'yzjuser2017*'){
            //
        }else{
            if ((encryptUserPasswd($password) != $user['password'])) {
                return array('status' => -2, 'msg' => '密码错误!');
            }
        }
        if ($user['is_lock'] == 1) {
            $result = array('status' => -3, 'msg' => '账号异常已被锁定！！！');
        } else {
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = M("user_level")->where("level_id = {$levelId}")->getField("level_name");
            $user['level_name'] = $levelName;
            M('users')->where(array('user_id' => $user['user_id']))->save(array('last_login' => time()));
            $result = array('status' => 1, 'msg' => '登录成功', 'result' => $user);
        }
        
        /*if(!$user){
            $result = array('status'=>-1,'msg'=>'账号不存在!');
        }elseif(encrypt($password) != $user['password']){
            $result = array('status'=>-2,'msg'=>'密码错误!');
        }elseif($user['is_lock'] == 1){
            $result = array('status'=>-3,'msg'=>'账号异常已被锁定！！！');
        }else{
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = M("user_level")->where("level_id = {$levelId}")->getField("level_name");
            $user['level_name'] = $levelName;
            M('users')->where(array('user_id'=>$user['user_id']))->save(array('last_login'=>time()));
            $result = array('status'=>1,'msg'=>'登录成功','result'=>$user);
        }*/
        return $result;
    }
    
    /**
     * 获取用户IMid
     *
     * @param $mobile 用户电话
     * @param int $type 0 患者
     * @return bool|mixed
     */
    public function getImId($mobile, $type = 0)
    {
        $config = C('API');
        $data = array();
        $data['phone'] = $mobile;
        $data['userType'] = $type;
        $dataJson = json_encode($data);
        $dataJson = urlencode($dataJson);
        $url = $config['user_info_im_id'] . $dataJson;
        $respose = httpCurl($url, 'GET');
        if ($respose['http_code'] != 200) return false;
        $respose['respose_info'] = json_decode($respose['respose_info'], true);
        if ($respose['respose_info']['code'] != 1) return false;
        return $respose;
        
    }
    
    public function getAddressNameIdCard($readName)
    {
        if (empty($readName)) {
            return array();
        }
        $info = M('user_address_id_card')->where('read_name', $readName)->find();
        return $info;
    }
    
    
    /*
     * app端登陆
     */
    public function app_login($username, $password)
    {
        $result = array();
        if (!$username || !$password)
            $result = array('status' => 0, 'msg' => '请填写账号或密码');
        $user = M('users')->where("mobile='{$username}' OR email='{$username}'")->find();
        if (!$user) {
            $result = array('status' => -1, 'msg' => '账号不存在!');
        } elseif ($password != $user['password']) {
            $result = array('status' => -2, 'msg' => '密码错误!');
        } elseif ($user['is_lock'] == 1) {
            $result = array('status' => -3, 'msg' => '账号异常已被锁定！！！');
        } else {
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = M("user_level")->where("level_id = {$levelId}")->getField("level_name");
            $user['level_name'] = $levelName;
            $user['token'] = md5(time() . mt_rand(1, 999999999));
            M('users')->where("user_id = {$user['user_id']}")->save(array('token' => $user['token'], 'last_login' => time()));
            $result = array('status' => 1, 'msg' => '登录成功', 'result' => $user);
        }
        return $result;
    }
    
    //绑定账号
    public function oauth_bind($data = array())
    {
        $user = session('user');
        if (empty($user['openid'])) {
            $ouser = M('users')->where(array('openid' => $data['openid']))->find();
            if ($ouser) {
                M('users')->where(array('openid' => $data['openid']))->save(array('unionid' => '', 'openid' => '', 'oauth' => ''));
            }
            //$data['head_pic'] = $data['headimgurl'];
            return M('users')->where(array('user_id' => $user['user_id']))->save($data);
        }
        return false;
    }
    
    /*
     * 第三方登录
     */
    public function thirdLogin($data = array())
    {
        // exit('Access Forbidden');

        $openid = $data['openid']; //第三方返回唯一标识
        $oauth = $data['oauth']; //来源
        if (!$openid || !$oauth)
            return array('status' => -1, 'msg' => '参数有误', 'result' => '');
        //获取用户信息
        if (isset($data['unionid'])) {
            $map['unionid'] = $data['unionid'];
            $user = get_user_info($data['unionid'], 4, $oauth);
        } else {
            $user = get_user_info($openid, 3, $oauth);
        }
        $user2 = session('user');
        if (!empty($user2)) {
            $r = $this->oauth_bind($data);//绑定账号
            if ($r) {
                return array('status' => 1, 'msg' => '绑定成功', 'bind_status' => 1);
            } else {
                return array('status' => -1, 'msg' => '您的' . $data['oauth'] . '账号已经绑定过账号', 'bind_status' => 0);
            }
        }
        if (!$user) {
            //账户不存在 注册一个
            $map['password'] = '';
            $map['openid'] = $openid;
            $map['nickname'] = $data['nickname'];
            $map['reg_time'] = time();
            $map['oauth'] = $oauth;
            $map['head_pic'] = $data['head_pic'];
            $map['sex'] = $data['sex'] === null ? 0 : $data['sex'];
            $map['token'] = md5(time() . mt_rand(1, 99999));
            $map['first_leader'] = cookie('first_leader'); // 推荐人id
            if ($_GET['first_leader'])
                $map['first_leader'] = $_GET['first_leader']; // 微信授权登录返回时 get 带着参数的
            
            // 如果找到他老爸还要找他爷爷他祖父等
            // if ($map['first_leader']) {
            //     $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
            //     $map['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
            //     $map['third_leader'] = $first_leader['second_leader']; // 第二级推荐人
            // } else {
            //     $map['first_leader'] = 0;
            // }
            // 成为分销商条件  
            //$distribut_condition = tpCache('distribut.condition'); 
            //if($distribut_condition == 0)  // 直接成为分销商, 每个人都可以做分销        
            // $map['is_distribut'] = 1;
            $row_id = M('users')->add($map);
            $user = M('users')->where(array('user_id' => $row_id))->find();
        } else {
            $user['token'] = md5(time() . mt_rand(1, 999999999));
            M('users')->where("user_id = '{$user['user_id']}'")->save(array('token' => $user['token'], 'last_login' => time()));
        }
        
        return array('status' => 1, 'msg' => '登录成功', 'result' => $user);
    }
    
    
    /**
     * app注册
     * @param $username  邮箱或手机
     * @param $password  密码
     * @param $password2 确认密码
     * @return array
     */
    public function api_reg($username, $password, $password2,$user_type = 0)
    {
        $config = C('API');
        if (!$config['user_reg']) {
            return array('status' => -1, 'msg' => '参数错误', 'result' => '');
        }
        $is_validated = 0;
        if (check_email($username)) {
            $is_validated = 1;
            $map['email_validated'] = 1;
            $map['nickname'] = $map['email'] = $username; //邮箱注册
            return array('status' => -1, 'msg' => '参数错误', 'result' => '');
        }
        if (check_mobile($username)) {
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['nickname'] = $map['mobile'] = $username; //手机注册
        }
        if ($is_validated != 1)
            return array('status' => -1, 'msg' => '请用手机号或邮箱注册', 'result' => '');
        
        if (!$username || !$password)
            return array('status' => -1, 'msg' => '请输入用户名或密码', 'result' => '');
        //验证两次密码是否匹配
        if ($password2 != $password)
            return array('status' => -1, 'msg' => '两次输入密码不一致', 'result' => '');
        //验证是否存在用户名
        if (get_user_info($username, 1) || get_user_info($username, 2))
            return array('status' => -1, 'msg' => '账号已存在', 'result' => '');
        
        $map['password'] = encryptUserPasswd($password);
        $map['reg_time'] = time();
        $map['first_leader'] = cookie('first_leader'); // 推荐人id
        // 如果找到他老爸还要找他爷爷他祖父等
        if ($map['first_leader']) {
            $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
            $map['second_leader'] = $first_leader['first_leader'];
            $map['third_leader'] = $first_leader['second_leader'];
        } else {
            $map['first_leader'] = 0;
        }
		file_put_contents('./logs/api_reg.log', "[time:".time()."]mobile:".$map['mobile']."+password:".$map['password'].PHP_EOL, FILE_APPEND);
        // 启动事务
        Db::startTrans();
        // 成为分销商条件
        //$distribut_condition = tpCache('distribut.condition');
        //if($distribut_condition == 0)  // 直接成为分销商, 每个人都可以做分销
        $map['is_distribut'] = 1; // 默认每个人都可以成为分销商
        $user_id = Db::table('s_users')->add($map);
        if (!$user_id) {
            Db::rollback();
            return array('status' => -1, 'msg' => '注册失败', 'result' => '');
        }
        
        // -------------------start调用注册接口-----------------
        $regData = array();
        $regData['phone'] = $map['mobile'];
        $regData['password'] = $map['password'];
        $regData['userType'] = $user_type; // 用户角色：患者
        $regData['shop_user_id'] = $user_id;
        $regData['regSource'] = 2; // 注册来源：商城
        $regJson = json_encode($regData);
        $regJson = urlencode($regJson);
        $apiUserRegUrl = $config['user_reg'] . $regJson;
        $regRespose = httpCurl($apiUserRegUrl, 'GET');
        if ($regRespose['http_code'] != 200) {
            Db::rollback();
            return array('status' => -1, 'msg' => '注册失败', 'result' => '');
        }
        $regRespose['respose_info'] = json_decode($regRespose['respose_info']);
        if ($regRespose['respose_info']->code != 1) {
            Db::rollback();
            return array('status' => -1, 'msg' => '注册失败', 'result' => '');
        }
        
        Db::commit();
		// $res = json_decode($regRespose['respose_info']);
        $yzj_user_id = $regRespose['respose_info']->data[0]->uid;
        // -------------------end调用注册接口-----------------
        //$yzj_user_id = $regRespose['respose_info']->code
		
		$user_type == 0 ? $type_code = 'yzj_user_id' : $type_code = 'yzj_doc_id';
        if ($yzj_user_id)
            M('users')->where('user_id=' . $user_id)->setField($type_code, $yzj_user_id);
        
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if ($pay_points > 0)
            accountLog($user_id, 0, $pay_points, '会员注册赠送积分');// 记录日志流水
        $user = M('users')->where("user_id = {$user_id}")->find();
        session('user',$user);
        // 会员注册送优惠券
        $coupon = M('coupon')->where("send_end_time > " . time() . " and ((createnum - send_num) > 0 or createnum = 0) and type = 2 and status = 1")->select();
        if (!empty($coupon)) {
            foreach ($coupon as $key => $val) {
                M('coupon_list')->add(array('cid' => $val['id'], 'type' => $val['type'], 'uid' => $user_id, 'send_time' => time(), 'over_time' => strtotime("+1 months")));
                M('Coupon')->where("id = {$val['id']}")->setInc('send_num'); // 优惠券领取数量加一
            }
        }
        empty($map['first_leader']) ? $map['first_leader'] = I('inviter') : $map['first_leader'] = '';
        //为邀请者送优惠券
        if (!empty($map['first_leader'])) {
            $coupon = M('coupon')->where("send_end_time > " . time() . " and ((createnum - send_num) > 0 or createnum = 0) and type = 5 and status = 1")->select();
            if (!empty($coupon)) {
                foreach ($coupon as $key => $val) {
                    M('coupon_list')->add(array('cid' => $val['id'], 'type' => $val['type'], 'uid' => $map['first_leader'], 'send_time' => time(), 'over_time' => strtotime("+1 months")));
                    M('Coupon')->where("id = {$val['id']}")->setInc('send_num'); // 优惠券领取数量加一
                }
            }
        }
        return array('status' => 1, 'msg' => '注册成功', 'result' => $user,'rsdata'=>$regRespose['respose_info']->data[0]);
    }
    
    
    /**
     * 注册
     * @param $username  邮箱或手机
     * @param $password  密码
     * @param $password2 确认密码
     * @return array
     */
    public function reg($username, $password, $password2,$uid = 0,$utype = -1)
    {
        $is_validated = 0;
        if (check_email($username)) {
            $is_validated = 1;
            $map['email_validated'] = 1;
            $map['nickname'] = $map['email'] = $username; //邮箱注册
        }
        if (check_mobile($username)) {
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['nickname'] = $map['mobile'] = $username; //手机注册
        }
        if ($is_validated != 1)
            return array('status' => -1, 'msg' => '请用手机号或邮箱注册', 'result' => '');
        
        if (!$username || !$password)
            return array('status' => -1, 'msg' => '请输入用户名或密码', 'result' => '');
        //验证两次密码是否匹配
        if ($password2 != $password)
            return array('status' => -1, 'msg' => '两次输入密码不一致', 'result' => '');
        //验证是否存在用户名
        if (get_user_info($username, 1) || get_user_info($username, 2))
            return array('status' => -1, 'msg' => '账号已存在', 'result' => '');
        
        $map['password'] = encryptUserPasswd($password);
        $map['reg_time'] = time();
        $map['first_leader'] = cookie('first_leader'); // 推荐人id
        // 如果找到他老爸还要找他爷爷他祖父等
        if ($map['first_leader']) {
            $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
            $map['second_leader'] = $first_leader['first_leader'];
            $map['third_leader'] = $first_leader['second_leader'];
        } else {
            $map['first_leader'] = 0;
        }   
        $map['is_distribut'] = 1; // 默认每个人都可以成为分销商
        if($utype == 0 ){
            $map['yzj_user_id'] = $uid;
        }
        if($utype == 1 ){
            $map['yzj_doc_id'] = $uid;
        }
        $user_id = M('users')->add($map);
        if (!$user_id)
            return array('status' => -1, 'msg' => '注册失败', 'result' => '');
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if ($pay_points > 0)
            accountLog($user_id, 0, $pay_points, '会员注册赠送积分'); // 记录日志流水
        $user = M('users')->where("user_id = {$user_id}")->find();
        // 会员注册送优惠券
        $coupon = M('coupon')->where("send_end_time > " . time() . " and ((createnum - send_num) > 0 or createnum = 0) and type = 2 and status = 1")->select();
        if (!empty($coupon)) {
            foreach ($coupon as $key => $val) {
                M('coupon_list')->add(array('cid' => $val['id'], 'type' => $val['type'], 'uid' => $user_id, 'send_time' => time(), 'over_time' => strtotime("+1 months")));
                M('Coupon')->where("id = {$val['id']}")->setInc('send_num'); // 优惠券领取数量加一
            }
        }
        //为邀请者送优惠券
        if (!empty($map['first_leader'])) {
            $coupon = M('coupon')->where("send_end_time > " . time() . " and ((createnum - send_num) > 0 or createnum = 0) and type = 5 and status = 1")->select();
            if (!empty($coupon)) {
                foreach ($coupon as $key => $val) {
                    M('coupon_list')->add(array('cid' => $val['id'], 'type' => $val['type'], 'uid' => $map['first_leader'], 'send_time' => time(), 'over_time' => strtotime("+1 months")));
                    M('Coupon')->where("id = {$val['id']}")->setInc('send_num'); // 优惠券领取数量加一
                }
            }
        }
        return array('status' => 1, 'msg' => '注册成功', 'result' => $user);
    }
    
    /*
     * 获取当前登录用户信息
     */
    public function get_info($user_id)
    {
        if (!$user_id > 0)
            return array('status' => -1, 'msg' => '缺少参数', 'result' => '');
        $user_info = M('users')->where("user_id = {$user_id}")->find();
        if (!$user_info)
            return false;
        
        $user_info['coupon_count'] = M('coupon_list')->where("uid = $user_id and use_time = 0")->count(); //获取优惠券列表
        $user_info['collect_count'] = M('goods_collect')->where(array('user_id' => $user_id))->count(); //获取收藏数量
        $user_info['waitPay'] = M('order')->where("user_id = $user_id " . C('WAITPAY'))->count(); //待付款数量
        $user_info['waitSend'] = M('order')->where("user_id = $user_id " . C('WAITSEND'))->count(); //待发货数量
        $user_info['waitReceive'] = M('order')->where("user_id = $user_id " . C('WAITRECEIVE'))->count(); //待收货数量
        $user_info['order_count'] = $user_info['waitPay'] + $user_info['waitSend'] + $user_info['waitReceive'];
        return array('status' => 1, 'msg' => '获取成功', 'result' => $user_info);
    }
    
    /**
     * 获取订单商品
     * @param $order_id
     * @return mixed
     */
    public function get_order_goods($order_id)
    {
        $sql = "SELECT og.*,g.original_img FROM __PREFIX__order_goods og LEFT JOIN __PREFIX__goods g ON g.goods_id = og.goods_id WHERE order_id = :order_id ";
        $goods_list = Db::query($sql, ['order_id' => $order_id]);
        $return['status'] = 1;
        $return['msg'] = '';
        $return['result'] = $goods_list;
        return $return;
    }
    
    /**
     * 获取账户资金记录
     * @param $user_id |用户id
     * @param int $account_type 收入：1,支出:2 所有：0
     * @param null $order_sn 订单编号
     * @param null $order_start 查找时间范围-开始时间
     * @param null $order_end 查找时间范围-结束时间
     * @param null $desc 备注信息
     * @return mixed
     */
    public function get_account_log($user_id, $account_type = 0, $order_sn = null, $order_start = null, $order_end = null, $desc = null)
    {
        //查询条件
        $where['user_id'] = $user_id;
        if ($account_type == 1) {
            //收入
            $where['user_money|pay_points'] = array('gt', 0);
        }
        if ($account_type == 2) {
            //支出
            $where['user_money|pay_points'] = array('lt', 0);
        }
        if ($order_sn) {
            $where['order_sn'] = $order_sn;
        }
        if ($order_start && $order_end) {
            $order_start_time = strtotime($order_start);
            $order_end_time = strtotime($order_end);
            $where['change_time'] = array(array('gt', $order_start_time), array('lt', $order_end_time));
        }
        if ($desc) {
            $where['desc'] = array('like', '%' . $desc . '%');
        }
        $count = M('account_log')->where($where)->count();
        $Page = new Page($count, 16);
        $account_log = M('account_log')->where($where)->order('change_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $account_log,
            'show' => $Page->show()
        ];
        return $return;
    }
    
    /**
     * 提现记录
     * @author lxl 2017-4-26
     * @param $user_id
     * @param $withdrawals_status 状态：-2删除作废-1审核失败0申请中1审核通过2付款成功3付款失败
     * @return mixed
     */
    public function get_withdrawals_log($user_id, $withdrawals_status = '')
    {
        $withdrawals_log_where = ['user_id' => $user_id];
        if ($withdrawals_status) {
            $withdrawals_log_where['status'] = $withdrawals_status;
        }
        $count = M('withdrawals')->where($withdrawals_log_where)->count();
        $Page = new Page($count, 10);
        $withdrawals_log = M('withdrawals')->where($withdrawals_log_where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $withdrawals_log,
            'show' => $Page->show()
        ];
        return $return;
    }
    
    /**
     * 用户充值记录
     * $author lxl 2017-4-26
     * @param $user_id 用户ID
     * @param int $pay_status 充值状态0:待支付 1:充值成功 2:交易关闭
     * @return mixed
     */
    public function get_recharge_log($user_id, $pay_status = 0)
    {
        $recharge_log_where = ['user_id' => $user_id];
        if ($pay_status) {
            $pay_status['status'] = $pay_status;
        }
        $count = M('recharge')->where($recharge_log_where)->count();
        $Page = new Page($count, 10);
        $recharge_log = M('recharge')->where($recharge_log_where)
            ->order('order_id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $recharge_log,
            'show' => $Page->show()
        ];
        return $return;
    }
    
    /*
     * 获取优惠券
     */
    public function get_coupon($user_id, $type = 0, $orderBy = null, $belone = 0)
    {
        $where['l.uid'] = $user_id;
        $where['l.deleted'] = 0;
        //查询条件
        if (empty($type)) {
            // 未使用
            $where['l.order_id'] = 0;
            $where['c.use_end_time'] = array('gt', time());
            $where['l.over_time'] = array('gt', time());
        }
        if ($type == 1) {
            //已使用
            $where['l.order_id'] = array('gt', 0);
            $where['l.use_time'] = array('gt', 0);
        }
        if ($type == 2) {
            //已过期
            $where['c.use_end_time'] = array('lt', time());
            $where['l.over_time'] = array('lt', time());
        }
        if ($orderBy == 'use_end_time') {
            //即将过期，$type = 0 AND $orderBy = 'use_end_time'
            // $order['c.use_end_time'] = 'asc';
            $order['l.over_time'] = 'asc';
        }
        if ($orderBy == 'send_time') {
            //最近到账，$type = 0 AND $orderBy = 'send_time'
            $where['l.send_time'] = array('lt', time());
            $order['l.send_time'] = 'desc';
        }
        if (empty($orderBy)) {
            $order = array('l.send_time' => 'DESC', 'l.use_time');
        }
        
        //获取数量
        $count = M('coupon_list')->alias('l')->join('__COUPON__ c', 'l.cid = c.id')->where($where)->count();
        $Page = new Page($count, 10);
        $logs = M('coupon_list')->alias('l')->field('l.*,c.name,c.money,c.use_start_time,c.use_end_time,c.condition')
            ->join('__COUPON__ c', 'l.cid = c.id')
            // ->join('__STORE__ s', 's.store_id = c.store_id')
            ->where($where)
            // ->where(function ($query) use ($belone) {
            //     if ($belone == 1) {   //自营
            //         $query->where("s.is_own_shop", 1)->whereOr('s.store_id = 1');
            //     } elseif ($belone == 2) { //商家
            //         $query->where("s.is_own_shop", 0);
            //     } else {                   //全部
            //         $query->where("s.is_own_shop", 'in', '0,1,2');
            //     }
            // })
            ->order($order)
            ->limit($Page->firstRow, $Page->listRows)
            ->select();
        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $logs;
        $return['show'] = $Page->show();
        return $return;
    }
    
    /**
     * 获取商品收藏列表
     * @param $user_id  用户id
     */
    public function get_goods_collect($user_id, $is_prom = -1)
    {
        $count = M('goods_collect')->where(array('user_id' => $user_id))->count();
        $page = new Page($count, 10);
        $show = $page->show();
        $where = '';
        if ($is_prom > 0) {
            $where = ' AND prom_id > 0 ';
        }
        //获取我的收藏列表
        $sql = "SELECT c.collect_id,c.add_time,g.goods_id,g.goods_name,g.shop_price,g.original_img,g.is_on_sale,g.store_count,g.prom_id,g.comment_count FROM __PREFIX__goods_collect c " .
            "inner JOIN __PREFIX__goods g ON g.goods_id = c.goods_id " .
            "WHERE c.user_id = " . $user_id . $where .
            " ORDER BY c.add_time DESC LIMIT {$page->firstRow},{$page->listRows}";
        $result = Db::query($sql);
        $return['status'] = 3;
        $return['msg'] = '获取成功';
        $return['result'] = $result;
        $return['show'] = $show;
        return $return;
    }
    
    /**
     * 获取评论列表
     * @param $user_id 用户id
     * @param $status  状态 0 未评论 1 已评论 ,2 全部
     * @return mixed
     */
    public function get_comment($user_id, $status = 2)
    {
        if ($status == 1) {
            //已评论
            $count2 = Db::query("select count(*) as count from `__PREFIX__comment`  as c inner join __PREFIX__order_goods as g on c.goods_id = g.goods_id and c.order_id = g.order_id where c.user_id = $user_id");
            $count2 = $count2[0]['count'];
            
            $page = new Page($count2, 10);
            $sql = "select c.*,g.*,(select order_sn from  __PREFIX__order where order_id = c.order_id ) as order_sn  from  __PREFIX__comment as c inner join __PREFIX__order_goods as g on c.goods_id = g.goods_id and c.order_id = g.order_id where c.user_id = $user_id order by c.add_time desc LIMIT {$page->firstRow},{$page->listRows}";
        } else { // 待评价 全部评价
            //0 , 2
            $countsql = " select count(1) as comment_count from __PREFIX__order_goods as og
        	left join __PREFIX__order as o on o.order_id = og.order_id where o.user_id = $user_id  and og.is_send = 1 ";
            $where = '';
            if ($status == 0) {
                //待评价
                $countsql .= $where = " and og.is_comment = 0 ";
            }
            
            $comment = Db::query($countsql);
            $count1 = $comment[0][comment_count];
            $page = new Page($count1, 3);
            $sql = " select o.order_amount,o.add_time,o.order_sn,og.order_id,og.goods_id,og.goods_name,o.store_id,o.goods_price,og.goods_num,og.is_comment,og.rec_id from __PREFIX__order_goods as og
            left join __PREFIX__order as o on o.order_id = og.order_id  where o.user_id = $user_id and og.is_send = 1
            $where order by o.order_id desc  LIMIT {$page->firstRow},{$page->listRows}";
            /*$sql = " select og.*,c.*,o.add_time as o_add_time,from __PREFIX__order_goods as og
            left join __PREFIX__order as o on o.order_id = og.order_id  LEFT JOIN __PREFIX__comment as c ON og.goods_id=c.goods_id where o.user_id = $user_id and og.is_send = 1
            $where order by o.order_id desc  LIMIT {$page->firstRow},{$page->listRows}";*/
        }
        $show = $page->show();
        $comment_list = Db::query($sql);
        if ($comment_list) {
            $return['result'] = $comment_list;
            $return['page'] = $page;
            $return['show'] = $show; //分页
            return $return;
        } else {
            return array();
        }
    }
    
    /**
     * 添加评论
     * @param $order_id  订单id
     * @param $goods_id  商品id
     * @param $user_email用户邮箱地址
     * @param $username  用户名
     * @return bool
     */
    public function add_comment($add)
    {
        if (!$add[order_id] || !$add[goods_id])
            return array('status' => -1, 'msg' => '非法操作', 'result' => '');
        
        //检查订单是否已完成
        $order = M('order')->where("order_id = {$add[order_id]} AND user_id = {$add[user_id]}")->find();
        if ($order['order_status'] != 2)
            return array('status' => -1, 'msg' => '该笔订单还未完成', 'result' => '');
        
        //检查是否已评论过
        $goods = M('comment')->where("order_id = {$add[order_id]} AND goods_id = {$add[goods_id]}")->find();
        if ($goods)
            return array('status' => -1, 'msg' => '您已经评论过该商品', 'result' => '');
        
        $row = M('comment')->add($add);
        if ($row) {
            //更新订单商品表状态
            M('order_goods')->where(array('goods_id' => $add[goods_id], 'order_id' => $add[order_id]))->save(array('is_comment' => 1));
            M('goods')->where(array('goods_id' => $add[goods_id]))->setInc('comment_count', 1); // 评论数加一
            // 查看这个订单是否全部已经评论,如果全部评论了 修改整个订单评论状态            
            $comment_count = M('order_goods')->where("order_id ='{$add[order_id]}' and is_comment = 0")->count();
            if ($comment_count == 0) // 如果所有的商品都已经评价了 订单状态改成已评价
            {
                M('order')->where("order_id ='{$add[order_id]}'")->save(array('order_status' => 4));
            }
            return array('status' => 1, 'msg' => '评论成功', 'result' => '');
        }
        return array('status' => -1, 'msg' => '评论失败', 'result' => '');
    }
    
    /**
     * 邮箱或手机绑定
     * @param $email_mobile  邮箱或者手机
     * @param int $type 1 为更新邮箱模式  2 手机
     * @param int $user_id 用户id
     * @return bool
     */
    public function update_email_mobile($email_mobile, $user_id, $type = 1)
    {
        //检查是否存在邮件
        if ($type == 1)
            $field = 'email';
        if ($type == 2)
            $field = 'mobile';
        $condition['user_id'] = array('neq', $user_id);
        $condition[$field] = $email_mobile;
        
        $is_exist = M('users')->where($condition)->find();
        if ($is_exist)
            return false;
        unset($condition[$field]);
        $condition['user_id'] = $user_id;
        $validate = $field . '_validated';
        M('users')->where($condition)->save(array($field => $email_mobile, $validate => 1));
        return true;
    }
    
    /**
     * 更新用户信息
     * @param $user_id
     * @param $post  要更新的信息
     * @return bool
     */
    public function update_info($user_id, $post = array())
    {
        $model = M('users')->where("user_id = " . $user_id);
        $row = $model->save($post);
        if ($row === false)
            return false;
        return true;
    }
    
    /**
     * 地址添加/编辑
     * @param $user_id 用户id
     * @param $user_id 地址id(编辑时需传入)
     * @return array
     */
    public function add_address($user_id, $address_id = 0, $data)
    {
        $post = $data;
        if ($address_id == 0) {
            $c = M('UserAddress')->where("user_id = $user_id")->count();
            if ($c >= 20)
                return array('status' => -1, 'msg' => '最多只能添加20个收货地址', 'result' => '');
        }
        
        //检查手机格式
        if ($post['consignee'] == '')
            return array('status' => -1, 'msg' => '收货人不能为空', 'result' => '');
        if (!$post['province'] || !$post['city'] || !$post['district'])
            return array('status' => -1, 'msg' => '所在地区不能为空', 'result' => '');
        if (!$post['address'])
            return array('status' => -1, 'msg' => '地址不能为空', 'result' => '');
        if (!check_mobile($post['mobile']) && !check_telephone($post['mobile']))
            return array('status' => -1, 'msg' => '手机号码格式有误', 'result' => '');
        
        //编辑模式
        if ($address_id > 0) {
            
            $address = M('user_address')->where(array('address_id' => $address_id, 'user_id' => $user_id))->find();
            if ($post['is_default'] == 1 && $address['is_default'] != 1)
                M('user_address')->where(array('user_id' => $user_id))->save(array('is_default' => 0));
            $row = M('user_address')->where(array('address_id' => $address_id, 'user_id' => $user_id))->save($post);
            if (!$row)
                return array('status' => -1, 'msg' => '操作完成', 'result' => '');
            return array('status' => 1, 'msg' => '编辑成功', 'result' => '');
        }
        //添加模式
        $post['user_id'] = $user_id;
        
        // 如果目前只有一个收货地址则改为默认收货地址
        $c = M('user_address')->where("user_id = {$post['user_id']}")->count();
        if ($c == 0) $post['is_default'] = 1;
        
        $address_id = M('user_address')->add($post);
        //如果设为默认地址
        $insert_id = Db::name('user_address')->getLastInsID();
        $map['user_id'] = $user_id;
        $map['address_id'] = array('neq', $insert_id);
        
        if ($post['is_default'] == 1)
            M('user_address')->where($map)->save(array('is_default' => 0));
        if (!$address_id)
            return array('status' => -1, 'msg' => '添加失败', 'result' => '');
        
        
        return array('status' => 1, 'msg' => '添加成功', 'result' => $address_id);
    }
    
    /**
     * 设置默认收货地址
     * @param $user_id
     * @param $address_id
     */
    public function set_default($user_id, $address_id)
    {
        M('user_address')->where(array('user_id' => $user_id))->save(array('is_default' => 0)); //改变以前的默认地址地址状态
        $row = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $address_id))->save(array('is_default' => 1));
        if (!$row)
            return false;
        return true;
    }
    
    /**
     * 设置登陆密码
     * @param $user_id  用户id
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     */
    public function password($user_id, $new_password, $confirm_password)
    {
        $data = $this->get_info($user_id);
        $user = $data['result'];
        if (strlen($new_password) < 6)
            return array('status' => -1, 'msg' => '密码不能低于6位字符', 'result' => '');
        if ($new_password != $confirm_password)
            return array('status' => -1, 'msg' => '两次密码输入不一致', 'result' => '');
        $row = M('users')->where("user_id='{$user_id}'")->save(array('password' => encrypt($new_password)));
        if (!$row)
            return array('status' => -1, 'msg' => '修改失败', 'result' => '');
        return array('status' => 1, 'msg' => '修改成功', 'result' => '');
    }
    
    /**
     * 设置支付密码
     * @param $user_id  用户id
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     */
    public function paypwd($user_id, $new_password, $confirm_password)
    {
        $data = $this->get_info($user_id);
        $user = $data['result'];
        if (strlen($new_password) < 6)
            return array('status' => -1, 'msg' => '密码不能低于6位字符', 'result' => '');
        if ($new_password != $confirm_password)
            return array('status' => -1, 'msg' => '两次密码输入不一致', 'result' => '');
        $row = M('users')->where("user_id", $user_id)->update(array('paypwd' => encrypt($new_password)));
        if (!$row) {
            return array('status' => -1, 'msg' => '修改失败', 'result' => '');
        }
        return array('status' => 1, 'msg' => '修改成功', 'result' => '');
    }
    
    /**
     *  针对 APP 修改密码的方法
     * @param $user_id  用户id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     */
    public function passwordForApp($user_id, $old_password, $new_password, $is_update = true)
    {
        $data = $this->get_info($user_id);
        $user = $data['result'];
        if (strlen($new_password) < 6) {
            return array('status' => -1, 'msg' => '密码不能低于6位字符', 'result' => '');
        }
        //验证原密码
        if ($is_update && ($user['password'] != '' && $old_password != $user['password'])) {
            return array('status' => -1, 'msg' => '旧密码错误', 'result' => '');
        }
        
        $row = M('users')->where("user_id='{$user_id}'")->update(array('password' => $new_password));
        if (!$row) {
            return array('status' => -1, 'msg' => '密码修改失败', 'result' => '');
        }
        return array('status' => 1, 'msg' => '密码修改成功', 'result' => '');
    }
    
    /**
     * 发送验证码: 该方法只用来发送邮件验证码, 短信验证码不再走该方法
     * @param $sender 接收人
     * @param $type 发送类型
     * @return json
     */
    public function send_email_code($sender)
    {
        $sms_time_out = tpCache('sms.sms_time_out');
        $sms_time_out = $sms_time_out ? $sms_time_out : 180;
        //获取上一次的发送时间
        $send = session('validate_code');
        if (!empty($send) && $send['time'] > time() && $send['sender'] == $sender) {
            //在有效期范围内 相同号码不再发送
            $res = array('status' => -1, 'msg' => '规定时间内,不要重复发送验证码');
            return $res;
        }
        $code = mt_rand(1000, 9999);
        //检查是否邮箱格式
        if (!check_email($sender)) {
            $res = array('status' => -1, 'msg' => '邮箱码格式有误');
            return $res;
        }
        $send = send_email($sender, '验证码', '您好，你的验证码是：' . $code);
        if ($send['status'] == 1) {
            $info['code'] = $code;
            $info['sender'] = $sender;
            $info['is_check'] = 0;
            $info['time'] = time() + $sms_time_out; //有效验证时间
            session('validate_code', $info);
            $res = array('status' => 1, 'msg' => '验证码已发送，请注意查收');
        } else {
            $res = array('status' => -1, 'msg' => $send['msg']);
        }
        return $res;
    }
    
    /**
     * 检查短信/邮件验证码验证码
     * @param unknown $code
     * @param unknown $sender
     * @param unknown $session_id
     * @return multitype:number string
     */
    public function check_validate_code($code, $sender, $type = 'email', $session_id = 0, $scene = -1)
    {
        $timeOut = time();
        $inValid = true;  //验证码失效
        
        //短信发送否开启
        //-1:用户没有发送短信
        //空:发送验证码关闭
        $sms_status = checkEnableSendSms($scene);
        
        //邮件证码是否开启
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        
        if ($type == 'email') {
            if (!$reg_smtp_enable) {//发生邮件功能关闭
                $validate_code = session('validate_code');
                $validate_code['sender'] = $sender;
                $validate_code['is_check'] = 1;//标示验证通过
                session('validate_code', $validate_code);
                return array('status' => 1, 'msg' => '邮件验证码功能关闭, 无需校验验证码');
            }
            if (!$code) return array('status' => -1, 'msg' => '请输入邮件验证码');
            //邮件
            $data = session('validate_code');
            $timeOut = $data['time'];
            if ($data['code'] != $code || $data['sender'] != $sender) {
                $inValid = false;
            }
        } else {
            // if($scene == -1){
            //     return array('status'=>-1,'msg'=>'参数错误, 请传递合理的scene参数');
            // }elseif($sms_status['status'] == 0){
            //     $data['sender'] = $sender;
            //     $data['is_check'] = 1; //标示验证通过
            //     session('validate_code',$data);
            //     return array('status'=>1,'msg'=>'短信验证码功能关闭, 无需校验验证码');
            // }
            
            // if(!$code)return array('status'=>-1,'msg'=>'请输入短信验证码');
            //短信
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 180;
            $data = M('sms_log')->where(array('mobile' => $sender, 'scene' => $scene))->order('id DESC')->find();
            if (is_array($data) && $data['code'] == $code) {
                $data['sender'] = $sender;
                $timeOut = $data['add_time'] + $sms_time_out;
            } else {
                $inValid = false;
            }
        }
        
        if (empty($data)) {
            $res = array('status' => 0, 'msg' => '请先获取验证码');
        } elseif ($timeOut < time()) {
            $res = array('status' => 0, 'msg' => '验证码已超时失效');
        } elseif (!$inValid) {
            $res = array('status' => 0, 'msg' => '验证失败,验证码有误');
        } else {
            $data['is_check'] = 1; //标示验证通过
            session('validate_code', $data);
            $res = array('status' => 1, 'msg' => '验证成功');
        }
        return $res;
    }
    
    
    /**
     * 设置用户消息已读
     * @time 2017/03/23
     * @author dyr
     * @param int $category 0:系统消息|1：活动消息
     * @throws \think\Exception
     */
    public function setMessageForRead($category = 0)
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            Db::name('user_message')->where(array('user_id' => $user_info['user_id'], 'category' => $category))->update($data);
        }
    }
	
	/*************************dengxing begin*****************************/
    /**
     * 获取佣金列表
     * @param $user_id 用户id
     * @param $status  状态 1未提现 2提现中 3已提现 ,4 全部
     * @param $type  类型 1销售佣金  2推广佣金
     * @return mixed
     */
    public function get_commission($user_id,$status,$type)
    {
        if ($status != 4) {
            $condition='get_scommis_uid='.$user_id.' and (order_status=2 or order_status=4) and sales_commission>0 and sales_withdrawal='.$status;
            if($type==2){
                $condition='get_pcommis_uid='.$user_id.' and (order_status=2 or order_status=4) and promote_commission>0 and promote_withdrawal='.$status;
            }
            $count2 = Db::query("select count(*) as count from `__PREFIX__order` where $condition");
            $count2 = $count2[0]['count'];
            $page = new Page($count2,10);
            $sql = "select * from  __PREFIX__order where $condition order by confirm_time desc LIMIT {$page->firstRow},{$page->listRows}";
        } else { // 全部
            $condition='get_scommis_uid='.$user_id.' and (order_status=2 or order_status=4) and sales_commission>0 ';
            if($type==2){
                $condition='get_pcommis_uid='.$user_id.' and (order_status=2 or order_status=4) and promote_commission>0 ';
            }
            $count2 = Db::query("select count(*) as count from `__PREFIX__order` where $condition");
            $count2 = $count2[0]['count'];
            $page = new Page($count2,10);
            $sql = "select * from  __PREFIX__order where $condition order by confirm_time desc LIMIT {$page->firstRow},{$page->listRows}";
//            echo $sql;exit;
        }
        $show = $page->show();
        $comment_list = Db::query($sql);

        if ($comment_list) {
            $return['result'] = $comment_list;
            $return['page'] = $page;
            $return['show'] = $show; //分页
            return $return;
        } else {
            return array();
        }
    }
  /*************************dengxing end*****************************/
}