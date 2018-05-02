<?php
/**
 * test for tomk79\px2-sitemapgs
 */

class mainTest extends PHPUnit_Framework_TestCase{

	/**
	 * サイトマップディレクトリのパス
	 */
	private $path_sitemaps;

	/**
	 * テスト用のファイル更新日タイムスタンプ
	 */
	private $test_timestamp;

	/**
	 * ファイルシステムユーティリティ
	 */
	private $fs;

	/**
	 * テストヘルパー
	 */
	private $testhelper;

	/**
	 * Google Spreadsheet ヘルパー
	 */
	private $gshelper;

	/**
	 * setup
	 */
	public function setup(){
		require_once(__DIR__.'/helper/testhelper.php');
		$this->testhelper = new testhelper();
		$this->test_timestamp = @mktime(0, 0, 0, 1, 1, 2000);
		$this->path_sitemap = __DIR__.'/testdata/standard/px-files/sitemaps/';
		$this->fs = new \tomk79\filesystem();
		mb_internal_encoding('utf-8');
		@date_default_timezone_set('Asia/Tokyo');

		// gsheetファイルを読み込む
		$this->gshelper = $this->testhelper->create_gshelper();

		// テストに使用する .gsheet ファイルを作成
		$this->fs->copy( __DIR__.'/testdata/data/sitemap.gsheet', __DIR__.'/testdata/standard/px-files/sitemaps/sitemap.gsheet' );
	}

	/**
	 * google spreadsheet to .csv 変換のテスト
	 */
	public function testCsv2GsConvert(){

		$this->fs->copy( __DIR__.'/testdata/data/sample001/sitemap.csv', __DIR__.'/testdata/standard/px-files/sitemaps/sitemap.csv' );

		// Google スプレッドシートへ出力
		$output = $this->testhelper->passthru( [
			'php',
			__DIR__.'/testdata/standard/.px_execute.php' ,
			'/?PX=sitemapgs.push' ,
		] );
		// var_dump($output);

		// シートの情報を取得
		$spreadsheets = $this->gshelper->gs()->spreadsheets->get( $this->gshelper->get_spreadsheet_id() );
		$this->assertEquals( preg_match( '/^sitemap\-[0-9]+\-[0-9]+$/', $spreadsheets['sheets'][0]['properties']['title'] ), 1 );

		// 既存のシート 'sitemap' を削除
		foreach($spreadsheets['sheets'] as $sheetInfo){
			if( $sheetInfo['properties']['title'] == 'sitemap' ){
				$requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
					'requests' => array(
						'deleteSheet'=>array(
							'sheetId' => $sheetInfo['properties']['sheetId'],
						)
					)
				));
				$response = $this->gshelper->gs()->spreadsheets->batchUpdate($this->gshelper->get_spreadsheet_id(), $requestBody);
			}
		}

		// 作成した新しいシートを 'sitemap' に改名
		$newSheetProperties = $spreadsheets['sheets'][0]['properties'];
		$newSheetProperties['title'] = 'sitemap';
		$response = $this->gshelper->gs()->spreadsheets->batchUpdate(
			$this->gshelper->get_spreadsheet_id(),
			new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
				'requests' => array(
					'updateSheetProperties'=>array(
						'properties' => $newSheetProperties,
						'fields' => '*',
					)
				)
			))
		);

		$this->assertEquals( 1, 1 );

	} // testCsv2GsConvert()


	/**
	 * google spreadsheet to .csv 変換のテスト
	 */
	public function testGs2CsvConvert(){

		// トップページを実行
		$output = $this->testhelper->passthru( [
			'php',
			__DIR__.'/testdata/standard/.px_execute.php' ,
			'/?PX=sitemapgs.pull' ,
		] );
		// var_dump($output);

		$this->assertEquals( 1, 1 );

	} // testGs2CsvConvert()

}
