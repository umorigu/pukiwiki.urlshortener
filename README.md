# PukiWiki URL短縮プラグイン s.inc.php

PukiWiki URL短縮プラグイン (s.inc.php) は短いURLによってページへのアクセスを可能にするプラグインです。

## 使い方

https://pukiwiki.osdn.jp/?UserPlugins/s.inc.php

## セットアップ

### s.inc.php 配置

* https://github.com/umorigu/pukiwiki.urlshortener から s.inc.php をダウンロードする。
* s.inc.php を plugin/ ディレクトリに配置する。

### ディレクトリ作成

wiki/ や plugin/ と並列の位置に次の2つのディレクトリを作成する。

* shortener/
* shortener_counter/

パーミッションの設定

```
chmod -R 777 shortener shortener_counter
```

### skin/pukiwiki.skin.php を編集

```
<?php if ($is_page) { ?>
 <?php if(SKIN_DEFAULT_DISABLE_TOPICPATH) { ?>
   <a href="<?php echo $link['reload'] ?>"><span class="small"><?php echo $link['reload'] ?></span></a>
 <?php } else { ?>
   <span class="small">
   <?php require_once(PLUGIN_DIR . 'topicpath.inc.php'); echo plugin_topicpath_inline(); ?>
   </span>
 <?php } ?>
<?php } ?>
```

ここ↑の置き換え、または下あたりに↓を追加する。

```
<?php if ($is_page) { ?>
 <br>
 <span class="small">
 <?php exist_plugin('s'); echo plugin_s_convert_get_short_link(); ?>
 </span>
<?php } ?>
```

実際はどこか1箇所に書けばよい。


### mod_rewriteを併用する場合

mod_rewrite用の.htaccessの例

```
RewriteEngine on
RewriteBase /var/www/html/pukiwiki
RewriteCond %{QUERY_STRING} ^&([0-9a-f]+)$
RewriteRule ^(.*)$ /pukiwiki/?cmd=s&k=%1
```

これで、

http://w.example.com/pukiwiki/?&2dc9e012b6

のような /?&xxxxxxxxxx 形式でアクセスできるようになる。(本来のURLへの302リダイレクト)

リダイレクトせずにページを表示する場合はPukiWiki本体のページURLカスタマイズによって行う。
https://pukiwiki.osdn.jp/dev/?PageURI

## 設定値

s.inc.php で設定する。

* define('PLUGIN_S_PAGEID_LENGTH', 10);
  * ページを表すキーを何文字にするか。最大32文字
* define('PLUGIN_S_VIRTUAL_QUERY', '?cmd=s&k=');
  * "/" と キーの間の文字列。mod_rewrite が使える場合は、ここで '?&' などを指定する。
* define('PLUGIN_S_PAGENAME_MININUM_LENGTH', 20);
  * 「ここの数字より短いページ名URLの場合には短縮URLを表示しない」
* define ('PLUGIN_S_SHORT_URL_ON_PERCENT', FALSE);
  * TRUEの場合、オリジナルのURLに '%'が含まれていると常に短いURLを利用する。

## Licenses

GPL v2
