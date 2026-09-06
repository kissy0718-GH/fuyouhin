# 全国不用品回収SEO情報ポータル

「不用品回収の裏側まで、正直に。」を基本思想とする、WordPressベースの情報メディア・自治体情報・業者比較・送客管理MVPです。

## 現在できること

- 公式情報に出典と確認日を付けた自治体・品目・記事の表示
- 地域、品目、即日・夜間条件での業者候補検索
- 試験用問い合わせの保存とLead ID採番
- 管理画面でのLead状態、成約、紹介料台帳の管理
- 人による承認、出典、確認日、品質スコアを要件にした公開ゲート

試験運用では架空のPartnerだけを表示し、`example.com` などの試験用メールアドレスだけを受け付けます。外部業者への送客、メール送信、AI・Search Console・GA4連携は行いません。

## 起動

Node.js 24系で依存関係を導入後、次を実行します。

```powershell
pnpm install
pnpm dev
```

起動後は表示されたURLを開きます。管理画面は `/wp-admin/`、ポータル管理は `wp-admin/admin.php?page=portal-dashboard` です。初回起動時にWordPress Playgroundが開発用WordPressを作成します。

## 構成

- [システム設計書](docs/system-design.md)
- `wordpress/wp-content/plugins/portal-core/` — CPT、業務DB、REST API、送客・紹介料ロジック、管理画面
- `wordpress/wp-content/themes/honest-portal/` — 表示テーマと業者検索画面
- `scripts/seed.php` — 明示的に動作確認用と表示する初期データ

本番公開前には、実在するPartner・料金条件・プライバシー説明・連絡窓口・環境変数・外部配信・API権限を設定し、ステージングで検証してください。本番DBやDNS、既存コンテンツは変更しません。
