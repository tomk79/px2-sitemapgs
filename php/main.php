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

	/**
	 * entry
	 * @param object $px Picklesオブジェクト
	 * @param object $plugin_conf プラグイン設定
	 */
	static public function exec($px, $plugin_conf){
		(new self($px, $plugin_conf))->convert_all();
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

		$this->realpath_sitemap_dir = $this->px->get_path_homedir().'sitemaps/';
		$this->locker = new lock($this->px, $this);
	}

	/**
	 * すべてのファイルを変換する
	 */
	public function convert_all(){
		// TODO: 未実装



		putenv('GOOGLE_APPLICATION_CREDENTIALS='.$this->plugin_conf['credentials_path']);
		$client = new \Google_Client();
		$client->useApplicationDefaultCredentials();
		$client->addScope(\Google_Service_Sheets::SPREADSHEETS);
		$client->setApplicationName('px2-sitemapgs');

		$service = new \Google_Service_Sheets($client);

		// 値を取得
		$response = $service->spreadsheets_values->get($this->plugin_conf['spreadsheet_id'], 'シート1!A1:A2');
		foreach ($response->getValues() as $index => $cols) {
			echo sprintf('<pre><code>%d: &quot;%s&quot;</code></pre>', $index+1, implode('", "', $cols))."\n";
		}

		return;
	}

}
