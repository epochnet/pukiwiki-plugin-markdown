# pukiwiki-plugin-markdown

PukiWiki のページの一部を Markdown（GitHub Flavored Markdown）で書けるようにするプラグインです。

```
#markdown{{
## 手順
1. `docker compose up -d`
2. 画面を確認する

| 項目 | 値 |
| --- | --- |
| ポート | 8080 |
}}
```

- 見出し・リスト・表・コードブロック・打ち消し線・URL の自動リンクなど、GitHub と同じ書き方が使えます
- **安全**：Markdown の中の HTML は実行せず文字として表示し、`javascript:` などの危険なリンクは取り除きます
- 外部への通信はありません（スタイルは内蔵。CDN の CSS やハイライトは設定したときだけ読み込みます）
- Composer は不要です（Markdown の変換には同梱の [cebe/markdown](https://github.com/cebe/markdown) 1.2.1 を使います）

## 動作環境

- PukiWiki 1.5.x、PHP 7.4 以上（PHP 8.4・8.5 で確認。mbstring が必要）
- EUC-JP / UTF-8 のどちらの Wiki でも動きます

検証状況：本物の PukiWiki で確かめたのは **PukiWiki 1.5.4（EUC-JP）** です。UTF-8 の Wiki は、同梱のテスト（`tests/run.php`）で確かめています。

## インストール

`plugin/` の中身を、PukiWiki の `plugin/` ディレクトリにコピーします。

```
plugin/markdown.inc.php
plugin/endmarkdown.inc.php
plugin/markdown/          （変換処理と同梱ライブラリ）
```

## 書き方

### ブロック形式（おすすめ）

```
#markdown{{
# 見出し
- 項目
}}
```

`}}` までが Markdown です。本文の中に `}}` だけの行を書くときは、`#markdown{{{ … }}}` のように括弧を増やします（PukiWiki の複数行プラグインの書き方です）。

### 範囲形式

```
#markdown()
# 見出し
- 項目
#endmarkdown()
```

`#markdown()` から `#endmarkdown()` までが Markdown です。1 ページに何か所でも書けます。`#endmarkdown()` を省くと、ページの最後までが Markdown になります。

範囲形式では、PukiWiki も同じ行を自分の書式として読みます（その表示は隠しています）。Markdown の行の書き方によっては、`#endmarkdown()` の後ろの PukiWiki の書式（引用やリストの続き）に影響することがあります。新しく書くときはブロック形式を使ってください。

## 設定

`pukiwiki.ini.php` などで定数を定義すると変えられます。

| 定数 | 既定 | 説明 |
| --- | --- | --- |
| `PLUGIN_MARKDOWN_BREAKS` | `FALSE` | `TRUE` にすると、1 つの改行も改行として表示します（PukiWiki に近い動き） |
| `PLUGIN_MARKDOWN_STYLESHEET_URL` | `''` | 追加で読み込む CSS（例：github-markdown-css）。空なら内蔵の最小限のスタイルだけ |
| `PLUGIN_MARKDOWN_HIGHLIGHTJS_BASE_URL` | `''` | highlight.js の置き場所（例：`https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/`）。空ならコードの色分けなし |

CDN を設定すると、閲覧者のブラウザがその CDN に通信します。社内向けの Wiki では、ファイルを同じサーバーに置いて、そのURLを指定することをおすすめします。

## セキュリティ

匿名で編集できる Wiki でも安全に使えるように、次のようにしています。

- Markdown の中の HTML（`<script>`、`<img onerror=…>` など）は、タグとして扱わず文字として表示します
- リンクは `http` `https` `mailto` `ftp` と相対 URL、画像は `http` `https` と相対 URL だけを通します。`javascript:` `data:` `vbscript:` などはリンクにしません（`jav&#x61;script:` のような書き換えや、前後の空白にも対応）
- コードブロックの言語名（` ```js ` の部分）は英数字と一部の記号だけを残し、属性を差し込めないようにしています

## 以前の markdown プラグインからの移行

`#markdown()` 〜 `#endmarkdown()` の書き方はそのまま使えます。以前の版との違いは次のとおりです。

- 以前は `endmarkdown.inc.php` がなかったため、`#endmarkdown()` の後ろの本文が表示されませんでした。表示されるようになります
- 以前は 1 ページの 2 か所目以降の `#markdown()` に 1 か所目の内容が表示されていました。それぞれの内容が表示されます
- `#markdown{{ }}` 形式が表示されるようになります
- 編集画面のプレビューに、保存前の内容が表示されるようになります
- Markdown の中の HTML は表示されなくなります（文字として表示されます）

以前の版を `plugin/vendor/` に入れていた場合、ほかのプラグインが使っていなければ `plugin/vendor/` と `plugin/Michelf/` は削除できます。

## テスト

```bash
php tests/run.php          # UTF-8 の Wiki として
php tests/run.php EUC-JP   # EUC-JP の Wiki として（mbstring が必要）
```

## ライセンス

GPL-2.0-or-later（[LICENSE](LICENSE)）。Copyright (C) 2022-2026 株式会社EPOCH-NET

同梱の cebe/markdown（`plugin/markdown/vendor/cebe/markdown/`）は MIT ライセンスです（同ディレクトリの LICENSE）。
