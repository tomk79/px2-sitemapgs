<?php
/**
 * main.php
 */
namespace tomk79\pickles2\sitemap_gs;

/**
 * main.php
 */
class main{
	/** Picklesオブジェクト */
	private $px;
	/** プラグイン設定 */
	private $plugin_conf;
	/** サイトマップフォルダのパス */
	private $realpath_sitemap_dir;
	/** アプリケーションロック */
	private $locker;
	/** 進捗を表示するフラグ */
	private $show_progress_flg = false;

	/**
	 * entry
	 * @param object $px Picklesオブジェクト
	 * @param object $plugin_conf プラグイン設定
	 */
	static public function exec($px, $plugin_conf){
		$plugin = new self($px, $plugin_conf);
		$px->pxcmd()->register('sitemapgs', function($px)use($plugin){
			$command = $px->get_px_command();
			$direction = null;
			switch(@strtolower($command[1])){
				case 'gs2csv':
					$direction = 'gs2csv';
					break;
				case 'csv2gs':
					$direction = 'csv2gs';
					break;
			}
			print $px->pxcmd()->get_cli_header();
			if($direction){
				print 'progress...'."\n";
				$plugin->show_progress_flg = true;
				$plugin->convert_all($direction, true);
				sleep(1); print "\n";
				print 'All process DONE!.'."\n";
			}else{
				print 'gs2csv, または csv2gs のいずれかのコマンドを指定してください。'."\n";
			}
			print "\n";
			print $px->pxcmd()->get_cli_footer();
			exit;
		});

		$plugin->convert_all('gs2csv', false);
	}

	/**
	 * constructor
	 * @param object $px Picklesオブジェクト
	 * @param object $plugin_conf プラグイン設定
	 */
	public function __construct( $px, $plugin_conf = null ){
		$this->px = $px;
		$this->plugin_conf = $plugin_conf;

		// object から 連想配列に変換
		$this->plugin_conf = json_decode( json_encode($this->plugin_conf), true );
		if( !is_array($this->plugin_conf) ){ $this->plugin_conf = array(); }
		if( !@strlen($this->plugin_conf['google_application_credentials']) ){
			$this->plugin_conf['google_application_credentials'] = false;
			$this->px->error('[px2-sitemapgs] `google_application_credentials` option is required.');
		}
		if( @$this->plugin_conf['csv_expire'] === false || @strtolower($this->plugin_conf['csv_expire']) === 'false' ){
			$this->plugin_conf['csv_expire'] = false;
		}elseif( !@strlen($this->plugin_conf['csv_expire']) ){
			$this->plugin_conf['csv_expire'] = 300;
		}
		// var_dump($this->plugin_conf);

		if($this->plugin_conf['google_application_credentials']){
			// 環境変数 `GOOGLE_APPLICATION_CREDENTIALS` を設定
			// Google Client ライブラリが読み取ります。
			putenv('GOOGLE_APPLICATION_CREDENTIALS='.$this->plugin_conf['google_application_credentials']);
		}

		$this->realpath_sitemap_dir = $this->px->get_path_homedir().'sitemaps/';
		$this->locker = new lock($this->px, $this);
	}

	/**
	 * すべてのファイルを変換する
	 *
	 * @param string $direction 変換の方向。 `gs2csv` または `csv2gs` を指定できます。 省略時 `gs2csv` 。
	 * @param boolean $force `true` を指定して強制的に変換する。
	 */
	public function convert_all( $direction = 'gs2csv', $force = false ){
		if(!$direction){ $direction = 'gs2csv'; }
		if( $this->plugin_conf['csv_expire'] === false && !$force){
			return;
		}
		$sitemap_files = array();
		$tmp_sitemap_files = $this->px->fs()->ls( $this->realpath_sitemap_dir );
		$this->msg('Listing sitemap files...');
		foreach( $tmp_sitemap_files as $filename ){
			if( preg_match( '/^\\~\\$/', $filename ) ){
				// エクセルの編集中のキャッシュファイルのファイル名だからスルー
				continue;
			}
			if( preg_match( '/^\\.\\~lock\\./', $filename ) ){
				// Libre Office, Open Office の編集中のキャッシュファイルのファイル名だからスルー
				continue;
			}
			$extless_basename = $this->px->fs()->trim_extension($filename);
			$extension = $this->px->fs()->get_extension($filename);
			$extension = strtolower($extension);

			if( $extension != 'gsheet' && $extension != 'csv' ){
				// 知らない拡張子はスキップ
				continue;
			}

			if( !@is_array($sitemap_files[$extless_basename]) ){
				$sitemap_files[$extless_basename] = array();
			}
			$sitemap_files[$extless_basename][$extension] = $filename;
		}
		// var_dump($sitemap_files);
		$this->msg($sitemap_files);

		foreach( $sitemap_files as $extless_basename=>$extensions ){
			$this->msg('');
			$this->msg('------ Progress "'.$extless_basename.'" :');

			// ファイルが既存しない場合、ファイル名がセットされていないので、
			// 明示的にセットする。
			if( !@strlen($extensions['gsheet']) ){
				$extensions['gsheet'] = $extless_basename.'.gsheet';
			}
			if( !@strlen($extensions['csv']) ){
				$extensions['csv'] = $extless_basename.'.csv';
			}

			if(
				$direction == 'gs2csv'
				&& (
					!is_file($this->realpath_sitemap_dir.$extensions['csv'])
					|| time() - $this->plugin_conf['csv_expire'] > filemtime( $this->realpath_sitemap_dir.$extensions['csv'] )
					|| $force
				)
			){
				// spreadsheet がマスターになる場合
				if( !$this->locker->lock() ){
					$this->msg('Skipped. (process locked)');
				}else{
					$result = $this->gs2csv(
						$this->realpath_sitemap_dir.$extensions['gsheet'],
						$this->realpath_sitemap_dir.$extensions['csv']
					);
					touch(
						$this->realpath_sitemap_dir.$extensions['csv']
					);
					$this->locker->unlock();
					$this->msg('"'.$extless_basename.'" -> DONE.');
				}

			}elseif(
				$direction == 'csv2gs'
				&& (
					true === $this->px->fs()->is_newer_a_than_b( $this->realpath_sitemap_dir.$extensions['csv'], $this->realpath_sitemap_dir.$extensions['gsheet'] )
					|| $force
				)
			){
				// CSV がマスターになる場合
				if( !$this->locker->lock() ){
					$this->msg('Skipped. (process locked)');
				}else{
					$result = $this->csv2gs(
						$this->realpath_sitemap_dir.$extensions['csv'],
						$this->realpath_sitemap_dir.$extensions['gsheet']
					);
					$this->locker->unlock();
					$this->msg('"'.$extless_basename.'" -> DONE.');
				}
			}
			$this->msg('');

		}
		return;
	}

	/**
	 * Googleスプレッドシート を サイトマップCSV に変換
	 *
	 * このメソッドは、変換後のファイルを生成するのみです。
	 * タイムスタンプの調整等は行いません。
	 *
	 * @param string $path_spreadsheet Googleスプレッドシートのパス
	 * @param string $path_csv CSVファイルのパス
	 * @return boolean 実行結果
	 */
	public function gs2csv($path_spreadsheet, $path_csv){
		$result = @(new gs2csv($this->px, $this))->convert( $path_spreadsheet, $path_csv );
		return $result;
	}

	/**
	 * サイトマップCSV を Googleスプレッドシート に変換
	 *
	 * このメソッドは、変換後のファイルを生成するのみです。
	 * タイムスタンプの調整等は行いません。
	 *
	 * @param string $path_csv CSVファイルのパス
	 * @param string $path_spreadsheet Googleスプレッドシートのパス
	 * @return boolean 実行結果
	 */
	public function csv2gs($path_csv, $path_spreadsheet){
		$result = @(new csv2gs($this->px, $this))->convert( $path_csv, $path_spreadsheet );
		return $result;
	}

	/**
	 * 進捗メッセージを表示する
	 * PXコマンドによる手動変換実行時に、送られた `$message` を標準出力します。
	 * 自然変換実行時は、メッセージは表示されず捨てられます。
	 * @param string $message メッセージ文字列
	 */
	public function msg( $message = '' ){
		if($this->show_progress_flg){
			if( is_float($message) || is_int($message) || is_string($message) ){
				echo $message."\n";
			}else{
				var_dump($message);
			}
		}
		return;
	}

	/**
	 * サイトマップCSVの定義を取得する
	 * @return array サイトマップCSV定義配列
	 */
	public function get_sitemap_definition(){
		$col = 'A';
		$num = 0;
		$rtn = array();
		$rtn['path'] = array('num'=>$num++,'col'=>$col++,'key'=>'path','name'=>'ページのパス');
		$rtn['content'] = array('num'=>$num++,'col'=>$col++,'key'=>'content','name'=>'コンテンツファイルの格納先');
		$rtn['id'] = array('num'=>$num++,'col'=>$col++,'key'=>'id','name'=>'ページID');
		$rtn['title'] = array('num'=>$num++,'col'=>$col++,'key'=>'title','name'=>'ページタイトル');
		$rtn['title_breadcrumb'] = array('num'=>$num++,'col'=>$col++,'key'=>'title_breadcrumb','name'=>'ページタイトル(パン屑表示用)');
		$rtn['title_h1'] = array('num'=>$num++,'col'=>$col++,'key'=>'title_h1','name'=>'ページタイトル(H1表示用)');
		$rtn['title_label'] = array('num'=>$num++,'col'=>$col++,'key'=>'title_label','name'=>'ページタイトル(リンク表示用)');
		$rtn['title_full'] = array('num'=>$num++,'col'=>$col++,'key'=>'title_full','name'=>'ページタイトル(タイトルタグ用)');
		$rtn['logical_path'] = array('num'=>$num++,'col'=>$col++,'key'=>'logical_path','name'=>'論理構造上のパス');
		$rtn['list_flg'] = array('num'=>$num++,'col'=>$col++,'key'=>'list_flg','name'=>'一覧表示フラグ');
		$rtn['layout'] = array('num'=>$num++,'col'=>$col++,'key'=>'layout','name'=>'レイアウト');
		$rtn['orderby'] = array('num'=>$num++,'col'=>$col++,'key'=>'orderby','name'=>'表示順');
		$rtn['keywords'] = array('num'=>$num++,'col'=>$col++,'key'=>'keywords','name'=>'metaキーワード');
		$rtn['description'] = array('num'=>$num++,'col'=>$col++,'key'=>'description','name'=>'metaディスクリプション');
		$rtn['category_top_flg'] = array('num'=>$num++,'col'=>$col++,'key'=>'category_top_flg','name'=>'カテゴリトップフラグ');
		$rtn['role'] = array('num'=>$num++,'col'=>$col++,'key'=>'role','name'=>'ロール');
		$rtn['proc_type'] = array('num'=>$num++,'col'=>$col++,'key'=>'proc_type','name'=>'コンテンツの処理方法');
		return $rtn;
	}

}
