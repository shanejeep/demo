<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: 当燃   2016-05-10
 */ 
namespace app\mobile\controller;

use think\Db;
use think\Page;

class Activity extends MobileBase {
    public function index(){      
        return $this->fetch();
    }
   /**
    * 商品详情页
    */ 
    public function group(){
        //form表单提交
        C('TOKEN_ON',true);  
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $goods_id = I("get.id/d",0);

        $group_buy_info = M('GroupBuy')->where(['goods_id' => $goods_id , 'start_time' => ['<=' , time()] , 'end_time' => ['>='  ,time()] ])->find(); // 找出这个商品
        if(empty($group_buy_info)) 
        {
            //$this->error("此商品没有团购活动",U('Home/Goods/goodsInfo',array('id'=>$goods_id)));
        }
                    
        $goods = M('Goods')->where('goods_id' , $goods_id)->find();
        $goods_images_list = M('GoodsImages')->where('goods_id' , $goods_id)->select(); // 商品 图册
                
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where('goods_id', $goods_id)->select(); // 查询商品属性表
                        
        // 商品规格 价钱 库存表 找出 所有 规格项id
        $keys = M('SpecGoodsPrice')->where('goods_id' , $goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') ");         
        if($keys)
        {
             $specImage =  M('SpecImage')->where(['goods_id' => $goods_id , 'src' => ['<>' ,'']] )->getField("spec_image_id,src");// 规格对应的 图片表， 例如颜色                
             $keys = str_replace('_',',',$keys);             
             $sql  = "SELECT a.name,a.order,b.* FROM __PREFIX__spec AS a INNER JOIN __PREFIX__spec_item AS b ON a.id = b.spec_id WHERE b.id IN(:keys) ORDER BY a.order";
             $filter_spec2 = Db::query($sql , ['keys'=>$keys]);             
             foreach($filter_spec2 as $key => $val)
             {                                  
                 $filter_spec[$val['name']][] = array(
                     'item_id'=> $val['id'],
                     'item'=> $val['item'],
                     'src'=>$specImage[$val['id']],
                     );                 
             }            
        }                
        $spec_goods_price  = M('spec_goods_price')->where("goods_id" , $goods_id)->getField("key,price,store_count"); // 规格 对应 价格 库存表
        M('Goods')->where("goods_id=$goods_id")->save(array('click_count'=>$goods['click_count']+1 )); // 统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计           
        $this->assign('group_buy_info',$group_buy_info);
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true)); // 规格 对应 价格 库存表
        $this->assign('commentStatistics',$commentStatistics);
        $this->assign('goods_attribute',$goods_attribute);       
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('filter_spec',$filter_spec);
        $this->assign('goods_images_list',$goods_images_list);
        $this->assign('goods',$goods);
        return $this->fetch();
    } 
    
    
    /**
     * 团购活动列表
     */
    public function group_list()
    {
    	$count =  M('GroupBuy')->where(time()." >= start_time and ".time()." <= end_time ")->count();// 查询满足要求的总记录数
    	$Page = new Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数  	
    	$show = $Page->show();// 分页显示输出
    	$this->assign('page',$show);// 赋值分页输出
        $list = M('GroupBuy')->where(time()." >= start_time and ".time()." <= end_time ")->limit($Page->firstRow.','.$Page->listRows)->select(); // 找出这个商品        
        $this->assign('list', $list);
        return $this->fetch();
    }
    
    public function ajaxGroupListGetMore(){
        $p = I('p',1);
        $list = M('GroupBuy')->where(time()." >= start_time and ".time()." <= end_time ")->page($p,10)->select(); // 找出这个商品
        $this->assign('list', $list);
        return $this->fetch();
    }
    
    
    public function discount_list(){
        $prom_id = I('id/d');    //活动ID
        $where = array(     //条件
            'prom_type'=>3,
            'prom_id'=>$prom_id,
        );
        $count =  M('goods')->where($where)->count(); // 查询满足要求的总记录数
        $Page = new Page($count, 20); //分页类
        $prom_list = M('goods')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select(); //活动对应的商品
        $this->assign('prom_list', $prom_list);
        if(I('is_ajax')){
            return $this->fetch('ajax_discount_list');
        }
        return $this->fetch();
    }
    
    public function discount_goods_list(){
    	$prom_list = M('prom_goods')->where("end_time>".time())->select();
    	$this->assign('prom_list', $prom_list);
    	return $this->fetch();
    }

    /**
     * 商品活动页面
     * $author lxl
     * $time 2017-1
     */
    public function promote_goods(){
        $now_time = time();
        $where = " start_time <= $now_time and end_time >= $now_time ";
        $count = M('prom_goods')->where($where)->count();  // 查询满足要求的总记录数
        $pagesize = 2;  //每页显示数
        $Page  = new Page($count,$pagesize); //分页类
        $promote = M('prom_goods')->field('id,title,start_time,end_time,prom_img')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();    //查询活动列表
        $this->assign('promote',$promote);
        if(I('is_ajax')){
            return $this->fetch('ajax_promote_goods');
        }
        return $this->fetch();
    }
    /**
     * 抢购活动列表页
     */
    public function flash_sale_list()
    {
        $time_space = flash_sale_time_space();
        $this->assign('time_space', $time_space);
        return $this->fetch();
    }

    /**
     * 抢购活动列表ajax
     */
    public function ajax_flash_sale()
    {
        $p = I('p',1);
        //I('start_time');
        $start_time = strtotime(date("Y-m-d 00:00:00"));
        //I('end_time')
        // $end_time = strtotime(date("Y-m-d 23:59:59"));
        $end_time = time();
        $where = array(
            'f.status' => 1,
            'f.start_time'=>array('elt',time()),
            'f.end_time'=>array('egt',time())
        );
        $flash_sale_goods = M('flash_sale')
            ->field('f.end_time,f.goods_name,f.price,f.goods_id,f.price,g.shop_price,100*(FORMAT(f.buy_num/f.goods_num,2)) as percent')
            ->alias('f')
            ->join('__GOODS__ g','g.goods_id = f.goods_id')
            ->where($where)
            ->page($p,10)
            ->cache(true,TPSHOP_CACHE_TIME)
            ->select();
        $this->assign('flash_sale_goods',$flash_sale_goods);
        return $this->fetch();
    }

}