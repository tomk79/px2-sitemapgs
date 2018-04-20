<?php
/**
 * lock.php
 */
namespace tomk79\pickles2\sitemap_gs;

/**
 * lock.php
 */
class lock{
	/** Picklesオブジェクト */
	private $px;
	/** sitemapExcelオブジェクト */
	private $plugin;
	/** */
	private $path_sitemap_cache_dir;

	/**
	 * constructor
	 * @param object $px Picklesオブジェクト
	 * @param object $plugin プラグインオブジェクト
	 */
	public function __construct( $px, $plugin ){
		$this->px = $px;
		$this->plugin = $plugin;
		$this->path_sitemap_cache_dir = $this->px->get_path_homedir().'_sys/ram/caches/sitemaps/';
		if( !$this->px->fs()->is_dir( $this->path_sitemap_cache_dir ) ){
			$this->px->fs()->mkdir_r( $this->path_sitemap_cache_dir );
		}
	}

	/**
	 * lock
	 */
	public function lock(){
		$i = 0;
		clearstatcache();
		while( @is_file( $this->path_sitemap_cache_dir.'making_sitemap_cache.lock.txt' ) ){
			$i ++;
			if( $i > 2 ){
				// 他のプロセスがサイトマップキャッシュを作成中。
				// 2秒待って解除されなければ、true を返して終了する。
				$this->px->error('Sitemap cache generating is now in progress. This page has been incompletely generated.');
				return false;
				break;
			}
			sleep(1);

			// PHPのFileStatusCacheをクリア
			clearstatcache();
		}

		$lockfile_src = '';
		$lockfile_src .= 'ProcessID='.getmypid()."\r\n";
		$lockfile_src .= @date( 'Y-m-d H:i:s' , time() )."\r\n";
		$lockfile_src .= '* tomk79/px2-sitemapgs'."\r\n";
		$this->px->fs()->save_file( $this->path_sitemap_cache_dir.'making_sitemap_cache.lock.txt', $lockfile_src );
		$this->px->fs()->save_file( $this->path_sitemap_cache_dir.'making_sitemapgs.lock.txt', $lockfile_src );

		return true;
	}

	/**
	 * is_locked
	 */
	public function is_locked(){
		clearstatcache();
		$res1 = $this->px->fs()->is_file( $this->path_sitemap_cache_dir.'making_sitemap_cache.lock.txt' );
		$res2 = $this->px->fs()->is_file( $this->path_sitemap_cache_dir.'making_sitemapgs.lock.txt' );
		if( $res1 || $res2 ){
			return true;
		}
		return false;
	}

	/**
	 * unlock
	 */
	public function unlock(){
		clearstatcache();
		$res1 = $this->px->fs()->rm( $this->path_sitemap_cache_dir.'making_sitemap_cache.lock.txt' );
		$res2 = $this->px->fs()->rm( $this->path_sitemap_cache_dir.'making_sitemapgs.lock.txt' );
		if( !$res1 || !$res2 ){
			return false;
		}
		return true;
	}

}
