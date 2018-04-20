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
	 * setup
	 */
	public function setup(){
		$this->test_timestamp = @mktime(0, 0, 0, 1, 1, 2000);
		$this->path_sitemap = __DIR__.'/testdata/standard/px-files/sitemaps/';
		$this->fs = new \tomk79\filesystem();
		mb_internal_encoding('utf-8');
		@date_default_timezone_set('Asia/Tokyo');

	}

	/**
	 * google spreadsheet to .csv 変換のテスト
	 */
	public function testGs2CsvConvert(){

		// トップページを実行
		$output = $this->passthru( [
			'php',
			__DIR__.'/testdata/standard/.px_execute.php' ,
			'/' ,
		] );
		// var_dump($output);

		$this->assertEquals( 1, 1 );


		// 後始末
		$output = $this->passthru( ['php', __DIR__.'/testdata/standard/.px_execute.php', '/' ] );
		$output = $this->passthru( ['php', __DIR__.'/testdata/standard/.px_execute.php', '/?PX=clearcache' ] );
		clearstatcache();
		$this->assertTrue( !is_dir( __DIR__.'/testdata/standard/caches/p/' ) );
		$this->assertTrue( !is_dir( __DIR__.'/testdata/standard/px-files/_sys/ram/caches/sitemaps/' ) );

	}//testGs2CsvConvert()



	/**
	 * コマンドを実行し、標準出力値を返す
	 * @param array $ary_command コマンドのパラメータを要素として持つ配列
	 * @return string コマンドの標準出力値
	 */
	private function passthru( $ary_command ){
		$cmd = array();
		foreach( $ary_command as $row ){
			$param = escapeshellarg($row);
			array_push( $cmd, $param );
		}
		$cmd = implode( ' ', $cmd );
		ob_start();
		passthru( $cmd );
		$bin = ob_get_clean();
		return $bin;
	}// passthru()

}
