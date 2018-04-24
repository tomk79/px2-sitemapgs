<?php
/**
 * PX Plugin "sitemapGs"
 */
namespace tomk79\pickles2\sitemap_gs;

class pxplugin_sitemapGs_helper_parseSitemapCsv{

	private $px;
	private $conf;
	private $path_csv;
	/**
	 * サイトマップ配列
	 */
	private $sitemap_array = array();
	/**
	 * ページIDマップ
	 */
	private $sitemap_id_map = array();
	/**
	 * ダイナミックパスの一覧
	 */
	private $sitemap_dynamic_paths = array();
	/**
	 * サイトマップのツリー構造
	 */
	private $sitemap_page_tree = array();
	/**
	 * ダイナミックパスパラメータ
	 */
	private $dynamic_path_param = array();

	/**
	 * constructor
	 * @param object $px Picklesオブジェクト
	 */
	public function __construct( $px, $path_csv ){
		$this->px = $px;
		$this->conf = $this->px->conf();
		$this->path_csv = $path_csv;

		$this->load_sitemap_csv();
	}

	/**
	 * サイトマップCSV(単体)を読み込む
	 */
	private function load_sitemap_csv(){

		// $path_sitemap_dir = $this->px->get_path_homedir().'sitemaps/';
		// $ary_sitemap_files = $this->px->fs()->ls( $path_sitemap_dir );
		// sort($ary_sitemap_files);

		// $path_top の設定値をチューニング
		$path_top = $this->conf->path_top;
		if(!strlen( $path_top )){ $path_top = '/'; }
		$path_top = preg_replace( '/\/$/si' , '/'.$this->px->get_directory_index_primary() , $path_top );//index.htmlを付加する。

		//  サイトマップをロード
		$num_auto_pid = 0;
		$tmp_sitemap = $this->px->fs()->read_csv( $this->path_csv );
		foreach ($tmp_sitemap as $row_number=>$row) {
			set_time_limit(30);//タイマー延命
			$num_auto_pid++;
			$tmp_array = array();
			if( preg_match( '/^(?:\*)/is' , $row[0] ) ){
				if( $row_number > 0 ){
					// アスタリスク始まりの場合はコメント行とみなす。
					continue;
				}
				// アスタリスク始まりでも、0行目の場合は、定義行とみなす。
				// 定義行とみなす条件: 0行目の全セルがアスタリスク始まりであること。
				$is_definition_row = true;
				foreach($row as $cell_value){
					if( !preg_match( '/^(?:\*)/is' , $cell_value ) ){
						$is_definition_row = false;
					}
				}
				if( !$is_definition_row ){
					continue;
				}
				$tmp_sitemap_definition = array();
				$tmp_col_id = 'A';
				foreach($row as $tmp_col_number=>$cell_value){
					$cell_value = trim(preg_replace('/^\*/si', '', $cell_value));
					$tmp_sitemap_definition[$cell_value] = array(
						'num'=>$tmp_col_number,
						'col'=>$tmp_col_id++,
						'key'=>$cell_value,
						'name'=>$cell_value,
					);
				}
				unset($is_definition_row);
				unset($cell_value);
				continue;
			}
			foreach ($tmp_sitemap_definition as $defrow) {
				$tmp_array[$defrow['key']] = $row[$defrow['num']];
			}
			if( !preg_match( '/^(?:\/|alias\:|javascript\:|\#|[a-zA-Z0-9]+\:\/\/)/is' , $tmp_array['path'] ) ){
				// 不正な形式のチェック
				continue;
			}
			switch( $this->get_path_type( $tmp_array['path'] ) ){
				case 'full_url':
				case 'javascript':
				case 'anchor':
					// 直リンク系のパスをエイリアス扱いにする
					$tmp_array['path'] = preg_replace('/^(?:alias:)?/s', 'alias:', $tmp_array['path']);
					break;
				default:
					// スラ止のパスに index.html を付加する。
					// ただし、JS、アンカー、外部リンクには適用しない。
					$tmp_array['path'] = preg_replace( '/\/((?:\?|\#).*)?$/si' , '/'.$this->px->get_directory_index_primary().'$1' , $tmp_array['path'] );
					break;
			}
			if( !strlen( $tmp_array['id'] ) ){
				//ページID文字列を自動生成
				$tmp_id = ':auto_page_id.'.($num_auto_pid);
				$tmp_array['id'] = $tmp_id;
				unset($tmp_id);
			}

			// project.path_top の設定に対する処理
			if( $tmp_array['path'] == $path_top ){
				$tmp_array['id'] = '';
			}elseif( !strlen($tmp_array['id']) ){
				$tmp_array['id'] = ':auto_page_id.'.($num_auto_pid);
			}

			if($this->get_path_type( $tmp_array['path'] ) == 'dynamic'){
				//ダイナミックパスのインデックス作成
				$tmp_preg_pattern = $tmp_array['path'];
				$preg_pattern = '';
				while(1){
					if( !preg_match('/^(.*?)\{(\$|\*)([a-zA-Z0-9\-\_]*)\}(.*)$/s',$tmp_preg_pattern,$tmp_matched) ){
						$preg_pattern .= preg_quote($tmp_preg_pattern,'/');
						break;
					}
					$preg_pattern .= preg_quote($tmp_matched[1],'/');
					switch( $tmp_matched[2] ){
						case '$':
							$preg_pattern .= '([a-zA-Z0-9\-\_]+)';break;
						case '*':
							$preg_pattern .= '(.*?)';break;
					}
					$tmp_preg_pattern = $tmp_matched[4];
					continue;
				}
				preg_match_all('/\{(\$|\*)([a-zA-Z0-9\-\_]*)\}/',$tmp_array['path'],$pattern_map);
				$tmp_path_original = $tmp_array['path'];
				$tmp_array['path'] = preg_replace('/'.preg_quote('{','/').'(\$|\*)([a-zA-Z0-9\-\_]*)'.preg_quote('}','/').'/s','$2',$tmp_array['path']);
				array_push( $this->sitemap_dynamic_paths, array(
					'path'=>$tmp_array['path'],
					'path_original'=>$tmp_path_original,
					'id'=>$tmp_array['id'],
					'preg'=>'/^'.$preg_pattern.'$/s',
					'pattern_map'=>$pattern_map[2],
				) );
				if( !strlen( $tmp_array['content'] ) ){
					$tmp_array['content'] = $tmp_array['path'];
				}
				$tmp_array['path'] = $tmp_path_original;
				unset($preg_pattern);
				unset($pattern_map);
				unset($tmp_path_original);
			}

			if( !strlen( $tmp_array['content'] ) ){
				$tmp_array['content'] = $tmp_array['path'];
				$tmp_array['content'] = preg_replace('/(?:\?|\#).*$/s','',$tmp_array['content']);
				$tmp_array['content'] = preg_replace('/\/$/s','/'.$this->px->get_directory_index_primary(), $tmp_array['content']);
			}
			$tmp_array['content'] = preg_replace( '/\/$/si' , '/'.$this->px->get_directory_index_primary() , $tmp_array['content'] );//index.htmlを付加する。
			if( preg_match( '/^alias\:/s' , $tmp_array['path'] ) ){
				//エイリアスの値調整
				$tmp_array['content'] = null;
				$tmp_array['path'] = preg_replace( '/^alias\:/s' , 'alias'.$num_auto_pid.':' , $tmp_array['path'] );
			}

			//  パンくず欄の先頭が > から始まっていた場合、削除
			$tmp_array['logical_path'] = @preg_replace( '/^\>+/s' , '' , $tmp_array['logical_path'] );

			$this->sitemap_array[$tmp_array['path']] = $tmp_array;
			$this->sitemap_id_map[$tmp_array['id']] = $tmp_array['path'];
		}

		// var_dump($this->sitemap_array);
		// var_dump($this->sitemap_id_map);
		return true;
	}//load_sitemap_csv();

	/**
	 * サイトマップ配列(単品)を取得する
	 *
	 * @return array (CSV単品に含まれる)全ページが含まれたサイトマップ配列
	 */
	public function get_sitemap(){
		return $this->sitemap_array;
	}

	/**
	 * パス文字列を受け取り、種類を判定する。
	 *
	 * @param string $path 調べるパス
	 * @return string|bool 判定結果。 処理結果は、 `$px->get_path_type()` に同じ。
	 */
	public function get_path_type( $path ) {
		$path_type = $this->px->get_path_type($path);
		return $path_type;
	}//get_path_type()

	/**
	 * ページ情報を取得する。
	 *
	 * このメソッドは、指定したページの情報を連想配列で返します。対象のページは第1引数にパスまたはページIDで指定します。
	 *
	 * カレントページの情報を取得する場合は、代わりに `$px->site()->get_current_page_info()` が使用できます。
	 *
	 * パスで指定したページの情報を取得する例 :
	 * <pre>&lt;?php
	 * // ページ &quot;/aaa/bbb.html&quot; のページ情報を得る
	 * $page_info = $px-&gt;site()-&gt;get_page_info('/aaa/bbb.html');
	 * var_dump( $page_info );
	 * ?&gt;</pre>
	 *
	 * ページIDで指定したページの情報を取得する例 :
	 * <pre>&lt;?php
	 * // トップページのページ情報を得る
	 * // (トップページのページIDは必ず空白の文字列)
	 * $page_info = $px-&gt;site()-&gt;get_page_info('');
	 * var_dump( $page_info );
	 * ?&gt;</pre>
	 *
	 * @param string $path 取得するページのパス または ページID。省略時、カレントページから自動的に取得します。
	 * @param string $key 取り出す単一要素のキー。省略時はすべての要素を含む連想配列が返されます。省略可。
	 * @return mixed 単一ページ情報を格納する連想配列、`$key` が指定された場合は、その値のみ。
	 */
	public function get_page_info( $path, $key = null ){
		if( is_null($path) ){ return null; }
		if( array_key_exists($path, $this->sitemap_id_map) && !is_null($this->sitemap_id_map[$path]) ){
			//ページIDで指定された場合、パスに置き換える
			$path = $this->sitemap_id_map[$path];
		}

		if( !preg_match( '/^(?:\/|[a-zA-Z0-9]+\:)/s', $path ) ){
			// $path が相対パスで指定された場合
			preg_match( '/(\/)$/s', $path, $tmp_matched );
			$path = $this->px->fs()->get_realpath( dirname( $this->px->req()->get_request_file_path() ).'/'.$path );
			if( @strlen($tmp_matched[1]) ){ $path .= $tmp_matched[1]; }
			$path = $this->px->fs()->normalize_path($path);
			unset( $tmp_matched );
		}
		switch( $this->get_path_type($path) ){
			case 'full_url':
			case 'javascript':
			case 'anchor':
				break;
			default:
				$path = preg_replace('/\/'.$this->px->get_directory_index_preg_pattern().'((?:\?|\#).*)?$/si','/$1',$path);//directory_index を一旦省略
				$tmp_path = $path;
				if( !array_key_exists($path, $this->sitemap_id_map) || is_null( $this->sitemap_array[$path] ) ){
					foreach( $this->px->get_directory_index() as $index_file_name ){
						$tmp_path = preg_replace('/\/((?:\?|\#).*)?$/si','/'.$index_file_name.'$1',$path);//省略された index.html を付加。
						if( !is_null( @$this->sitemap_array[$tmp_path] ) ){
							break;
						}
					}
				}
				$path = $tmp_path;
				unset($tmp_path);
				$parsed_url = parse_url($path);
				break;
		}

		if( is_null( @$this->sitemap_array[$path] ) ){
			//  サイトマップにズバリなければ、
			//  ダイナミックパスを検索する。
			$sitemap_dynamic_path = $this->get_dynamic_path_info( $path );
			if( is_array( $sitemap_dynamic_path ) ){
				$path = $sitemap_dynamic_path['path_original'];
			}
		}
		$args = func_get_args();

		switch( $this->get_path_type($path) ){
			case 'full_url':
			case 'javascript':
			case 'anchor':
				break;
			default:
				$path = preg_replace( '/\/$/si' , '/'.$this->px->get_directory_index_primary() , $path );
				break;
		}

		if( is_null( @$this->sitemap_array[$path] ) ){
			//  サイトマップにズバリなければ、
			//  引数からパラメータを外したパスだけで再検索
			$path = @$parsed_url['path'];
		}

		$rtn = @$this->sitemap_array[$path];
		if( !is_array($rtn) ){ return null; }
		// if( !strlen( @$rtn['title_breadcrumb'] ) ){ $rtn['title_breadcrumb'] = $rtn['title']; }
		// if( !strlen( @$rtn['title_h1'] ) ){ $rtn['title_h1'] = $rtn['title']; }
		// if( !strlen( @$rtn['title_label'] ) ){ $rtn['title_label'] = $rtn['title']; }
		// if( !strlen( @$rtn['title_full'] ) ){ $rtn['title_full'] = $rtn['title'].' | '.$this->px->conf()->name; }
		if( count($args) >= 2 ){
			$rtn = $rtn[$args[1]];
		}
		return $rtn;
	}

	/**
	 * 子階層のページの一覧を取得する。
	 *
	 * このメソッドは、指定したページの子階層のページの一覧を返します。`$path` を省略した場合は、カレントページのパスを起点に一覧を抽出します。
	 *
	 * カレントページの子階層のリンクを作成する例 :
	 * <pre>&lt;?php
	 * // カレントページの子階層のリンクを作成する
	 * $children = $px-&gt;site()-&gt;get_children();
	 * print '&lt;ul&gt;';
	 * foreach( $children as $child ){
	 * 	print '&lt;li&gt;'.$px-&gt;theme()-&gt;mk_link($child).'&lt;/li&gt;';
	 * }
	 * print '&lt;/ul&gt;';
	 * ?&gt;</pre>
	 *
	 * カレントページの子階層のリンクを、list_flg を無視してすべて表示する例 :
	 * <pre>&lt;?php
	 * // カレントページの子階層のリンクを作成する
	 * // (list_flg を無視してすべて表示する)
	 * $children = $px-&gt;site()-&gt;get_children(null, array('filter'=&gt;false));
	 * print '&lt;ul&gt;';
	 * foreach( $children as $child ){
	 * 	print '&lt;li&gt;'.$px-&gt;theme()-&gt;mk_link($child).'&lt;/li&gt;';
	 * }
	 * print '&lt;/ul&gt;';
	 * ?&gt;</pre>
	 *
	 * @param string $path 起点とするページのパス または ページID。省略時、カレントページから自動的に取得します。
	 * @param array $opt オプション(省略可)
	 * <dl>
	 *   <dt>$opt['filter'] (初期値: `true`)</dt>
	 *     <dd>フィルターの有効/無効を切り替えます。`true` のとき有効、`false`のとき無効となります。フィルターが有効な場合、サイトマップで `list_flg` が `0` のページが一覧から除外されます。</dd>
	 * </dl>
	 * @return array ページの一覧
	 */
	public function get_children( $path = null, $opt = array() ){
		if( is_null( $path ) ){
			$path = $this->px->req()->get_request_file_path();
		}
		$filter = true;
		if(!is_null(@$opt['filter'])){ $filter = !empty($opt['filter']); }

		$page_info = $this->get_page_info( $path );

		if( $filter && is_array( @$this->sitemap_page_tree[$page_info['path']]['children'] ) ){
			//  ページキャッシュツリーがすでに作られている場合
			return $this->sitemap_page_tree[$page_info['path']]['children'];
		}
		if( !$filter && is_array( @$this->sitemap_page_tree[$page_info['path']]['children_all'] ) ){
			//  ページキャッシュツリーがすでに作られている場合
			return $this->sitemap_page_tree[$page_info['path']]['children_all'];
		}

		$tmp_children_orderby_manual = array();
		$tmp_children_orderby_auto = array();
		$tmp_children_orderby_listed_manual = array();
		$tmp_children_orderby_listed_auto = array();

		// $current_layer = '';
		// if( strlen( trim($page_info['id']) ) ){
		// 	$tmp_breadcrumb = explode( '>', $page_info['logical_path'].'>'.$page_info['id'] );
		// 	foreach( $tmp_breadcrumb as $tmp_path ){
		// 		if( !strlen($tmp_path) ){continue;}
		// 		$tmp_page_info = $this->get_page_info( trim($tmp_path) );
		// 		$current_layer .= '>'.$tmp_page_info['id'];
		// 	}
		// }
		// unset($tmp_breadcrumb,$tmp_path,$tmp_page_info);

		foreach( $this->get_sitemap() as $row ){
			if( !strlen($row['id']) ){
				continue;
			}
			// if($filter){
			// 	if( !$row['list_flg'] ){
			// 		continue;
			// 	}
			// }

			// $target_layer = '';
			$parent_page_id = '';
			if( strlen( trim($row['id']) ) ){
				$tmp_breadcrumb = @explode( '>', $row['logical_path'] );
				$tmp_page_info = $this->get_page_info( trim($tmp_breadcrumb[count($tmp_breadcrumb)-1]) );
				$parent_page_id = trim($tmp_page_info['id']);

				// foreach( $tmp_breadcrumb as $tmp_path ){
				// 	if( !strlen($tmp_path) ){continue;}
				// 	$tmp_page_info = $this->get_page_info( trim($tmp_path) );
				// 	$target_layer .= '>'.$tmp_page_info['id'];
				// }
			}
			unset($tmp_breadcrumb,$tmp_path,$tmp_page_info);

			if( $page_info['id'] == $parent_page_id ){
				if(@strlen($row['orderby'])){
					array_push( $tmp_children_orderby_manual , $row['id'] );
				}else{
					array_push( $tmp_children_orderby_auto , $row['id'] );
				}
				if( $row['list_flg'] ){
					if(@strlen($row['orderby'])){
						array_push( $tmp_children_orderby_listed_manual , $row['id'] );
					}else{
						array_push( $tmp_children_orderby_listed_auto , $row['id'] );
					}
				}
			}
		}

		//  ページキャッシュを作成しなおす
		usort( $tmp_children_orderby_listed_manual , array( $this , 'usort_sitemap' ) );
		$this->sitemap_page_tree[$page_info['path']]['children'] = array_merge( $tmp_children_orderby_listed_manual , $tmp_children_orderby_listed_auto );
		usort( $tmp_children_orderby_manual , array( $this , 'usort_sitemap' ) );
		$this->sitemap_page_tree[$page_info['path']]['children_all'] = array_merge( $tmp_children_orderby_manual , $tmp_children_orderby_auto );

		//  return value
		$rtn = null;
		if($filter){
			$rtn = $this->sitemap_page_tree[$page_info['path']]['children'];
		}else{
			$rtn = $this->sitemap_page_tree[$page_info['path']]['children_all'];
		}

		return $rtn;
	}//get_children()

	/**
	 * ページ情報の配列を並び替える。
	 *
	 * @param string $a 比較対象1のページID
	 * @param string $b 比較対象2のページID
	 * @return int 並び順の前後関係 (`1`|`0`|`-1`)
	 */
	private function usort_sitemap( $a , $b ){
		$page_info_a = $this->get_page_info( $a );
		$page_info_b = $this->get_page_info( $b );
		$orderby_a = $page_info_a['orderby'];
		$orderby_b = $page_info_b['orderby'];
		if( strlen( $orderby_a ) && !strlen( $orderby_b ) ){
			return	-1;
		}elseif( strlen( $orderby_b ) && !strlen( $orderby_a ) ){
			return	1;
		}elseif( $orderby_a < $orderby_b ){
			return	-1;
		}elseif( $orderby_a > $orderby_b ){
			return	1;
		}
		return	0;
	}//usort_sitemap()

	/**
	 * ダイナミックパス情報を得る。
	 *
	 * @param string $path 対象のパス
	 * @return string|bool 見つかった場合に、ダイナミックパスを、見つからない場合に `false` を返します。
	 */
	public function get_dynamic_path_info( $path ){
		foreach( $this->sitemap_dynamic_paths as $sitemap_dynamic_path ){
			//ダイナミックパスを検索
			if( $sitemap_dynamic_path['path_original'] == $path ){
				return $sitemap_dynamic_path;
			}
			if( preg_match( $sitemap_dynamic_path['preg'] , $path ) ){
				return $sitemap_dynamic_path;
			}
		}
		return false;
	}

}
