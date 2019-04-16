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
namespace app\home\logic;
use think\Model;
use think\Db;
use think\Page;
/**
 *
 * Class orderLogic
 * @package Home\Logic
 */
class StoreLogic extends Model
{
    /**
     * 更新店铺评分
     * @param $store_id
     */
    public function updateStoreScore($store_id){
        $store_where = array('store_id'=>$store_id,'deleted'=>0);
        $store['store_desccredit'] = M('order_comment')->where($store_where)->avg('describe_score');
        $store['store_servicecredit'] = M('order_comment')->where($store_where)->avg('seller_score');
        $store['store_deliverycredit'] = M('order_comment')->where($store_where)->avg('logistics_score');
        M('store')->where(array('store_id'=>$store_id))->save($store);
    }
    /**
     * 获取行业评分
     * @param $sc_id
     * @return array
     */
    public function storeComparison($sc_id)
    {
        $comparison_where = array('sc_id' => $sc_id, 'deleted' => 0);
        $creditone=Db::name('store')->where($comparison_where)->cache('true')->avg('store_desccredit');
        $comparison['store_desccredit_avg'] = empty($creditone) ? 0 : number_format($creditone,1);
        $credittwo=Db::name('store')->where($comparison_where)->cache('true')->avg('store_servicecredit');
        $comparison['store_servicecredit_avg'] =  empty($credittwo) ? 0 : number_format($credittwo,1);
        $creditthree=Db::name('store')->where($comparison_where)->cache('true')->avg('store_deliverycredit');
        $comparison['store_deliverycredit_avg'] = empty($creditthree) ? 0 : number_format($creditthree,1);
        return $comparison;
    }
    /**
     * 获取店铺评分
     * @param $store_id
     * @return array
     */
    public function storeCommentStatistics($store_id)
    {
        $store = M('store')->where(array('store_id' => $store_id, 'deleted' => 0))->find();
        $store_comment_score = array(
            'store_desccredit' => number_format($store['store_desccredit'], 1),
            'store_servicecredit' => number_format($store['store_servicecredit'], 1),
            'store_deliverycredit' => number_format($store['store_deliverycredit'], 1)
        );
        return $store_comment_score;
    }

    /**
     * 获取百分数
     * @param $comparison
     * @param $store_comment_score
     * @return array
     */
    public function storeMatch($comparison, $store_comment_score)
    {
        if ($store_comment_score['store_desccredit'] == 0
            || $store_comment_score['store_servicecredit'] == 0
            || $store_comment_score['store_deliverycredit'] == 0
        ) {
            $store_match = array(
                'desccredit_match' => 0,
                'servicecredit_match' => 0,
                'servicecredit_deliverycredit' => 0
            );
        } else {
            $store_match = array(
                'desccredit_match' => $this->getPercent($store_comment_score['store_desccredit'], $comparison['store_desccredit_avg']),
                'servicecredit_match' => $this->getPercent($store_comment_score['store_servicecredit'], $comparison['store_servicecredit_avg']),
                'deliverycredit_match' => $this->getPercent($store_comment_score['store_deliverycredit'], $comparison['store_deliverycredit_avg'])
            );
        }
        return $store_match;
    }


    public function getPercent($score, $avg)
    {
        if ($avg == 0) {
            return 100;
        } else {
            return round(($score - $avg) / $avg * 100, '2');
        }
    }


    /**
     * 获取用户收藏的店铺
     * @author dyr
     * @param $user_id
     * @param null $sc_id
     * @return mixed
     */
    public function getCollectStore($user_id,$sc_id=null)
    {
        if(!empty($sc_id)){
            $store_collect_where['s.sc_id'] = $sc_id;
        }
        $store_collect_where['sc.user_id'] = $user_id;
        $count = M('store_collect')->alias('sc')
                ->join('__STORE__ s','s.store_id = sc.store_id' , 'LEFT')
                ->where($store_collect_where)
                ->count();
        $page = new Page($count,10);
        $show = $page->show();
        if ($count === 0){
            $return['result'] = array();
            $return['show'] = $show;
            return $return;
        }
        $store_collect_list = Db::name('store_collect')
            ->alias('sc')
            ->field('sc.log_id,s.store_id,s.store_qq,s.store_name,s.store_logo,s.store_avatar,s.store_qq,s.store_desccredit,s.store_servicecredit,
            s.store_deliverycredit,r1.name as province_name,r2.name as city_name,r3.name as district_name,s.deleted as goods_array,s.store_collect')
            ->join('__STORE__ s','s.store_id = sc.store_id')
            ->join('__REGION__ r1','r1.id = s.province_id', 'LEFT')
            ->join('__REGION__ r2 ','r2.id = s.city_id', 'LEFT')
            ->join('__REGION__ r3 ','r3.id = s.district', 'LEFT')
            ->where($store_collect_where)
            ->order('sc.add_time DESC')
            ->limit($page->firstRow,$page->listRows)
            ->select();
        foreach($store_collect_list as $key=>$value){
            $store_collect_list[$key]['goods_array'] = D("store")->getStoreGoods($value['store_id'],3);
        }
        $return['result'] = $store_collect_list;
        $return['show'] = $show;
        return $return;
    }

    /**
     *
     * 店铺街
     * @param null $sc_id 分类id
     * @param int $province_id
     * @param int $city_id
     * @param null $order
     * @param int $item 记录条数
     * @return mixed
     */
    public function getStoreList($sc_id = null, $province_id = 0, $city_id = 0, $order = null, $item = 10)
    {
        $store_where = array('s.store_state' => 1);
        if (!empty($sc_id)) {
            $store_where['s.sc_id'] = $sc_id;
        }

        if (!empty($province_id)) {
            $store_where['s.province_id'] = $province_id;
        }

        if (!empty($city_id)) {
            $store_where['s.city_id'] = $city_id;
        }

        if($order){
            $orderBy['s.'.$order] = 'desc';
        }else{
            $orderBy = array('s.store_sort' => 'desc');
        }
        $store_count = M('store')->alias('s')->where($store_where)->count();
        $page = new Page($store_count, $item);
        $show = $page->show();
        $store_list = M('store')
            ->alias('s')
            ->field("s.store_id,s.store_qq,s.store_name,s.seo_description,s.store_logo,s.store_banner,s.store_aliwangwang,s.store_qq,s.store_desccredit,s.store_servicecredit,
            s.store_deliverycredit,r1.name as province_name,r2.name as city_name,r3.name as district_name,s.deleted as goods_array")
            ->join('__REGION__ r1 ',' r1.id = s.province_id' , 'LEFT')
            ->join('__REGION__ r2 ',' r2.id = s.city_id', 'LEFT')
            ->join('__REGION__ r3 ',' r3.id = s.district', 'LEFT')
            ->where($store_where)
            ->order($orderBy)
            ->limit($page->firstRow, $page->listRows)
            ->select();
        foreach ($store_list as $key => $value) {
            $store_list[$key]['goods_array'] = D("store")->getStoreGoods($value['store_id'], 4);
        }
        $return['result'] = $store_list;
        $return['show'] = $show;
        $return['pages'] = $page;
        return $return;
    }

    /**
     * 获取收藏商家数量
     * @param type $user_id
     * @param type $sc_id
     * @return type
     */
    public function getCollectNum($user_id, $sc_id=null)
    {
        if(!empty($sc_id)){
            $store_collect_where['s.sc_id'] = $sc_id;
        }
        $store_collect_where['sc.user_id'] = $user_id;
        $count = M('store_collect')->alias('sc')
                ->join('__STORE__ s','s.store_id = sc.store_id' , 'LEFT')
                ->where($store_collect_where)
                ->count();
        return $count;
    }
}