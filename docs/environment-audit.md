# 環境調査結果

調査日：2026-09-09（Asia/Tokyo）

## 確認できた事実

| 対象 | 結果 | 確認方法 |
|---|---|---|
| 作業フォルダ | `.git` のみ。アプリケーションファイルなし | 隠しファイルを含む一覧 |
| WordPress | 作業フォルダ内に存在しない | 全ファイル一覧 |
| Theme / Plugins | 存在しない | 同上 |
| PHP / Composer | PATH上にコマンドを確認できない | Get-Command |
| MySQL / MariaDB | PATH上にコマンドを確認できない。該当名のWindowsサービスも確認できない | Get-Command / Get-Service |
| WP-CLI / Docker | PATH上にコマンドを確認できない | Get-Command |
| Node.js | Codex付属ランタイムを確認 | Get-Command |
| Git | 初期ブランチ master。コミットなし、remote登録なし | git status / log / remote |
| Hosting | 接続設定、既存サイトURL、`.openai/hosting.json` なし | 作業フォルダ確認 |
| API | このプロジェクト用の設定ファイルなし | 作業フォルダ確認 |
| Environment Variables | 関連する名称を調査。WordPress・DB・外部APIの設定と特定できる名称なし | 名称のみ確認。値は表示していない |
| AGENTS.md | 作業フォルダと確認した上位フォルダに存在しない | ファイル一覧 |

PATHにないアプリの未インストールや、外部ホスティング契約の不存在までは断定しない。ディスク全域・他プロジェクト・認証情報保管庫は探索していない。

## 初期判断

既存サイトへの改修ではなく、新規WordPressプロジェクトとして設計する。ホスティング、外部API、公開用データは未確認。指示書35項に従い、今回の初回成果物は設計書とする。実装・WordPress起動・DB接続・公開・業者への送信は未実施。

## 実装環境の条件

PHP 8.3以上、MySQL 8.0以上またはMariaDB 10.11以上、HTTPSを基準とする。実際の採用バージョンは導入時にサポート期間と互換性を確認し固定する。[WordPress公式要件](https://wordpress.org/about/requirements/)

ローカルまたはステージングにPHP・DB・WordPress・WP-CLIを用意し、DB作成、パーマリンク、管理画面ログインを確認してからPhase 1の実装・統合テストに進む。WordPress本体、DB、アップロード、秘密情報はGitに含めない。

必要な外部情報は、利用するWordPress環境（新規ローカルか既存サーバーか）、本番ドメイン、運営者表記、確認済み掲載データ。パスワードやAPIキーを設計書やチャットに転記せず、環境の秘密情報管理を使用する。
