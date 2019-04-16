<?php


namespace app\seller\controller;

use think\AjaxPage;
use think\Page;
use app\mobile\model\goods;

class Test extends Base
{
	
	public function info(){
		$id = I('get.id',0);
		$goodsModel = new goods();
		$rs = $goodsModel->getGoodsInfo($id);
		halt($rs);
	}
    
}