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

		// シート 'sitemap' から全行を取得
		$response = $this->gshelper->gs()->spreadsheets_values->get($this->gshelper->get_spreadsheet_id(), 'sitemap'); // 範囲を省略すると全部取得する
		$all_rows = $response->getValues();
		// var_dump($all_rows);

		$this->assertEquals( $all_rows[8-1][0], 'id' );
		$this->assertEquals( $all_rows[8-1][1], 'title' );
		$this->assertEquals( $all_rows[8-1][27], 'custom_006' );
		$this->assertEquals( $all_rows[9-1][1], 'ホーム' );
		$this->assertEquals( $all_rows[10-1][2], 'はじめに' );
		$this->assertEquals( $all_rows[16-1][11], '/sample_pages/training/' );
		$this->assertEquals( $all_rows[23-1][18], '1' );
		$this->assertEquals( $all_rows[35-1][22], 'custom-27' );
		$this->assertEquals( $all_rows[35-1][27], 'custom-27' );
		$this->assertEquals( $all_rows[39-1][0], 'EndOfData' );

	} // testCsv2GsConvert()


	/**
	 * google spreadsheet to .csv 変換のテスト
	 */
	public function testGs2CsvConvert(){

		// GS上の値を書き換えてみる
		$value = new \Google_Service_Sheets_ValueRange();
		$value->setValues(array(
			'values' => array('HOME'),
		));
		$response = $this->gshelper->gs()->spreadsheets_values->update(
			$this->gshelper->get_spreadsheet_id(),
			'sitemap!B9',
			$value,
			array(
				'valueInputOption' => 'USER_ENTERED'
			)
		);

		// Google スプレッドシート から CSV への変換
		$output = $this->testhelper->passthru( [
			'php',
			__DIR__.'/testdata/standard/.px_execute.php' ,
			'/?PX=sitemapgs.pull' ,
		] );
		// var_dump($output);

		$csv = $this->fs->read_csv( __DIR__.'/testdata/standard/px-files/sitemaps/sitemap.csv' );
		// var_dump($csv);

		$this->assertEquals( $csv[1-1][0], '* path' );
		$this->assertEquals( $csv[1-1][17], '* **delete_flg' );
		$this->assertEquals( $csv[1-1][18], '* custom_001' );
		$this->assertEquals( $csv[2-1][3], 'HOME' );//テストコード上で書き換えられている値
		$this->assertEquals( $csv[5-1][13], 'テーマの編集方法をご紹介します。' );
		$this->assertEquals( $csv[23-1][3], '共有コンテンツ' );
		$this->assertEquals( $csv[23-1][4], '' );
		$this->assertEquals( $csv[27-1][0], '/sample_pages/samples/popup.html' );
		$this->assertEquals( $csv[29-1][18], 'custom-28' );

	} // testGs2CsvConvert()

}
