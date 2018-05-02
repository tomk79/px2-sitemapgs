<?php
class testhelper{
	public function __construct(){
	}

	/**
	 * $gshelper を生成する
	 */
	public function create_gshelper(){
		putenv('GOOGLE_APPLICATION_CREDENTIALS='.__DIR__.'/../testdata/data/service-account.json');

		// gsheetファイルを読み込む
		$gshelper = new \tomk79\pickles2\sitemap_gs\pxplugin_sitemapGs_helper_GsHelper();
		$gshelper->load(__DIR__.'/../testdata/data/sitemap.gsheet');
		return $gshelper;
	}

	/**
	 * コマンドを実行し、標準出力値を返す
	 * @param array $ary_command コマンドのパラメータを要素として持つ配列
	 * @return string コマンドの標準出力値
	 */
	public function passthru( $ary_command ){
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
