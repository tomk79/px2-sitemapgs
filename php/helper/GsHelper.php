<?php
/**
 * PX Plugin "sitemapGs"
 */
namespace tomk79\pickles2\sitemap_gs;

class pxplugin_sitemapGs_helper_GsHelper{

	/** Google Client */
	private $gclient;

	/** Google Spreadsheet Service */
	private $gs;

	/** Google Spreadsheet ID */
	private $spreadsheet_id;

	/**
	 * コンストラクタ
	 */
	public function __construct(){
		$this->gclient = new \Google_Client();
		$this->gclient->useApplicationDefaultCredentials();
		$this->gclient->addScope(\Google_Service_Sheets::SPREADSHEETS);
		$this->gclient->setApplicationName('px2-sitemapgs');

		$this->gs = new \Google_Service_Sheets($this->gclient);
	}

	/**
	 * 新規ファイルを作成
	 */
	public function create(){
		// 新規のファイルは作れない
		return false;
	}

	/**
	 * 既存のファイルを開く
	 */
	public function load( $path ){
		if(!strlen($path)){ return false; }
		if(!is_file($path)){ return false; }
		if(!is_readable($path)){ return false; }

		$file_bin = file_get_contents($path);
		$file_bin = trim($file_bin);
		$this->spreadsheet_id = $file_bin;


		// TODO: これはほんとはここじゃない
		// 値を取得
		// see: https://developers.google.com/sheets/api/reference/rest/
		$response = $this->gs->spreadsheets_values->get($this->spreadsheet_id, 'sitemap!A1:A2');
		foreach ($response->getValues() as $index => $cols) {
			echo sprintf('<pre><code>%d: &quot;%s&quot;</code></pre>', $index+1, implode('", "', $cols))."\n";
		}

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
