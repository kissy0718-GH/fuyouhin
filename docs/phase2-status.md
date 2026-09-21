# Phase 2：SEO基盤

更新：2026-09-16

## 実装

- 個別情報の検索掲載は、確認担当が独自性・ユーザー価値を確認して明示指定する方式。公開済みだけではindex対象にしない。
- 確認対象の変更でSEO承認も解除。最終確認日から90日を超えた情報は検索掲載対象・サイトマップから外す。
- 地域・カテゴリー・トップの検索掲載は「SEO公開管理」で固有のタイトル・説明文を登録し審査。空一覧・不正な地域を拒否し、掲載内容が変わると再審査が必要。
- canonicalは単一出力。ページ送りはページごとの正規URLとし、絞り込みは基本URLをcanonicalに指定してnoindex。
- 表示用パンくずとBreadcrumbListは同じデータを使用。記事はArticle、入力済み運営者名はOrganizationとして出力。架空のレビュー・認定・料金は出力しない。
- WordPress標準サイトマップを専用providerで拡張し、審査済みの個別情報と一覧のみを出力。標準の投稿・タクソノミー・ユーザーのサイトマップは除外。
- 公開済みの旧URLを保存し、現在の確認済み公開URLへ301転送。非公開の送信先・自己転送・複数候補への曖昧な転送は拒否。
- 品目・地域・サービス・記事タイプを共有する関連記事を最大3件表示。自分自身は除外。

## 操作

1. 個別レコードの公開確認画面で「独自の情報・ユーザー価値を確認し、検索掲載対象にする」を選ぶ。
2. 「情報確認 → SEO公開管理」で、`/scam/` や `/company/tokyo/` のような実在する一覧のパス、固有のタイトル・紹介文を登録する。トップは `/`。自治体詳細は個別レコード側で審査する。
3. 内容変更後は個別情報を再確認し、一覧が「再審査が必要」になった場合も再確認する。一覧の承認取消もこの画面で行える。
4. 管理者は運営者情報ページと一致する実名称を設定できる。フッターの表示とOrganizationに共通利用する。

サイト全体の「検索エンジンがサイトをインデックスしないようにする」設定を尊重する。ローカルプレビューはこの設定を有効にしたままで、サイトマップも無効。本番の検索公開操作は実施していない。

## 検証

WordPress 7.1正式版、PHP 8.3、Playground SQLiteで既存のPhase 1テストとSEOテストを実行。結果は `.runtime/test-result.txt`。権限、空・未審査一覧、承認失効、URL履歴、サイトマップの含有/除外、canonicalの重複、Article/BreadcrumbList、301、ページ送り、検索条件と全体noindexを検証対象に含める。

Phase 2終了時は63項目のPHP統合テスト、13公開URL、2件の404、4管理画面とSEOのHTTP検証に合格。

## 残る条件・制限

- MySQL/MariaDBでの検証、本番HTTPS・バックアップ、実情報の登録は未実施。
- ローカルの自動テストはGoogleのインデックス登録・リッチリザルト表示を保証しない。Search Consoleと実ドメインでの検証は公開環境準備後に行う。
- サイトマップ・一覧審査の内容照合は初期件数向けに全対象IDを読む。全国規模の大量レコードを投入する前に、負荷測定と照合結果のキャッシュ・更新キュー化が必要。
- URLの所有権が別の公開ページへ移った場合、現存するページを優先し転送しない。地域slug変更による一覧自体の移動は自動転送の対象外。個別記事・業者・自治体の過去URLを対象とする。
- 検索掲載以外の固定ページは現時点でnoindex。運営者・編集方針などの実内容整備後に個別の公開基準を追加する。

## 参照仕様

- [WordPress Sitemap Provider](https://developer.wordpress.org/reference/classes/wp_sitemaps_provider/)
- [Google canonical](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- [Google BreadcrumbList](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb)
