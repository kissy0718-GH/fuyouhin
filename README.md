# 不用品回収・遺品整理 悪徳業者撲滅プロジェクト

不用品回収の裏側まで、正直に。

提供された開発指示書に従い、まず既存環境を確認し、WordPressによる段階開発の設計をまとめました。

- [設計書](docs/design.md)：システム、CPT、データ、SEO、AI、送客・紹介料、開発Phaseの19項目
- [環境調査結果](docs/environment-audit.md)：確認できた環境と未確認事項
- [受入チェックリスト](docs/acceptance-checklist.md)：実装後に確認する合格条件

Phase 1のテーマと専用プラグインを実装しています。ローカルWordPressで記事・自治体・業者を編集し、人が出典を確認した内容だけを公開できます。本番公開・実情報の登録・MySQL環境での検証は別途必要です。

- [導入・操作手順](docs/setup.md)
- [実装状況と検証結果](docs/phase1-status.md)
- [Phase 2：SEO管理・検証・制限](docs/phase2-status.md)
- [Phase 3：記事制作・AI下書き連携](docs/phase3-status.md)
- [初期原稿4本・業者候補3社と取り込み手順](content/initial/README.md)
- [初期原稿の閲覧用一覧](content/initial/index.html)
- [運営方針の原稿3本](content/policies/index.html)
- [公開準備の引継ぎ・必要情報](docs/launch-preparation.md)

Node.jsとpnpmのある環境では `pnpm install --frozen-lockfile` の後、`pnpm preview` でローカルプレビュー、`pnpm test` で独立したWordPressを使う統合テストを起動できます。初回は公式WordPress等のダウンロードにネット接続が必要です。

自然検索の個別キーワードと問い合わせの完全な紐付けは保証せず、Search Consoleの集計と記事経由の送客・収益を分けて扱います。情報を確認できない自治体・企業・料金・取材発言を創作しません。
