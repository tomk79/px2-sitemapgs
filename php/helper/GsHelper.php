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
	 * Google Spreadsheet Service
	 */
	public function gs(){
		return $this->gs;
	}

	/**
	 * 既存のファイルから、Spreadsheet ID を読み取る
	 */
	public function load( $path ){
		if(!strlen($path)){ return false; }
		if(!is_file($path)){ return false; }
		if(!is_readable($path)){ return false; }

		$file_bin = file_get_contents($path);
		$file_bin = trim($file_bin); // TODO: この仕様ではないかもしれない。要調査
		$spreadsheet_id = $file_bin;

		return $spreadsheet_id;
	}

}
