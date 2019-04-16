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
 * $Author: IT宇宙人 2015-08-10 $
 */ 
namespace app\home\controller;
use Think\File;

class Uploadify extends Base {

	public function upload(){
		$func = I('func');
		$path = I('path','temp');
		$info = array(
				'num'=> I('num/d'),
				'title' => '',
				'upload' =>U('Admin/Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'logo')),
				'size' => '4M',
				'type' =>'jpg,png,gif,jpeg',
				'input' => I('input'),
				'func' => empty($func) ? 'undefined' : $func,
		);
		$this->assign('info',$info);
		return $this->fetch();
	}
	
	/*
	 删除上传的图片
	*/
	public function delupload(){
		$action=isset($_GET['action']) ? $_GET['action'] : null;
		$filename= isset($_GET['filename']) ? $_GET['filename'] : null;
		$filename= str_replace('../','',$filename);
		$filename= trim($filename,'.');
		$filename= trim($filename,'/');
		if($action=='del' && !empty($filename)){
			$size = getimagesize($filename);
			$filetype = explode('/',$size['mime']);
			if($filetype[0]!='image'){
				return false;
				exit;
			}
			unlink($filename);
			exit;
		}
	}  
	/**上传图片* 
	* dirs 图片保存目录
	*
	*/

	public function img_upload($dirs,$name=null){
	    //获取表单上传文件 
	    if(empty($name))
	    	$file = request()->file();
	    else
	    	$file = request()->file($name);

	    if(empty($name)){
	    	//不同名称多字段单张上传
	    	foreach ($file as $key => $val) {
	    		 $info = $file[$key]->move(ROOT_PATH . 'public' . DS . 'upload/'. $dirs); 
	    		 $nameArr[$key]='/public/upload/'.$dirs.'/'.$info->getSaveName(); 
	    	}
	    }else{
	    	if(is_array($file)){
	    		//带名称多张图片上传
	    		foreach ($file as $key => $val) {
	    			$info = $file[$key]->move(ROOT_PATH . 'public' . DS . 'upload/'. $dirs); 
	    		    $nameArr[$key]='/public/upload/'.$dirs.'/'.$info->getSaveName(); 
	    		}

	    	}else{	
	    		//带名称单张图片上传
		    	$info = $file->move(ROOT_PATH . 'public' . DS . 'upload/'. $dirs); 
		    	$nameArr[$name]='/public/upload/'.$dirs.'/'.$info->getSaveName(); 
	    	}

	    }
	    //移动到框架应用根目录/public/uploads/ 目录下 
	    if ($info) { 
	     return  $nameArr; 
	    } else { 
	      //上传失败获取错误信息 
	       return  $file->getError(); 
	    }
	}

	/**上传图片* 
	* dirs 图片保存目录
	*
	*/

	public function oss_upload($dirs,$name=null,$oldurl=null){
	    //获取表单上传文件 
	    $file = request()->file();
	    $allowsize=1024*1024*4;
	    if(empty($name)){
	    	//不同名称多字段单张上传
	    	foreach ($file as $key => $val) {
	    		if($val->checkSize($allowsize)){
	    			 $info[$key] = $val->fileup($val,$dirs,$oldurl); 
	    		}else{
	    			return false;
	    		}
		    }
	    }else{
	    	if(is_array($file[$name])){
	    		//带名称多张图片上传
	    		foreach ($file[$name] as $key => $val) {
		    		if($val->checkSize($allowsize)){
		    			$info[$key] = $val->fileup($val,$dirs,$oldurl); 
		    		}else{
		    			return false;
		    		}
	    			
	    		}

	    	}else{	
	    		//带名称单张图片上传
	    			if($file[$name]->checkSize($allowsize)){
		    			//$info[$key] = $val->fileup($val,$dirs); 
		    			$info[$name] = $file[$name]->fileup($file[$name],$dirs,$oldurl); 
		    		}else{
		    			return false;
		    		}
	    		
	    	}

	    }
	    //移动到框架应用根目录/public/uploads/ 目录下 
	    if ($info) { 
	     return  $info; 
	    } else { 
	      //上传失败获取错误信息 
	       return  $file->getError(); 
	    }
	}

}