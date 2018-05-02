<?php
/**
 * test for tomk79\px2-sitemapgs
 */

class cleaningTest extends PHPUnit_Framework_TestCase{

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
	 * setup
	 */
	public function setup(){
		require_once(__DIR__.'/helper/testhelper.php');
		$this->testhelper = new testhelper();
		$this->fs = new \tomk79\filesystem();
	}

	/**
	 * 後処理
	 */
	public function testCleaning(){

		// 後始末
		$this->fs->rm( __DIR__.'/testdata/standard/px-files/sitemaps/sitemap.gsheet' );
		$output = $this->testhelper->passthru( ['php', __DIR__.'/testdata/standard/.px_execute.php', '/' ] );
		$output = $this->testhelper->passthru( ['php', __DIR__.'/testdata/standard/.px_execute.php', '/?PX=clearcache' ] );
		clearstatcache();

		$this->assertTrue( !is_file( __DIR__.'/testdata/standard/px-files/sitemaps/sitemap.gsheet' ) );
		$this->assertTrue( !is_dir( __DIR__.'/testdata/standard/caches/p/' ) );
		$this->assertTrue( !is_dir( __DIR__.'/testdata/standard/px-files/_sys/ram/caches/sitemaps/' ) );

	} // testCleaning()

}
