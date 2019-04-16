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
 * Date: 2015-09-09
 */

namespace app\seller\controller;

use think\AjaxPage;
use think\Db;
use think\Page;
use think\Verify;
use app\home\logic\UsersLogic;

class User extends Base
{
    /**
     * 账户资金调节
     */
    public function return_goods()
    {
        $desc = I('post.desc');
        $return_goods_id = I('return_goods_id/d');
        $return_goods = M('return_goods')->where(['id' => $return_goods_id, 'store_id' => STORE_ID])->find();
        empty($return_goods) && $this->error("参数有误");
        
        $user_id = $return_goods['user_id'];
        $order_goods = M('order_goods')->where(['order_id' => $return_goods['order_id'], 'goods_id' => $return_goods['goods_id'], 'spec_key' => $return_goods['spec_key']])->find();
        if ($order_goods['is_send'] != 1) {
            $is_send = array(0 => '未发货', 1 => '已发货', 2 => '已换货', 3 => '已退货');
            $this->error("商品状态为: {$is_send[$order_goods['is_send']]} 状态不能退款操作");
        }
        /*
                $order = M('order')->where("order_id = {$return_goods['order_id']}")->find();


                // 计算退回积分公式
                //  退款商品占总商品价比例 =  (退款商品价 * 退款商品数量)  / 订单商品总价      // 这里是算出 退款的商品价格占总订单的商品价格的比例 是多少
                //  退款积分 = 退款比例  * 订单使用积分

                // 退款价格的比例
                $return_price_ratio = ($order_goods['member_goods_price'] * $order_goods['goods_num']) / $order['goods_price'];
                // 退还积分 = 退款价格的比例 *
                $return_integral = ceil($return_price_ratio * $order['integral']);

                 // 退还金额 = (订单商品总价 - 优惠券 - 优惠活动) * 退款价格的比例 - (退还积分 + 当前商品送出去的积分) / 积分换算比例
                 // 因为积分已经退过了, 所以退金额时应该把积分对应金额推掉 其次购买当前商品时送出的积分也要退回来,以免被刷积分情况

                $return_goods_price = ($order['goods_price'] - $order['coupon_price'] - $order['order_prom_amount']) * $return_price_ratio - ($return_integral + $order_goods['give_integral']) /  tpCache('shopping.point_rate');
                $return_goods_price = round($return_goods_price,2); // 保留两位小数
         */
        
        $refund = order_settlement($return_goods['order_id'], $order_goods['rec_id']);  // 查看退款金额
        //  print_r($refund);
        $return_goods_price = $refund['refund_settlement'] ? $refund['refund_settlement'] : 0; // 这个商品的退款金额
        //$refund_integral = $refund['refund_integral'] ? ($refund['refund_integral'] * -1) : 0; // 这个商品的退积分
        $refund_integral = $refund['refund_integral'] - $refund['give_integral'];
        
        
        if (IS_POST) {
            if (!$desc)
                $this->error("请填写操作说明");
            if (!$user_id > 0)
                $this->error("参数有误");

//            $pending_money = M('store')->where(" store_id = ".STORE_ID)->getField('pending_money'); // 商家在未结算资金 
//            if($pending_money < $return_goods_price)
//                $this->error("你的未结算资金不足 ￥{$return_goods_price}");
            
            //     M('store')->where(" store_id = ".STORE_ID)->setDec('pending_money',$user_money); // 从商家的 未结算自己里面扣除金额
            $result = storeAccountLog(STORE_ID, 0, $return_goods_price * -1, $desc, $return_goods['order_id'], $return_goods['order_sn']);
            if ($result) {
                accountLog($user_id, $return_goods_price, $refund_integral, '订单退货', 0, $return_goods['order_id'], $return_goods['order_sn']);
            } else {
                $this->error("操作失败");
            }
            M('order_goods')->where("rec_id", $order_goods['rec_id'])->save(array('is_send' => 3));//更改商品状态
            // 如果一笔订单中 有退货情况, 整个分销也取消                      
            M('rebate_log')->where("order_id", $return_goods['order_id'])->save(array('status' => 4, 'remark' => '订单有退货取消分成'));
            
            $this->success("操作成功", U("Order/return_list"));
            exit;
        }
        
        $this->assign('return_goods_price', $return_goods_price);
        $this->assign('return_integral', $refund_integral);
        $this->assign('order_goods', $order_goods);
        $this->assign('user_id', $user_id);
        return $this->fetch();
    }
    
    public function update_user_pwd()
    {
        if (IS_POST) {
            $username = I('post.username');
            if (!$username) $this->error('请输入电话号码');
            
            if (!check_mobile($username)) $this->error('请输入正确的手机号码');
            
            $user_id = M('users')->where('mobile=' . $username)->getField('user_id');
            if (!$user_id) $this->error('用户未注册');
            
            $store_info = M('store')->where('user_id=' . $user_id)->find();
            if (!$store_info) $this->error('未开通店铺');
            
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password != $password2) $this->error('密码输入不一致，请重新输入');
            
            $verify_code = I('post.verify_code');
            if (!$verify_code) $this->error('请输入图形验证码');
            
            $code = I('post.code', '');
            if (!$code) $this->error('请输入手机验证码');
            // 找回密码
            $scene = 2;
            
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_update_pwd')) {
                $this->error('验证码错误');
            }
            
            $session_id = session_id();
            $logic = new UsersLogic();
            $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
            if ($check_code['status'] != 1) {
                $this->error($check_code['msg']);
            }
            
            // -------------------start调用修改登录密码接口-----------------
            $encodePwd = encryptUserPasswd($password);
            $config = C('API');
            $data = array();
            $data['phone'] = $username;
            $data['password'] = $encodePwd;
            $regJson = json_encode($data);
            $regJson = urlencode($regJson);
            $apiUserRegUrl = $config['user_modify_login_pwd'] . $regJson;
            $respose = httpCurl($apiUserRegUrl, 'GET');
            if ($respose['http_code'] != 200) $this->error('修改失败');
            
            $respose['respose_info'] = json_decode($respose['respose_info']);
            
            if ($respose['respose_info']->code != 1) $this->error('修改失败');
            
            $rs = M('users')->where('mobile=' . $username)->setField('password', $encodePwd);
            if (!$rs) $this->error('修改失败');
            $this->success('修改成功');
        }
        
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }
    
    /**
     * 验证码获取
     */
    public function pwd_verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_update_pwd';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }
    
    /**
     *
     * @time 2017/03/23
     * @author dyr
     * 商家发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users', $users);
        return $this->fetch();
    }
    
    /**
     * 商家发送活动消息
     * @author dyr
     * @time  2017/03/23
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $seller_id = session('seller_id');
        $users = I('post.user/a');//个体id
        $message = array(
            'seller_id' => $seller_id,
            'message' => $message,
            'category' => 1,//活动消息
            'send_time' => time(),
            'type' => 0,//个体消息
        );
        if (!empty($users)) {
            $create_message_id = Db::name('Message')->insertGetId($message);
            foreach ($users as $key) {
                Db::name('user_message')->insert(['user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 1]);
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }
}