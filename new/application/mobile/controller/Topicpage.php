<?php

/**
 * 专题
 */

namespace app\mobile\controller;

class Topicpage extends MobileBase
{
    // 保险
    public function insurance()
    {
        return $this->fetch();
    }
    
    public function qdnxj()
    {
        return $this->fetch();
    }
	public function yearh()
    {
        return $this->fetch();
    }
    
    public function page1212()
    {
        return $this->fetch();
    }
	
	//展示收益
	public function show_lucre()
    {
        return $this->fetch();
    }
	
	
	//幸运转盘
	public function zhuanpan(){
		$user = session('user');
		$count_info = M("topic_count")->where('uid',$user['user_id'])->where('cdate',date("Ymd"))->find();
		if(empty($count_info)){
			M("topic_count")->add(array('uid'=>$user['user_id'],'num'=>3,'cdate'=>date("Ymd")));
		}
		$this->assign("num",$count_info['num']);
	  	$this->assign('app_type',$this->app_type);
	  	$this->assign('access_type',$this->access_type);
	  	$this->assign('token',$this->token);
		return $this->fetch();
	}
	//处理幸运转盘
	public function do_zp(){
		$user = session('user');
		if(empty($user)){
			$_SESSION['order_preview_backurl'] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			exit(json_encode(array('code'=>-1,'msg'=>"请先登录！")));
		}
		$map['uid'] = $user['user_id'];
		$map['cdate'] = date("Ymd");
		$do_count = M('topic_count')->where($map)->value("num");
		if($do_count <= 0)		exit(json_encode(array('code'=>-1,'msg'=>"您的抽奖次数已用完，请明日再来！！")));
		$c_num = rand(1,100);
		$flag = true;
		//优惠券信息
        $data['type'] = 6;
        $data['uid'] = $user['user_id'];
        $data['order_id'] = $data['use_time'] = 0;
        $data['send_time'] = time();
		$config = C('API');
		switch($c_num){
			case $c_num >= 1 && $c_num <20:	// 再抽一次
				$rid = 6; 
				$rs = M('topic_count')->where($map)->setInc("num");
				if(!$rs)	$flag = false;
				$msg = "再抽一次吧！";
			break;
			case $c_num >=20 && $c_num <55:	// 20医豆
				$rid = 3;  //
				$data = array();
				$data['number'] = 20;
				$data['phone'] = $user["mobile"];
				switch ($this->app_type) {
					case 1:
					case 2:
						$data['type'] = 0;
						break;
					case 3:
					case 4:
						$data['type'] = 1;
						break;
					default:
						$data['type'] = 0;	
				}
				$data['token'] = '313533333839626573746D656469';
				$dataJson = json_encode($data);
				$dataJson = urlencode($dataJson);
				$url =  $config['addBean'] . $dataJson;
				$respose = httpCurl($url, 'POST');
				if ($respose['http_code'] != 200) $flag = false;;
				$respose['respose_info'] = json_decode($respose['respose_info'], true);
				if ($respose['respose_info']['code'] != 20000) $flag = false;
				$msg = "您的奖励是20医豆！";
			break;
			case $c_num >=55 && $c_num <75:	//5元优惠券
				$rid = 2;  //
				$data['cid'] = 23;
				$data['over_time'] = M('coupon')->where('id',$data['cid'])->value("use_end_time");
				$rs = M('coupon_list')->add($data);
				if(!$rs)	$flag = false;
				$msg = "您的奖励是5元商城优惠券！";
			break;
			case $c_num >=75 && $c_num <96:	//10元优惠券
				$rid = 5;  //
				$data['cid'] = 24;
				$data['over_time'] = M('coupon')->where('id',$data['cid'])->value("use_end_time");
				$rs = M('coupon_list')->add($data);
				if(!$rs)	$flag = false;
				$msg = "您的奖励是10元商城优惠券！";
			break;
			case $c_num >=96 && $c_num <=100:	//819医豆
				$rid = 1;  
					$data = array();
				$data['number'] = 819;
				$data['phone'] = $user["mobile"];
				switch ($this->app_type) {
					case 1:
					case 2:
						$data['type'] = 0;
						break;
					case 3:
					case 4:
						$data['type'] = 1;
						break;
					default:
						$data['type'] = 0;	
				}
				$data['token'] = '313533333839626573746D656469';
				$dataJson = json_encode($data);
				$dataJson = urlencode($dataJson);
				$url =  $config['addBean'] . $dataJson;
				$respose = httpCurl($url, 'POST');
				if ($respose['http_code'] != 200) $flag = false;;
				$respose['respose_info'] = json_decode($respose['respose_info'], true);
				if ($respose['respose_info']['code'] != 1) $flag = false;
				$msg = "您的奖励是819医豆！";
			break;
		}
		if($flag == false){
			exit(json_encode(array('code'=>2,'rid'=>$rid,'msg'=>"网络异常，请稍后再试~")));	//如果调用接口失败，则不减次数
		}
		M('topic_count')->where($map)->setDec("num");  //扣除用户次数
		M("topic_reward")->add(array('rid'=>$rid,'uid'=>$user['user_id'],"add_time"=>time()));  //奖励存表
		exit(json_encode(array('code'=>1,'rid'=>$rid,'msg'=>$msg)));
	}
	
    
}
