<?php
/**
 * PX Plugin "sitemapGs" export
 */
namespace tomk79\pickles2\sitemap_gs;

/**
 * PX Plugin "sitemapGs" export
 */
class csv2gs{

	/** Picklesオブジェクト */
	private $px;
	/** sitemapGsオブジェクト */
	private $plugin;

	private $path_spreadsheet, $path_csv;
	private $site;
	private $default_cell_style_boarder = array();// 罫線の一括指定
	private $current_row = 1;
	private $current_col = 'A';


	/**
	 * constructor
	 * @param object $px Picklesオブジェクト
	 * @param object $plugin プラグインオブジェクト
	 */
	public function __construct( $px, $plugin ){
		$this->px = $px;
		$this->plugin = $plugin;
	}

	/**
	 * CSVのパスを取得
	 */
	private function get_realpath_csv(){
		return $this->path_csv;
	}
	/**
	 * 出力先 Google Spreadsheet のパスを取得
	 */
	private function get_path_spreadsheet(){
		return $this->path_spreadsheet;
	}

	/**
	 * 現在のサイトマップを Google Spreadsheet に出力する。
	 */
	public function convert( $path_csv, $path_spreadsheet ){
		$this->path_spreadsheet = $path_spreadsheet;
		$this->path_csv = $path_csv;

		// ↓疑似サイトマップオブジェクト
		// 　sitemapGs実行時点で、
		// 　本物の$siteはスタンバイされていないので、
		// 　偽物でエミュレートする必要があった。
		$this->site = new pxplugin_sitemapGs_helper_parseSitemapCsv( $this->px, $this->path_csv );

		$table_definition = $this->get_table_definition();

		$helper = new pxplugin_sitemapGs_helper_GsHelper();
		if( !$helper ){
			return false;
		}
		$objPHPExcel = $helper->create();

		$objPHPExcel->setActiveSheetIndex(0);
		$objSheet = $objPHPExcel->getActiveSheet();

		// フォント
		$objSheet->getDefaultStyle()->getFont()->setName('メイリオ');

		// フォントサイズ
		$objSheet->getDefaultStyle()->getFont()->setSize(12);

		// 背景色指定(準備)
		$objSheet->getDefaultStyle()->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);

		// ウィンドウ枠を固定
		$objSheet->freezePane('B'.$table_definition['row_data_start']);

		$this->default_cell_style_boarder = array(// 罫線の一括指定
			'borders' => array(
				'top'     => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				'bottom'  => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				'left'    => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				'right'   => array('style' => \PHPExcel_Style_Border::BORDER_THIN)
			)
		);

		// 設定セル
		$this->current_row = 1;
		$objSheet->getCell('A'.$this->current_row)->setValue( $this->mk_config_string() );
		$maxCol = 'A';
		foreach( $table_definition['col_define'] as $col ){
			if($maxCol < $col['col']){ $maxCol = $col['col']; }
		}
		$mainColor = preg_replace( '/^\#/', '', '#333333' );
		for( $col = 'A'; $col <= $maxCol; $col ++ ){
			$objSheet->getStyle($col.$this->current_row)->getFill()->getStartColor()->setRGB( $mainColor );
			$objSheet->getStyle($col.$this->current_row)->getFont()->getColor()->setRGB( $mainColor );
			$objSheet->getStyle($col.$this->current_row)->getFont()->setSize(8);
		}
		$objSheet->getRowDimension($this->current_row)->setRowHeight(10);

		$this->current_row ++;
		$this->current_row ++;

		// シートタイトルセル
		$sheetTitle = '「'.$this->px->conf()->name.'」 サイトマップ';
		$objSheet->setTitle('sitemap');//←文字数制限がある。超えると落ちる。
		$objSheet->getCell('A'.$this->current_row)->setValue($sheetTitle);
		$objSheet->getStyle('A'.$this->current_row)->getFont()->setSize(24);
		$this->current_row ++;
		$objSheet->getCell('A'.$this->current_row)->setValue('Exported: '.@date('Y-m-d H:i:s', filemtime($this->path_csv)));

		// 定義行
		$this->current_row = $table_definition['row_definition'] - 1;
		foreach( $table_definition['col_define'] as $def_row ){
			// 論理名
			$cellName = ($def_row['col']).$this->current_row;
			$objSheet->getCell($cellName)->setValue($def_row['name']);
			$objSheet->getStyle($cellName)->getFill()->getStartColor()->setRGB('cccccc');

			// 罫線の一括指定
			$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );

			// title列の整形
			if( $def_row['key'] == 'title' ){
				$tmp_col = $def_row['col'];
				for($i = 0; $i < $this->get_max_depth(); $i ++){
					$tmp_col ++;
					$objSheet->getStyle(($tmp_col).$this->current_row)->applyFromArray( $this->default_cell_style_boarder );
				}
				$objSheet->mergeCells($cellName.':'.($tmp_col).$this->current_row);
				unset($tmp_col);
			}

		}
		$this->current_row ++;
		foreach( $table_definition['col_define'] as $def_row ){
			// 物理名
			$cellName = ($def_row['col']).$this->current_row;
			$objSheet->getCell($cellName)->setValue($def_row['key']);
			$objSheet->getStyle($cellName)->getFill()->getStartColor()->setRGB('dddddd');

			// 罫線の一括指定
			$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );

			// title列の整形
			if( $def_row['key'] == 'title' ){
				$tmp_col = $def_row['col'];
				for($i = 0; $i < $this->get_max_depth(); $i ++){
					$tmp_col ++;
					$objSheet->getStyle(($tmp_col).$this->current_row)->applyFromArray( $this->default_cell_style_boarder );
				}
				$objSheet->mergeCells($cellName.':'.($tmp_col).$this->current_row);
				unset($tmp_col);
			}

		}


		//セルの幅設定
		$objSheet->getColumnDimension($table_definition['col_define']['id']['col'])->setWidth(8);
		$objSheet->getColumnDimension($table_definition['col_define']['title']['col'])->setWidth(3);
		$tmp_col = $table_definition['col_define']['title']['col'];
		for($i = 0; $i < $this->get_max_depth(); $i ++){
			$tmp_col ++;
			if( $i+1 == $this->get_max_depth() ){
				$objSheet->getColumnDimension($tmp_col)->setWidth(20);
			}else{
				$objSheet->getColumnDimension($tmp_col)->setWidth(3);
			}
		}
		$objSheet->getColumnDimension(@$table_definition['col_define']['title_h1']['col'])->setWidth(2);
		$objSheet->getColumnDimension(@$table_definition['col_define']['title_label']['col'])->setWidth(2);
		$objSheet->getColumnDimension(@$table_definition['col_define']['title_breadcrumb']['col'])->setWidth(2);
		$objSheet->getColumnDimension(@$table_definition['col_define']['title_full']['col'])->setWidth(2);
		$objSheet->getColumnDimension(@$table_definition['col_define']['path']['col'])->setWidth(40);
		$objSheet->getColumnDimension(@$table_definition['col_define']['content']['col'])->setWidth(20);
		$objSheet->getColumnDimension(@$table_definition['col_define']['list_flg']['col'])->setWidth(3);
		$objSheet->getColumnDimension(@$table_definition['col_define']['layout']['col'])->setWidth(9);
		// $objSheet->getColumnDimension(@$table_definition['col_define']['extension']['col'])->setWidth(9);
		$objSheet->getColumnDimension(@$table_definition['col_define']['description']['col'])->setWidth(30);
		$objSheet->getColumnDimension(@$table_definition['col_define']['keywords']['col'])->setWidth(30);
		// $objSheet->getColumnDimension(@$table_definition['col_define']['auth_level']['col'])->setWidth(3);
		$objSheet->getColumnDimension(@$table_definition['col_define']['orderby']['col'])->setWidth(3);
		$objSheet->getColumnDimension(@$table_definition['col_define']['category_top_flg']['col'])->setWidth(3);

		// 行移動
		$this->current_row = $table_definition['row_data_start'];

		// データ行を作成する
		// var_dump( $this->site->get_sitemap() );
		$this->mk_xlsx_body($objSheet);

		// データ行の終了を宣言
		$this->current_row ++;
		$this->current_row ++;
		$objSheet->getCell('A'.$this->current_row)->setValue( 'EndOfData' );
		for( $col = 'A'; $col <= $maxCol; $col ++ ){
			$objSheet->getStyle($col.$this->current_row)->getFill()->getStartColor()->setRGB( 'dddddd' );
			$objSheet->getStyle($col.$this->current_row)->getFont()->setSize(8);
		}
		$objSheet->getRowDimension($this->current_row)->setRowHeight(5);
		$this->current_row ++;



		$objPHPExcel->setActiveSheetIndex(0);//メインのセルを選択しなおし。

		$helper->save($objPHPExcel, $path_spreadsheet, 'Excel2007');

		clearstatcache();

		return $this;
	}// convert()

	/**
	 * 設定文字列を作成する
	 */
	private function mk_config_string(){
		$config = array();
		$table_definition = $this->get_table_definition();
		foreach( $table_definition as $key=>$val ){
			if( $key == 'col_define' ){ continue; }
			array_push( $config, urlencode($key).'='.urlencode($val) );
		}

		// sitemapGsのバージョン情報を記載
		array_push( $config, 'version='.urlencode( $this->plugin->get_version() ) );

		$rtn = implode('&', $config);
		return $rtn;
	}

	/**
	 * パンくずの最大の深さを計測
	 */
	private function get_max_depth(){
		static $max_depth = null;
		if( is_int($max_depth) ){
			return $max_depth;
		}

		$max_depth = 0;
		foreach( $this->site->get_sitemap() as $page_info ){
			$tmp_breadcrumb = explode('>',$page_info['logical_path']);
			if( $max_depth < count($tmp_breadcrumb) ){
				$max_depth = count($tmp_breadcrumb);
			}
		}
		$max_depth += 3;//ちょっぴり余裕を
		return $max_depth;
	}

	/**
	 * サイトマップをスキャンして、xlsxのデータ部分を作成する
	 */
	private function mk_xlsx_body($objSheet, $page_id = ''){
		if(!is_string($page_id)){return false;}
		$sitemap_definition = $this->get_sitemap_definition();
		$table_definition = $this->get_table_definition();
		// var_dump($this->site->get_sitemap());
		$page_info = $this->site->get_page_info($page_id);
		if(!is_array($page_info)){
			return false;
		}
		// var_dump($page_id);
		// var_dump($page_info);

		set_time_limit(30);

		foreach( $table_definition['col_define'] as $def_row ){
			$cellName = ($def_row['col']).$this->current_row;
			$cellValue = @$page_info[$def_row['key']];
			switch($def_row['key']){
				case 'title_h1':
				case 'title_label':
				case 'title_breadcrumb':
					if($cellValue == $page_info['title']){
						$cellValue = '';
					}
					$objSheet->getCell($cellName)->setValue($cellValue);

					// 罫線の一括指定
					$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );
					break;
				case 'title':
					// 罫線を引く
					$tmp_col = $def_row['col'];
					for($i = 0; $i <= $this->get_max_depth(); $i ++ ){
						$tmp_border_style = array(
							'borders' => array(
								'top'     => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
								'bottom'  => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
								'left'    => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
								'right'   => array('style' => \PHPExcel_Style_Border::BORDER_THIN, 'color'=>array('rgb'=>'dddddd')),
							)
						);
						if($i != 0){
							$tmp_border_style['borders']['left']['style'] = \PHPExcel_Style_Border::BORDER_THIN;
							$tmp_border_style['borders']['left']['color'] = array('rgb'=>'dddddd');
						}
						if($i == $this->get_max_depth()){
							$tmp_border_style['borders']['right']['style'] = \PHPExcel_Style_Border::BORDER_THIN;
						}
						$objSheet->getStyle($tmp_col.$this->current_row)->applyFromArray( $tmp_border_style );
						$tmp_col ++;
					}
					unset($tmp_col);

					if( !strlen($page_info['id']) ){
						// トップページには細工をしない
					}elseif( !strlen($page_info['logical_path']) ){
						// トップページ以外でパンくず欄が空白のものは、
						// 第2階層
						$def_row['col'] ++;
					}else{
						$tmp_breadcrumb = explode('>',$page_info['logical_path']);
						for($i = 0; $i <= count($tmp_breadcrumb); $i ++ ){
							$def_row['col'] ++;
						}
					}
					$cellName = ($def_row['col']).$this->current_row;

					$objSheet->getCell($cellName)->setValue($cellValue);
					$objSheet->getStyle($cellName)->applyFromArray( array('borders'=>array(
						'left'=>array( 'color'=>array('rgb'=>'666666') ) ,
					)) );

					// 罫線の一括指定
					// $objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );
					break;
				case 'content':
					if($cellValue == $page_info['path']){
						$cellValue = '';
					}
					$objSheet->getCell($cellName)->setValue($cellValue);

					// 罫線の一括指定
					$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );
					break;
				case 'path':
					$objSheet->getCell($cellName)->setValue($this->repair_path($cellValue));

					// 罫線の一括指定
					$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );
					break;
				case 'id':
					$objSheet->getCell($cellName)->setValue($this->repair_page_id($cellValue, $page_info['path']));

					// 罫線の一括指定
					$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );
					break;
				case 'keywords':
				case 'description':
					$objSheet->getCell($cellName)->setValue($cellValue);

					// フォントサイズ
					$objSheet->getStyle($cellName)->getFont()->setSize(9);

					// 罫線の一括指定
					$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );
					break;
				default:
					$objSheet->getCell($cellName)->setValue($cellValue);

					// 罫線の一括指定
					$objSheet->getStyle($cellName)->applyFromArray( $this->default_cell_style_boarder );
					break;
			}
		}
		$this->current_row ++;

		$children = $this->site->get_children($page_id, array('filter'=>false));
		// var_dump($children);
		foreach( $children as $child ){
			$page_info = $this->site->get_page_info($child);
			if(!strlen($page_info['id'])){
				$this->px->error('ページIDがセットされていません。');
				continue;
			}
			$this->mk_xlsx_body($objSheet, $page_info['id']);
		}
		return true;
	}// mk_xlsx_body()

	/**
	 * 加工されたパスを戻す
	 */
	private function repair_path($path){
		$tmp_path = $path;
		$tmp_path = preg_replace('/^alias[0-9]*\:/si','alias:',$tmp_path);
		$tmp_path = preg_replace('/^alias\:([a-zA-Z0-9\+]+\:|\/\/)/si','$1',$tmp_path);
		$tmp_path = preg_replace('/^alias\:\#/si','#',$tmp_path);
		switch( $this->px->get_path_type($tmp_path) ){
			case 'full_url':
			case 'javascript':
			case 'anchor':
				break;
			default:
				$tmp_path = preg_replace('/\/'.$this->px->get_directory_index_preg_pattern().'((?:\?|\#).*)?$/s', '/$1', $tmp_path);
				break;
		}
		$path = $tmp_path;
		return $path;
	}

	/**
	 * 加工されたページIDを戻す
	 */
	private function repair_page_id($page_id, $path){
		$page_id = preg_replace('/^\:auto_page_id\.[0-9]+$/si', '', $page_id);
		$tmp_path = $path;
		$tmp_path = preg_replace('/\/'.$this->px->get_directory_index_preg_pattern().'$/si', '/', $tmp_path);
		$tmp_path = preg_replace('/\.(?:html)$/si', '', $tmp_path);
		$tmp_path = preg_replace('/^\/+/si', '', $tmp_path);
		$tmp_path = preg_replace('/\/+$/si', '', $tmp_path);
		$tmp_path = preg_replace('/\//si', '.', $tmp_path);
		if($tmp_path == $page_id){
			$page_id = '';
		}
		return $page_id;
	}

	/**
	 * 表の構造定義を得る
	 */
	private function get_table_definition(){
		static $rtn = null;
		if(is_array($rtn)){ return $rtn; }

		$rtn = array();
		$rtn['row_definition'] = 8;
		$rtn['row_data_start'] = $rtn['row_definition']+1;
		$rtn['skip_empty_col'] = 20;
		$rtn['col_define'] = array();

		$current_col = 'A';

		$rtn['col_define']['id'] = array( 'col'=>($current_col++) );
		$rtn['col_define']['title'] = array( 'col'=>($current_col++) );
		for($i = 0; $i<$this->get_max_depth(); $i++){
			$current_col++;
		}
		$rtn['col_define']['title_h1'] = array( 'col'=>($current_col++) );
		$rtn['col_define']['title_label'] = array( 'col'=>($current_col++) );
		$rtn['col_define']['title_breadcrumb'] = array( 'col'=>($current_col++) );
		$rtn['col_define']['title_full'] = array( 'col'=>($current_col++) );

		$sitemap_definition = $this->get_sitemap_definition();
		foreach($sitemap_definition as $def_row){
			if($def_row['key'] == 'logical_path'){continue;}

			$rtn['col_define'][$def_row['key']]['name'] = $def_row['name'];
			$rtn['col_define'][$def_row['key']]['key'] = $def_row['key'];

			if(strlen(@$rtn['col_define'][$def_row['key']]['col'])){continue;}
			$rtn['col_define'][$def_row['key']]['col'] = ($current_col++);
		}

		return $rtn;
	}

	/**
	 * サイトマップ定義を取得する
	 */
	private function get_sitemap_definition(){
		// $rtn = $this->site->get_sitemap_definition();
		$rtn = $this->plugin->get_sitemap_definition();

		if( !is_array(@$rtn['**delete_flg']) ){
			$rtn['**delete_flg'] = array();
			$rtn['**delete_flg']['name'] = '削除フラグ';
			$rtn['**delete_flg']['key'] = '**delete_flg';
		}

		$pageInfo = $this->site->get_page_info('');
		foreach( $rtn as $key=>$val ){
			unset($pageInfo[$key]);
		}
		unset($pageInfo['**delete_flg']);
		foreach( array_keys($pageInfo) as $key ){
			$rtn[$key]['key'] = $key;
			$rtn[$key]['name'] = $key;
		}

		return $rtn;
	}

}
