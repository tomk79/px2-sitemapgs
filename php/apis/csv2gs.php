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
		$spreadsheet_id = $helper->load($path_spreadsheet);
		$this->plugin->msg( 'Spreadsheet ID: '.$spreadsheet_id );
		// var_dump($spreadsheet_id);


		$this->plugin->msg( 'Creating new sheet...' );
		$requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
			'requests' => array(
				'addSheet'=>array(
					'properties'=>array(
						'title' => 'sitemap-'.date('Ymd-His'),
						'index' => 0,
						'sheetType' => 'GRID',
						'gridProperties' => array(
							'columnCount' => ( count($table_definition['col_define']) + $this->get_max_depth() + 3 ),
						),
						'hidden' => false,
					)
				)
			)
		));
		$response = $helper->gs()->spreadsheets->batchUpdate($spreadsheet_id, $requestBody);
		$newSheetProperties = $response->replies[0]['addSheet']['properties'];
		// var_dump($newSheetProperties['sheetId']);
		$this->plugin->msg( 'new sheet id: '.$newSheetProperties['sheetId'] );



		$this->plugin->msg( 'removing rows...' );
		$requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
			'requests' => array(
				'deleteDimension'=>array(
					'range'=>array(
						'sheetId' => $newSheetProperties['sheetId'],
						'dimension' => 'ROWS',
						'startIndex' => 1,
						// 'endIndex' => 1000,
					)
				)
			)
		));
		$response = $helper->gs()->spreadsheets->batchUpdate($spreadsheet_id, $requestBody);
		$this->plugin->msg( 'DONE.' );



		$this->default_cell_style_boarder = array(// 罫線の一括指定
			'top'     => array('style' => 'SOLID'),
			'bottom'  => array('style' => 'SOLID'),
			'left'    => array('style' => 'SOLID'),
			'right'   => array('style' => 'SOLID'),
		);

		$rows = array();

		// 設定セル
		$tmp_row = array('values'=>array());
		$col = -1;
		foreach( $table_definition['col_define'] as $def_row ){
			$col ++;
			array_push($tmp_row['values'], array(
				'userEnteredValue'=>array(
					'stringValue'=>($col ? '' : $this->mk_config_string())
				),
				'userEnteredFormat'=>array(
					'backgroundColor'=>array('red'=>0,'green'=>0,'blue'=>0,'alpha'=>0), // black
					'textFormat'=>array(
						'fontSize'=>6,
					),
				),
			));

			// title列の整形
			if( $def_row['key'] == 'title' ){
				$tmp_col = $def_row['col'];
				for($i = 0; $i < $this->get_max_depth(); $i ++){
					$tmp_col ++;
					array_push($tmp_row['values'], array(
						'userEnteredValue'=>array(
							'stringValue'=>''
						),
						'userEnteredFormat'=>array(
							'backgroundColor'=>array('red'=>0,'green'=>0,'blue'=>0,'alpha'=>0), // black
							'textFormat'=>array(
								'fontSize'=>6,
							),
						),
					));
				}
				unset($tmp_col);
			}
		}
		array_push($rows, $tmp_row);
		array_push($rows, array('values' => array('userEnteredValue'=>array('stringValue'=>''))));

		// シートタイトルセル
		array_push($rows, array(
			'values' => array(
				array(
					'userEnteredValue'=>array('stringValue'=>'「'.$this->px->conf()->name.'」 サイトマップ'),
					'userEnteredFormat'=>array(
						'textFormat'=>array(
							'fontSize'=>24,
							'bold'=>true,
						),
					),
				),
			)
		));
		array_push($rows, array(
			'values' => array(
				array(
					'userEnteredValue'=>array('stringValue'=>'Exported: '.@date('Y-m-d H:i:s', filemtime($this->path_csv))),
					'userEnteredFormat'=>array(
						'textFormat'=>array(
							'fontSize'=>10,
						),
					),
				),
			)
		));
		array_push($rows, array('values' => array('userEnteredValue'=>array('stringValue'=>''))));
		array_push($rows, array('values' => array('userEnteredValue'=>array('stringValue'=>''))));


		// 定義行
		$tmp_row = array('values'=>array());
		$col = -1;
		foreach( $table_definition['col_define'] as $def_row ){
			$col ++;

			// 論理名
			array_push($tmp_row['values'], array(
				'userEnteredValue'=>array(
					'stringValue'=>$def_row['name']
				),
				'userEnteredFormat'=>array(
					'backgroundColor'=>array('red'=>0.8,'green'=>0.8,'blue'=>0.8,'alpha'=>0), // #cccccc
					'borders'=>$this->default_cell_style_boarder,
				),
			));

			// title列の整形
			if( $def_row['key'] == 'title' ){
				$tmp_col = $def_row['col'];
				for($i = 0; $i < $this->get_max_depth(); $i ++){
					$tmp_col ++;
					array_push($tmp_row['values'], array(
						'userEnteredValue'=>array(
							'stringValue'=>''
						),
						'userEnteredFormat'=>array(
							'backgroundColor'=>array('red'=>0.8,'green'=>0.8,'blue'=>0.8,'alpha'=>0), // #cccccc
							'borders'=>$this->default_cell_style_boarder,
						),
					));
				}
				unset($tmp_col);
			}

		}
		array_push($rows, $tmp_row);


		// 物理名
		$tmp_row = array('values'=>array());
		$col = -1;
		foreach( $table_definition['col_define'] as $def_row ){
			$col ++;

			// 論理名
			array_push($tmp_row['values'], array(
				'userEnteredValue'=>array(
					'stringValue'=>$def_row['key']
				),
				'userEnteredFormat'=>array(
					'backgroundColor'=>array('red'=>0.87,'green'=>0.87,'blue'=>0.87,'alpha'=>0), // #dddddd
					'borders'=>$this->default_cell_style_boarder,
				),
			));

			// title列の整形
			if( $def_row['key'] == 'title' ){
				$tmp_col = $def_row['col'];
				for($i = 0; $i < $this->get_max_depth(); $i ++){
					$tmp_col ++;
					array_push($tmp_row['values'], array(
						'userEnteredValue'=>array(
							'stringValue'=>''
						),
						'userEnteredFormat'=>array(
							'backgroundColor'=>array('red'=>0.87,'green'=>0.87,'blue'=>0.87,'alpha'=>0), // #dddddd
							'borders'=>$this->default_cell_style_boarder,
						),
					));
					// $objSheet->getStyle(($tmp_col).$this->current_row)->applyFromArray( $this->default_cell_style_boarder );
				}
				// $objSheet->mergeCells($cellName.':'.($tmp_col).$this->current_row);
				unset($tmp_col);
			}

		}
		array_push($rows, $tmp_row);



		// データ行を作成する
		$gsheet_body_rows = $this->mk_gsheet_body();
		foreach( $gsheet_body_rows as $gsheet_body_row ){
			array_push( $rows, array('values'=>$gsheet_body_row) );
		}


		// データ行の終了を宣言
		array_push($rows, array('values' => array('userEnteredValue'=>array('stringValue'=>''))));
		array_push($rows, array('values' => array('userEnteredValue'=>array('stringValue'=>''))));

		$tmp_row = array('values'=>array());
		$col = -1;
		foreach( $table_definition['col_define'] as $def_row ){
			$col ++;

			array_push($tmp_row['values'], array(
				'userEnteredValue'=>array(
					'stringValue'=>($col ? '' : 'EndOfData')
				),
				'userEnteredFormat'=>array(
					'backgroundColor'=>array('red'=>0.87,'green'=>0.87,'blue'=>0.87,'alpha'=>0), // #dddddd
					'textFormat'=>array(
						'fontSize'=>8,
					),
				),
			));

			// title列の整形
			if( $def_row['key'] == 'title' ){
				$tmp_col = $def_row['col'];
				for($i = 0; $i < $this->get_max_depth(); $i ++){
					$tmp_col ++;
					array_push($tmp_row['values'], array(
						'userEnteredValue'=>array(
							'stringValue'=>''
						),
						'userEnteredFormat'=>array(
							'backgroundColor'=>array('red'=>0.87,'green'=>0.87,'blue'=>0.87,'alpha'=>0), // #dddddd
							'textFormat'=>array(
								'fontSize'=>8,
							),
						),
					));
				}
				unset($tmp_col);
			}

		}
		array_push($rows, $tmp_row);
		array_push($rows, array('values' => array('userEnteredValue'=>array('stringValue'=>''))));



		// Google Spreadsheet に書き込む
		$requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
			'requests' => array(
				'appendCells'=>array(
					'sheetId' => $newSheetProperties['sheetId'],
					'rows' => $rows,
					'fields' => '*',
				)
			)
		));
		$response = $helper->gs()->spreadsheets->batchUpdate($spreadsheet_id, $requestBody);

		// titleの定義セルを統合
		$requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
			'requests' => array(
				// 論理定義行
				array(
					'mergeCells'=>array(
						'range' => array(
							'sheetId' => $newSheetProperties['sheetId'],
							'startRowIndex' => 6,
							'endRowIndex' => 7,
							'startColumnIndex' => 1,
							'endColumnIndex' => 1 + $this->get_max_depth() + 1,
						),
						'mergeType' => 'MERGE_ALL',
					)
				),
				// 物理定義行
				array(
					'mergeCells'=>array(
						'range' => array(
							'sheetId' => $newSheetProperties['sheetId'],
							'startRowIndex' => 7,
							'endRowIndex' => 8,
							'startColumnIndex' => 1,
							'endColumnIndex' => 1 + $this->get_max_depth() + 1,
						),
						'mergeType' => 'MERGE_ALL',
					)
				),
			)
		));
		$response = $helper->gs()->spreadsheets->batchUpdate($spreadsheet_id, $requestBody);

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
	 * サイトマップをスキャンして、Google Spreadsheet のデータ部分を作成する
	 */
	private function mk_gsheet_body($page_id = ''){
		$rows = array();
		if(!is_string($page_id)){return $rows;}
		$sitemap_definition = $this->get_sitemap_definition();
		$table_definition = $this->get_table_definition();
		// var_dump($this->site->get_sitemap());
		$page_info = $this->site->get_page_info($page_id);
		if(!is_array($page_info)){
			 return $rows;
		}
		// var_dump($page_id);
		// var_dump($page_info);

		set_time_limit(30);

		$tmp_row = array();
		foreach( $table_definition['col_define'] as $def_row ){
			$cellValue = @$page_info[$def_row['key']];
			if(is_null($cellValue)){$cellValue = '';}
			$cellStyle = array(
				'borders'=>$this->default_cell_style_boarder,
				'textFormat'=>array(
					'fontSize'=>10,
				),
			);

			switch($def_row['key']){
				case 'title_h1':
				case 'title_label':
				case 'title_breadcrumb':
					if($cellValue == $page_info['title']){
						$cellValue = '';
					}
					array_push($tmp_row, array(
						'userEnteredValue'=>array(
							'stringValue'=>$cellValue,
						),
						'userEnteredFormat'=>$cellStyle,
					));
					break;
				case 'title':
					// 罫線を引く
					$tmp_depth = 0;
					if( !strlen($page_info['id']) ){
						// トップページには細工をしない
					}elseif( !strlen($page_info['logical_path']) ){
						// トップページ以外でパンくず欄が空白のものは、
						// 第2階層
						$tmp_depth = 1;
					}else{
						$tmp_breadcrumb = explode('>',$page_info['logical_path']);
						for($i = 0; $i <= count($tmp_breadcrumb); $i ++ ){
							$tmp_depth ++;
						}
					}
					for($i = 0; $i <= $this->get_max_depth(); $i ++ ){
						array_push($tmp_row, array(
							'userEnteredValue'=>array(
								'stringValue'=>($tmp_depth==$i ? $cellValue : ''),
							),
							'userEnteredFormat'=>$cellStyle,
						));
						$tmp_col ++;
					}
					unset($tmp_depth);

					break;
				case 'content':
					if($cellValue == $page_info['path']){
						$cellValue = '';
					}

					array_push($tmp_row, array(
						'userEnteredValue'=>array(
							'stringValue'=>$cellValue,
						),
						'userEnteredFormat'=>$cellStyle,
					));
					break;
				case 'path':
					$cellValue = $this->repair_path($cellValue);

					array_push($tmp_row, array(
						'userEnteredValue'=>array(
							'stringValue'=>$cellValue,
						),
						'userEnteredFormat'=>$cellStyle,
					));
					break;
				case 'id':
					$cellValue = $this->repair_page_id($cellValue, $page_info['path']);
					array_push($tmp_row, array(
						'userEnteredValue'=>array(
							'stringValue'=>$cellValue,
						),
						'userEnteredFormat'=>$cellStyle,
					));
					break;
				case 'keywords':
				case 'description':
					// フォントサイズ
					$cellStyle['textFormat']['fontSize'] = 9;

					array_push($tmp_row, array(
						'userEnteredValue'=>array(
							'stringValue'=>$cellValue,
						),
						'userEnteredFormat'=>$cellStyle,
					));
					break;
				default:
					array_push($tmp_row, array(
						'userEnteredValue'=>array(
							'stringValue'=>$cellValue,
						),
						'userEnteredFormat'=>$cellStyle,
					));
					break;
			}

		}
		array_push($rows, $tmp_row);

		$children = $this->site->get_children($page_id, array('filter'=>false));
		// var_dump($children);
		foreach( $children as $child ){
			$page_info = $this->site->get_page_info($child);
			if(!strlen($page_info['id'])){
				$this->px->error('ページIDがセットされていません。');
				continue;
			}
			$rows = array_merge($rows, $this->mk_gsheet_body($page_info['id']));
		}
		return $rows;
	}// mk_gsheet_body()

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
