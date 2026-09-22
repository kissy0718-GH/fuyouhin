<?php
defined( 'ABSPATH' ) || exit;
add_action( 'admin_menu', function () { add_submenu_page( 'cleanup-dashboard', '記事制作', '記事制作', 'edit_cleanup_contents', 'cleanup-ai', 'cleanup_ai_screen' ); } );
function cleanup_ai_form_start( $action, $job_id = 0 ) {
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'cleanup_ai_' . $action, 'cleanup_ai_nonce' );
    echo '<input type="hidden" name="action" value="cleanup_ai_' . esc_attr( $action ) . '"><input type="hidden" name="job_id" value="' . esc_attr( $job_id ) . '">';
}
function cleanup_ai_field( $name, $label, $value = '', $large = false, $required = true ) {
    echo '<p><label for="ai-' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
    if ( $large ) { echo '<textarea id="ai-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" class="large-text" rows="5" ' . ( $required ? 'required' : '' ) . '>' . esc_textarea( $value ) . '</textarea>'; }
    else { echo '<input id="ai-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" class="large-text" value="' . esc_attr( $value ) . '" ' . ( $required ? 'required' : '' ) . '>'; }
    echo '</p>';
}
function cleanup_ai_screen() {
    if ( ! cleanup_ai_human() ) { return; }
    echo '<div class="wrap"><h1>記事制作</h1><p>企画 → 一次資料 → 構成 → 原稿 → 人による事実確認 → 公開確認。外部AIは未接続です。指示書を生成先に渡し、返された原稿を登録できます。ここから外部サービスへ送信する処理はありません。</p>';
    $id = absint( $_GET['job_id'] ?? 0 ); $job = cleanup_ai_job( $id );
    if ( $job ) { cleanup_ai_job_screen( $id, $job ); echo '</div>'; return; }
    echo '<h2>新しい企画</h2>'; cleanup_ai_form_start( 'create' );
    cleanup_ai_field( 'keyword', 'キーワード' ); cleanup_ai_field( 'intent', '検索している人が知りたいこと', '', true );
    echo '<p><label>制作判断 <select name="decision">';
    foreach ( array( 'CREATE' => '新規作成', 'UPDATE' => '既存記事を更新', 'MERGE' => '複数記事を統合', 'NO ACTION' => '作成しない' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; }
    echo '</select></label> <label>記事の種類 <select name="article_type">'; foreach ( cleanup_article_types() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; } echo '</select></label></p>';
    cleanup_ai_field( 'reason', '判断理由・既存記事との重複を調べた結果', '', true );
    cleanup_ai_field( 'target_id', '更新先の記事ID（新規作成・作成しない場合は空欄）', '', false, false );
    cleanup_ai_field( 'merge_ids', '統合元の記事ID（カンマ区切り。更新先を含めない）', '', false, false );
    cleanup_ai_field( 'unique_value', '独自の情報・読者への価値', '', true, false );
    cleanup_ai_field( 'outline', '構成案', '', true, false );
    cleanup_ai_field( 'sources', '一次資料：1行につき URL | 資料名 | 裏付ける内容・要約', '', true, false );
    echo '<p>作成しない場合を除き、一次資料・独自の価値・構成案は必須です。資料は必要な要約に限定し、個人情報、取材原本、転載できない長文を入れないでください。</p><p><label><input type="checkbox" name="safe_sources" value="1" required>資料の利用範囲を確認し、個人情報や非公開の取材原本を含めていません</label></p>';
    submit_button( '企画を保存' ); echo '</form><h2>制作中・過去の企画</h2><table class="widefat striped"><thead><tr><th>企画</th><th>判断</th><th>状態</th></tr></thead><tbody>';
    foreach ( get_posts( array( 'post_type' => 'cleanup_ai_job', 'post_status' => 'private', 'numberposts' => 30 ) ) as $post ) { $item = cleanup_ai_job( $post->ID ); echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=cleanup-ai&job_id=' . $post->ID ) ) . '">' . esc_html( $post->post_title ) . '</a></td><td>' . esc_html( $item['decision'] ?? '' ) . '</td><td>' . esc_html( cleanup_ai_state_label( $item['state'] ?? '' ) ) . '</td></tr>'; }
    echo '</tbody></table><p>最新30件を表示しています。公開記事数を増やすこと自体を目的にせず、資料と読者の必要性から企画を選んでください。</p></div>';
}
function cleanup_ai_state_label( $state ) { return array( 'ready' => '原稿待ち', 'draft' => '下書き・確認中', 'applied' => '元記事へ採用済み', 'closed' => '制作しない' )[ $state ] ?? $state; }
function cleanup_ai_job_screen( $id, $job ) {
    echo '<h2>' . esc_html( $job['keyword'] ) . '</h2><p>' . esc_html( $job['decision'] . ' / ' . cleanup_ai_state_label( $job['state'] ) ) . '</p><dl>';
    foreach ( array( 'intent' => '検索意図', 'reason' => '判断理由', 'unique_value' => '独自の価値', 'outline' => '構成' ) as $key => $label ) { echo '<dt><strong>' . esc_html( $label ) . '</strong></dt><dd>' . nl2br( esc_html( $job[ $key ] ) ) . '</dd>'; } echo '</dl><h3>一次資料</h3><ol start="0">';
    foreach ( $job['sources'] as $source ) { echo '<li><a href="' . esc_url( $source['url'] ) . '" rel="noopener">' . esc_html( $source['title'] ) . '</a><p>' . nl2br( esc_html( $source['excerpt'] ) ) . '</p></li>'; } echo '</ol>';
    echo '<h3>既存記事の重複確認候補</h3><ul>';
    $candidates = get_posts( array( 'post_type' => 'article', 'post_status' => array( 'publish', 'draft', 'pending' ), 's' => $job['keyword'], 'numberposts' => 10 ) );
    foreach ( $candidates as $candidate ) { if ( current_user_can( 'edit_post', $candidate->ID ) ) { echo '<li><a href="' . esc_url( get_edit_post_link( $candidate->ID ) ) . '">' . esc_html( $candidate->post_title ) . '</a>（ID ' . esc_html( $candidate->ID ) . '）</li>'; } }
    echo '</ul><p>キーワード検索による候補です。候補がないことは、重複がない証明ではありません。検索意図と内容も確認してください。</p>';
    if ( 'ready' === $job['state'] ) {
        $brief = cleanup_ai_brief( $id );
        if ( ! is_wp_error( $brief ) ) { echo '<details><summary>生成先へ渡す指示書を表示</summary><textarea readonly class="large-text" rows="14" aria-label="生成指示書">' . esc_textarea( wp_json_encode( $brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</textarea></details>'; }
        else { echo '<p>' . esc_html( $brief->get_error_message() ) . '</p>'; }
        echo '<h3>返された原稿を登録</h3>'; cleanup_ai_form_start( 'submit', $id );
        cleanup_ai_field( 'title', '原稿タイトル' ); cleanup_ai_field( 'content', '本文（見出し等のHTMLを使用できます）', '', true ); cleanup_ai_field( 'excerpt', '抜粋', '', true, false );
        cleanup_ai_field( 'claims', '確認対象の主張：1行につき 種類 | 出典番号（0始まり） | 主張', '', true );
        echo '<p>種類：municipality（自治体）、law（法律）、price（料金）、date（日付）、url、company（企業）、hours（営業時間）、policy（制度）、other（その他）。未確認や矛盾がある内容も省略せず登録してください。</p>';
        cleanup_ai_field( 'provider', '生成元（手動の場合はmanual）' ); cleanup_ai_field( 'model', 'モデル名（手動の場合はmanual）' );
        submit_button( '新しい下書きとして保存' ); echo '</form>';
    }
    $draft = 'applied' === $job['state'] ? ( $job['target_id'] ?? 0 ) : ( $job['draft_id'] ?? 0 );
    if ( $draft && get_post( $draft ) ) {
        echo '<h3>原稿と確認</h3><p><a href="' . esc_url( get_edit_post_link( $draft ) ) . '">本文・出典・情報確認日を編集する</a> | <a href="' . esc_url( admin_url( 'admin.php?page=cleanup-review&post_id=' . $draft ) ) . '">公開確認画面</a></p><ul>';
        foreach ( cleanup_ai_audit( $draft ) as $note ) { echo '<li>' . esc_html( $note ) . '</li>'; } echo '</ul>';
        echo '<h4>原稿の現在の内容</h4><div style="background:white;padding:16px">' . wp_kses_post( wpautop( get_post_field( 'post_content', $draft ) ) ) . '</div>';
        if ( ! empty( $job['target_id'] ) && 'draft' === $job['state'] ) {
            echo '<h4>元記事との差分（本文）</h4>';
            echo wp_kses_post( wp_text_diff( get_post_field( 'post_content', $job['target_id'] ), get_post_field( 'post_content', $draft ), array( 'title_left' => '現在の元記事', 'title_right' => '更新案' ) ) ?: '<p>本文に差分はありません。</p>' );
            echo '<p>元記事：' . esc_html( get_the_title( $job['target_id'] ) ) . ' → 更新案：' . esc_html( get_the_title( $draft ) ) . '</p>';
        }
        $claims = get_post_meta( $draft, '_cleanup_ai_claims', true );
        if ( current_user_can( 'cleanup_review' ) ) {
            cleanup_ai_form_start( 'facts', $id );
            echo '<input type="hidden" name="draft_id" value="' . esc_attr( $draft ) . '"><input type="hidden" name="digest" value="' . esc_attr( cleanup_ai_review_digest( $draft ) ) . '"><h4>人によるファクトチェック</h4>';
            foreach ( (array) $claims as $index => $claim ) {
                echo '<fieldset style="padding:16px;margin:12px 0;border:1px solid #bbb"><legend>' . esc_html( $claim['type'] . ' / 出典 ' . $claim['source_index'] ) . '</legend><p>' . esc_html( $claim['claim'] ) . '</p><label>照合結果 <select name="facts[' . esc_attr( $index ) . '][status]">';
                foreach ( array( 'needs_review' => '要確認', 'verified' => '原資料と照合済み', 'disputed' => '矛盾あり' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $claim['status'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
                echo '</select></label><p><label>確認箇所・判断理由<textarea class="large-text" rows="2" name="facts[' . esc_attr( $index ) . '][note]" required>' . esc_textarea( $claim['note'] ) . '</textarea></label></p></fieldset>';
            }
            echo '<p><label><input type="checkbox" name="full_review" value="1" required>主張リストだけでなく本文全体を読み、確認項目の漏れも点検しました</label></p>'; submit_button( '確認結果を保存' ); echo '</form>';
            if ( ! empty( $job['target_id'] ) && 'draft' === $job['state'] ) {
                cleanup_ai_form_start( 'apply', $id ); echo '<input type="hidden" name="digest" value="' . esc_attr( cleanup_ai_review_digest( $draft ) ) . '"><p>採用すると元記事の本文・抜粋・基本出典・記事タイプを更新し、確認待ちに戻します。公開中の場合は一時的に非公開になります。地域・品目の分類とURLのslugは維持します。元記事の最終公開確認は別操作です。統合元の記事は自動で削除・転送しません。</p><p><label><input type="checkbox" name="confirmed" value="1" required>差分を確認し、元記事を確認待ちへ戻して採用します</label></p>'; submit_button( '元記事へ採用して確認待ちにする', 'secondary' ); echo '</form>';
            }
        }
        $links = cleanup_related_posts( $draft );
        echo '<h4>内部リンク候補</h4><ul>'; foreach ( $links as $link ) { echo '<li><a href="' . esc_url( get_permalink( $link ) ) . '">' . esc_html( $link->post_title ) . '</a></li>'; } echo '</ul><p>候補は自動挿入しません。本文との関係を確認して編集してください。</p>';
    }
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=cleanup-ai' ) ) . '">企画一覧へ戻る</a></p>';
}
function cleanup_ai_lines( $text ) {
    if ( ! is_string( $text ) ) { return array(); }
    return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', $text ) ) ) );
}
foreach ( array( 'create', 'submit', 'facts', 'apply' ) as $action ) {
    add_action( 'admin_post_cleanup_ai_' . $action, function () use ( $action ) {
        if ( ! cleanup_ai_human() ) { wp_die( '編集権限が必要です。', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'cleanup_ai_' . $action, 'cleanup_ai_nonce' );
        $input = wp_unslash( $_POST ); $id = absint( $input['job_id'] ?? 0 );
        if ( 'create' === $action ) {
            if ( '1' !== ( $input['safe_sources'] ?? '' ) ) { wp_die( '資料の利用範囲を確認してください。' ); }
            $sources = array(); foreach ( cleanup_ai_lines( $input['sources'] ?? '' ) as $line ) { $parts = array_map( 'trim', explode( '|', $line, 3 ) ); $sources[] = array( 'url' => $parts[0] ?? '', 'title' => $parts[1] ?? '', 'excerpt' => $parts[2] ?? '' ); }
            $input['sources'] = $sources; $input['merge_ids'] = array_filter( array_map( 'trim', explode( ',', is_string( $input['merge_ids'] ?? '' ) ? $input['merge_ids'] : '' ) ) );
            $result = cleanup_ai_create_job( $input ); if ( ! is_wp_error( $result ) ) { $id = $result; }
        } elseif ( 'submit' === $action ) {
            $claims = array(); foreach ( cleanup_ai_lines( $input['claims'] ?? '' ) as $line ) { $parts = array_map( 'trim', explode( '|', $line, 3 ) ); $claims[] = array( 'type' => $parts[0] ?? '', 'source_index' => ctype_digit( $parts[1] ?? '' ) ? (int) $parts[1] : -1, 'claim' => $parts[2] ?? '' ); }
            $result = cleanup_ai_submit( $id, array( 'title' => $input['title'] ?? '', 'content' => $input['content'] ?? '', 'excerpt' => $input['excerpt'] ?? '', 'provider' => $input['provider'] ?? '', 'model' => $input['model'] ?? '', 'claims' => $claims ) );
        } elseif ( 'facts' === $action ) {
            if ( '1' !== ( $input['full_review'] ?? '' ) ) { wp_die( '本文全体を確認してください。' ); }
            $draft = absint( $input['draft_id'] ?? 0 );
            if ( (int) get_post_meta( $draft, '_cleanup_ai_job_id', true ) !== $id ) { wp_die( '対象の企画と原稿が一致しません。' ); }
            $result = cleanup_ai_review_claims( $draft, sanitize_text_field( $input['digest'] ?? '' ), $input['facts'] ?? array() );
        } else {
            if ( '1' !== ( $input['confirmed'] ?? '' ) ) { wp_die( '採用内容を確認してください。' ); }
            $result = cleanup_ai_apply( $id, sanitize_text_field( $input['digest'] ?? '' ) );
        }
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $result->get_error_data()['status'] ?? 400, 'back_link' => true ) ); }
        wp_safe_redirect( admin_url( 'admin.php?page=cleanup-ai&job_id=' . $id ) ); exit;
    } );
}
