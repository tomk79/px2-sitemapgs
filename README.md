# px2-sitemapgs
Pickles2 に、サイトマップを Google スプレッドシート で編集できる機能を追加します。

※開発中


## 事前準備

このプラグインからGoogleスプレッドシートにアクセスするために必要な事前準備について説明します。

1. [Google Developers Console](https://console.developers.google.com/) にログインして、プロジェクトを作成します。
2. 作成したプロジェクトの "認証情報" メニューから、 "認証情報を作成" で  を作成します。 
	- "サービスアカウントキー" を選択します。
	- 役割で "閲覧者" を選択します。
	- キーのタイプ で "JSON" を選択します。
	- JSONファイルのダウンロードが始まるので、保存します。 (以降、 `service-account.json` と呼びます)
3. "Google Sheets API" を有効にします。
4. Googleスプレッドシートを作成します。
5. Googleスプレッドシートの共有設定を開き、 共有します。
	- `service-account.json` に含まれる `client_email` にあるメールアドレスに共有します。
	- 編集権限を付与します。
6. `service-account.json` と、スプレッドシートのIDを控えます。
	- この情報はプラグインオプションで使用します。
	- スプレッドシートのURL `https://docs.google.com/spreadsheets/d/xxxxxxxx/edit#gid=0` のうち、 `xxxxxxxx` の部分がスプレッドシートのIDです。

## インストール - Install

※準備中


## オプション - Options

```php
<?php
return call_user_func( function(){

	// funcs: Before sitemap
	$conf->funcs->before_sitemap = [
		// sitemapGS
		'tomk79\pickles2\sitemap_gs\main::exec('.json_encode(array(
			// `service-account.json` のパス
			'google_application_credentials' => '/path/to/service-account.json',

			// CSVファイルの有効期限 (秒)
			// 60 と設定した場合、 CSVファイルのタイムスタンプから 60秒以上経過していたら、
			// Googleスプレッドシートから再読み込みします。
			// false を設定した場合、自然更新されなくなります。
			'csv_expire' => 60*5, // 5分ごとに再読み込み
		)).')',
	];

	return $conf;
} );
```

## PX Commands

- `?PX=sitemapgs.gs2csv`, `?PX=sitemapgs.pull` : Googleスプレッドシート から CSV への変換を手動で実行する。
- `?PX=sitemapgs.csv2gs`, `?PX=sitemapgs.push` : CSV から Googleスプレッドシート への変換を手動で実行する。


## 制約

- サイトマップとして使用するシートの名称は、固定文字列 `sitemap` としてください。


## 関連リンク

- [Google Developers Console](https://console.developers.google.com/)
- [Google Sheets API Document](https://developers.google.com/sheets/api/reference/rest/)
- [google/google-api-php-client](https://github.com/google/google-api-php-client)


## 更新履歴 - Change log

### px2-sitemapexcel 0.0.1 (リリース日未定)

- Initial Release.


## ライセンス - License

MIT License


## 作者 - Author

- Tomoya Koyanagi <tomk79@gmail.com>
- website: <http://www.pxt.jp/>
- Twitter: @tomk79 <http://twitter.com/tomk79/>


## 開発者向け情報 - for Developer

### テスト - Test

```
$ php ./vendor/phpunit/phpunit/phpunit
```
