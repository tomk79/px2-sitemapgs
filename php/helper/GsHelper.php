<?php
/**
 * PX Plugin "sitemapGs"
 */
namespace tomk79\pickles2\sitemap_gs;

class pxplugin_sitemapGs_helper_GsHelper{

	/**
	 * コンストラクタ
	 */
	public function __construct(){
	}

	/**
	 * 新規ファイルを作成
	 */
	public function create(){
		$objPHPExcel = new \PHPExcel();
		return $objPHPExcel;
	}

	/**
	 * 既存のファイルを開く
	 */
	public function load( $path ){
		if(!strlen($path)){ return false; }
		if(!is_file($path)){ return false; }
		if(!is_readable($path)){ return false; }

		$objPHPExcel = \PHPExcel_IOFactory::load($path);
		return $objPHPExcel;
	}

	/**
	 * 保存する
	 */
	public function save( $objPHPExcel, $path, $type = 'Excel2007' ){
		$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, $type);
		return $objWriter->save($path);
	}

}
