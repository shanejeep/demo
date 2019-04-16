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
 * Date: 2016-03-19
 */

namespace app\common\logic;

use think\Model;
/**
 * Class orderLogic
 * @package Common\Logic
 */
class OrderLogic extends Model
{	
	public function get_order_info($order_id){
		return M('order')->where(array('order_id'=>$order_id))->find();
	}
	
	public function get_order_goods_list($order_id){
		return M('order')->where(array('order_id'=>$order_id))->select();
	}
	
	//取消订单
	public function cancel_order($user_id,$order_id){
		$order = M('order')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->find();
		//检查是否未支付订单 已支付联系客服处理退款
		if(empty($order))
			return array('status'=>-1,'msg'=>'订单不存在','result'=>'');
		//检查是否未支付的订单
		if($order['pay_status'] > 0 || $order['order_status'] > 0)
			return array('status'=>-1,'msg'=>'支付状态或订单状态不允许','result'=>'');
		//获取记录表信息
		//$log = M('account_log')->where(array('order_id'=>$order_id))->find();
		//有余额支付的情况
		if($order['user_money'] > 0 || $order['integral'] > 0){
			accountLog($user_id,$order['user_money'],$order['integral'],"订单取消，退回{$order['user_money']}元,{$order['integral']}积分");
		}
	
		$row = M('order')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->save(array('order_status'=>3,'user_note'=>'用户取消订单'));
		$data['order_id'] = $order_id;
		$data['action_user'] = $user_id;
		$data['action_note'] = '您取消了订单';
		$data['order_status'] = 3;
		$data['pay_status'] = $order['pay_status'];
		$data['shipping_status'] = $order['shipping_status'];
		$data['log_time'] = time();
		$data['status_desc'] = '用户取消订单';
		M('order_action')->add($data);//订单操作记录
	
		if(!$row) return array('status'=>-1,'msg'=>'操作失败','result'=>'');
		return array('status'=>1,'msg'=>'操作成功','result'=>'');
	}
	 
	/*
	 * 获取最近一笔订单
	*/
	public function get_last_order($user_id){
		$last_order = M('order')->where("user_id",$user_id)->order('order_id DESC')->find();
		return $last_order;
	}
	
	/*
	 * 获取订单商品
	*/
	public function get_order_goods($order_id){
		$sql = "SELECT og.*,g.original_img FROM __PREFIX__order_goods og LEFT JOIN __PREFIX__goods g ON g.goods_id = og.goods_id WHERE order_id = :order_id ";
		$goods_list = $this->query($sql , ['order_id' => $order_id]);
		$return['status'] = 1;
		$return['msg'] = '';
		$return['result'] = $goods_list;
		return $return;
	}
	
	public function check_dispute_order($order_id,$complain_id,$user_id){
		$res = array('flag'=>1,'data'=>'');
		$complain_log = M('complain')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->find();
		if($complain_log){
			$res = array('flag'=>2,'msg'=>"该订单已经投诉过，请在用户中心投诉管理查看处理进度",'data'=>'');
		}else{
			$order = $this->get_order_info($order_id);
			if($order['pay_status'] == 0){
				$res = array('flag'=>2,'msg'=>"该订单并未付款，无法进行投诉交易服务。",'data'=>'');
			}elseif($complain_id == 1 && $order['shipping_status'] == 1){
				//配送投诉，如果卖家已经发货，所以不能提交
				$res = array('flag'=>2,'data'=>'','msg'=>"该纠纷类型暂无法提交，可能是您的订单已完成，或您已申请过同类型的纠纷单，建议您优先联系卖家客服处理。前往帮助中心了解<a href=''>纠纷发起规则</a>。");
			}elseif(in_array($complain_id,array(2,3,7,8,9,10))){
				//查看是否有申请退货退款，换货维修售后服务
				$return_goods = M('return_goods')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->select();
				$headhtml = '<div class="choosetyp6"><span style="width:20%">是否选择</span><span style="width:20%">售后服务单</span><span style="width:40%">对应商品</span><span style="width:20%">售后服务单状态</span></div>';
				$mismatch = $headhtml.'<div class="applyrestore"><p class="tit">如果没有满足条件的售后服务单</p><p class="mali">如果你遇到售后类型问题，可以先去申请返修退换货；倘若在售后过程中仍有问题，可再来申请交易纠纷</p><a href="'.U('Order/return_goods_index').'">申请返修退换货</a></div>';
				if(empty($return_goods)){
					$res = array('flag'=>2,'data'=> $mismatch,'msg'=>"该纠纷类型暂无法提交，可能是该订单下没有审核不通过的退货服务单，建议您选择其他纠纷类型，或联系卖家客服处理。前往帮助中心了解<a href=''>纠纷发起规则</a>。");
				}else{
					$state = C('REFUND_STATUS');
					$html = $headhtml;
					foreach ($return_goods as $k=>$val){
						$html .= '<div class="choosetyp6">';
						$goods_url = U('Goods/goodsInfo',array('id'=>$val['goods_id']));
						$return_url = U('Order/return_goods_info',array('id'=>$val['id']));
						$goods_name = M('order_goods')->where(array('order_id'=>$order_id,'goods_id'=>$val['goods_id']))->getField('goods_name');
						if($k == 0){
							$html .= '<span style="width:20%"><input type="radio" checked name="order_goods_id" value="'.$val['goods_id'].'">&nbsp;&nbsp;'.$val['id'].'</span>';
						}else{
							$html .= '<span style="width:20%"><input type="radio" name="order_goods_id" value="'.$val['goods_id'].'">&nbsp;&nbsp;'.$val['id'].'</span>';
						}
						$html .= '<span style="width:20%"><a href="'.$return_url.'" target="_blank"><img src="'.goods_thum_images($val['goods_id'],60,60).'" height="60" title=""></a></span>';
						$html .= '<span style="width:40%"><a class="shop_name_ir" href="'.$goods_url.'" target="_blank">'.$goods_name.'</a></span>';
						$html .= '<span style="width:20%">'.$state[$val['status']].'</span></div>';
					}
					
					$res = array('flag'=>1,'data'=>$html);//如果售后服务单有多个，那就让用户选择投诉
					if(count($return_goods) == 1){
						$res = array('flag'=>1,'data' => $html);
						$return_goods = $return_goods[0];
						if($return_goods['status'] == -2){
							$res = array('flag'=>2,'msg'=>"该服务单会员自己选择了取消，建议您优先联系卖家客服解决。前往帮助中心了解纠纷发起规则。",'data'=>'');
						}
						if($return_goods['status'] == -1){
							$res = array('flag'=>1,'data'=> $html);
						}
						if($return_goods['status'] == 0){
							if(($return_goods['addtime']+48*3600)>time()){
								$res = array('flag'=>2,'msg'=>'该纠纷类型暂无法提交，您的该类型服务单还在等待卖家审核中');
							}
						}
						if($return_goods['status']>=1){
							if($complain_id == 10){
								if(empty($return_goods['delivery'])){
									$res = array('flag'=>2,'data'=>'','msg'=>"该纠纷类型暂无法提交，可能是您还未在服务单中上传物流信息，或服务单已处理完成，建议您优先联系卖家客服解决。前往帮助中心了解纠纷发起规则。");
								}elseif(($return_goods['receivetime']+48*3600)>time()){
									$res = array('flag'=>2,'data'=>'','msg'=>"该服务单还在等待卖家处理，并未超过48小时，建议您优先联系卖家客服解决。前往帮助中心了解纠纷发起规则。");
								}
							}
							if($complain_id == 9 && $return_goods['status']<4){
								$res = array('flag'=>2,'data'=>'','msg'=>"该服务单还在等待卖家处理，并未完成，建议您优先联系卖家客服解决。前往帮助中心了解纠纷发起规则。");
							}
						}
						//找不到退货退款服务单
						if($complain_id<4 && $return_goods['type']==1){
							$res = array('flag'=>2,'data'=>$mismatch,'msg'=>"该纠纷类型暂无法提交，可能是该订单下没有审核不通过的此类服务单，建议您选择其他类型，或联系卖家客服解决。前往帮助中心了解纠纷发起规则");
						}
						//找不到换货维修服务单
						if($complain_id>6 && $return_goods['type']==0){
							$res = array('flag'=>2,'data'=>$mismatch,'msg'=>"该纠纷类型暂无法提交，可能是该订单下没有审核不通过的此类服务单，建议您选择其他类型，或联系卖家客服解决。前往帮助中心了解纠纷发起规则");
						}
					}
				}
			}
		}
		return $res;
	}
}