<?php
defined( 'ABSPATH' ) || exit;
add_action( 'add_meta_boxes', function () {
    foreach ( cleanup_types() as $type ) { add_meta_box( 'cleanup-data', '情報・出典・公開確認', 'cleanup_meta_box', $type, 'normal', 'high' ); }
} );
function cleanup_meta_box( $post ) {
    wp_nonce_field( 'cleanup_save_' . $post->ID, 'cleanup_nonce' );
    $data = cleanup_data( $post->ID );
    echo '<p>情報を保存してから「公開確認画面」で内容を確認してください。内容が変わると再確認が必要になります。未確認の情報は入力せず、空欄を残してください。</p>';
    echo '<p><strong>確認状態：' . esc_html( cleanup_is_approved( $post->ID ) ? '確認済み' : '要確認' ) . '</strong></p>';
    $ai_job = (int) get_post_meta( $post->ID, '_cleanup_ai_job_id', true );
    if ( $ai_job ) { echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=cleanup-ai&job_id=' . $ai_job ) ) . '">記事制作・ファクトチェックへ</a></p>'; }
    if ( current_user_can( 'cleanup_review' ) ) { echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=cleanup-review&post_id=' . $post->ID ) ) . '">保存済みの内容を公開確認する</a></p>'; }
    foreach ( cleanup_fields( $post->post_type ) as $key => $field ) {
        $value = $data[ $key ] ?? ''; $name = 'cleanup_data[' . $key . ']';
        echo '<p><label for="cleanup-' . esc_attr( $key ) . '"><strong>' . esc_html( $field[0] ) . '</strong></label><br>';
        if ( 'select' === $field[1] ) {
            echo '<select id="cleanup-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
            foreach ( $field[2] as $option => $label ) { echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select>';
        } elseif ( 'textarea' === $field[1] ) {
            echo '<textarea class="widefat" rows="3" id="cleanup-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
        } else {
            $input_type = in_array( $field[1], array( 'url', 'date' ), true ) ? $field[1] : 'text';
            echo '<input class="widefat" type="' . esc_attr( $input_type ) . '" id="cleanup-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
        }
        echo '</p>';
    }
}
add_action( 'save_post', function ( $id, $post ) {
    if ( ! in_array( $post->post_type, cleanup_types(), true ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! isset( $_POST['cleanup_nonce'] ) ) { return; }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cleanup_nonce'] ) ), 'cleanup_save_' . $id ) || ! current_user_can( 'edit_post', $id ) ) { return; }
    $data = cleanup_validate_data( wp_unslash( $_POST['cleanup_data'] ?? array() ), $post->post_type );
    if ( is_wp_error( $data ) ) { set_transient( 'cleanup_error_' . get_current_user_id(), $data->get_error_message(), 120 ); cleanup_invalidate( $id ); return; }
    update_post_meta( $id, '_cleanup_data', $data );
}, 20, 2 );
add_action( 'admin_notices', function () {
    $message = get_transient( 'cleanup_error_' . get_current_user_id() );
    if ( $message ) { echo '<div class="notice notice-error"><p>構造化情報は保存されませんでした：' . esc_html( $message ) . '</p></div>'; delete_transient( 'cleanup_error_' . get_current_user_id() ); }
} );
add_action( 'admin_menu', function () {
    add_menu_page( '情報確認', '情報確認', 'edit_cleanup_contents', 'cleanup-dashboard', 'cleanup_dashboard', 'dashicons-shield' );
    add_submenu_page( 'cleanup-dashboard', '公開確認', '公開確認', 'cleanup_review', 'cleanup-review', 'cleanup_review_screen' );
} );
function cleanup_dashboard() {
    echo '<div class="wrap"><h1>情報確認ダッシュボード</h1><p>確認済みの情報だけを公開します。SEO・送客・収益計測は後続フェーズで追加します。</p><table class="widefat striped"><thead><tr><th>タイトル</th><th>種類</th><th>公開状態</th><th>最終確認日</th><th>情報確認</th></tr></thead><tbody>';
    $posts = get_posts( array( 'post_type' => cleanup_types(), 'post_status' => array( 'draft', 'pending', 'publish', 'future' ), 'numberposts' => 50, 'orderby' => 'modified' ) );
    foreach ( $posts as $post ) {
        if ( ! current_user_can( 'edit_post', $post->ID ) ) { continue; }
        $data = cleanup_data( $post->ID ); $date = $data['last_verified_at'] ?? '';
        $stale = $date && strtotime( $date ) < strtotime( '-90 days' );
        echo '<tr><td><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( $post->post_title ?: 'タイトル未設定' ) . '</a></td><td>' . esc_html( get_post_type_object( $post->post_type )->label ) . '</td><td>' . esc_html( get_post_status_object( $post->post_status )->label ) . '</td><td>' . esc_html( $date ?: '未確認' ) . '</td><td>' . esc_html( $stale ? '確認期限超過' : ( cleanup_is_approved( $post->ID ) ? '確認済み' : '要確認' ) ) . '</td></tr>';
    }
    if ( ! $posts ) { echo '<tr><td colspan="5">まだ登録されていません。記事・自治体・業者情報のメニューから登録してください。</td></tr>'; }
    echo '</tbody></table><p>更新が新しい50件を表示しています。すべてのレコードは各コンテンツの一覧で確認できます。</p></div>';
}
function cleanup_review_screen() {
    $id = absint( $_GET['post_id'] ?? 0 ); $post = get_post( $id );
    if ( ! $post || ! in_array( $post->post_type, cleanup_types(), true ) || ! current_user_can( 'edit_post', $id ) ) { echo '<div class="wrap"><h1>公開確認</h1><p>記事・自治体・業者の編集画面から「公開確認する」を開いてください。</p></div>'; return; }
    echo '<div class="wrap"><h1>公開前の確認：' . esc_html( $post->post_title ) . '</h1><p>一次資料と本文を照合してください。この操作により現在の保存内容が公開されます。</p>';
    echo '<div style="max-width:900px;background:white;padding:24px">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div><dl>';
    foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) { echo '<dt><strong>' . esc_html( $tax->label ) . '</strong></dt><dd>' . esc_html( implode( '、', wp_get_object_terms( $id, $tax->name, array( 'fields' => 'names' ) ) ) ) . '</dd>'; }
    foreach ( cleanup_fields( $post->post_type ) as $key => $field ) { echo '<dt><strong>' . esc_html( $field[0] ) . '</strong></dt><dd>' . nl2br( esc_html( cleanup_data( $id )[ $key ] ?? '未設定' ) ) . '</dd>'; }
    echo '</dl>';
    $errors = cleanup_review_errors( $id );
    if ( $errors ) { echo '<div class="notice notice-warning"><p>' . esc_html( implode( ' ', $errors ) ) . '</p></div>'; }
    else {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'cleanup_approve_' . $id );
        echo '<input type="hidden" name="action" value="cleanup_approve"><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="fingerprint" value="' . esc_attr( cleanup_fingerprint( $id ) ) . '"><p><label><input type="checkbox" name="confirmed" value="1" required>根拠と掲載内容を確認しました</label></p>';
        echo '<p><label><input type="checkbox" name="indexable" value="1">独自の情報・ユーザー価値を確認し、検索掲載対象にする</label></p><p>検索掲載はサイト全体の検索エンジン設定にも従います。ローカル環境は非公開設定を維持します。</p>';
        submit_button( '確認済みとして公開' ); echo '</form>';
    }
    echo '<p><a href="' . esc_url( get_edit_post_link( $id ) ) . '">編集画面に戻る</a></p></div>';
}
add_action( 'admin_post_cleanup_approve', function () {
    $id = absint( $_POST['post_id'] ?? 0 ); check_admin_referer( 'cleanup_approve_' . $id );
    if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) { wp_die( '確認チェックが必要です。', '', array( 'response' => 400 ) ); }
    $result = cleanup_approve( $id, sanitize_text_field( wp_unslash( $_POST['fingerprint'] ?? '' ) ) );
    if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) ); }
    cleanup_set_indexable( $id, '1' === ( $_POST['indexable'] ?? '' ) );
    wp_safe_redirect( get_edit_post_link( $id, 'raw' ) ); exit;
} );
// Region identities are entered from verified official data, never inferred.
add_action( 'city_add_form_fields', function () { cleanup_region_fields(); } );
add_action( 'city_edit_form_fields', function ( $term ) { echo '<tr><td colspan="2">'; cleanup_region_fields( $term->term_id ); echo '</td></tr>'; } );
function cleanup_region_fields( $id = 0 ) {
    wp_nonce_field( 'cleanup_region', 'cleanup_region_nonce' );
    echo '<p><label>所属都道府県 <select name="cleanup_prefecture_id"><option value="0">選択してください</option>';
    foreach ( get_terms( array( 'taxonomy' => 'prefecture', 'hide_empty' => false ) ) as $pref ) { echo '<option value="' . esc_attr( $pref->term_id ) . '" ' . selected( get_term_meta( $id, 'cleanup_prefecture_id', true ), $pref->term_id, false ) . '>' . esc_html( $pref->name ) . '</option>'; }
    echo '</select></label></p><p><label>自治体コード <input name="cleanup_code" inputmode="numeric" pattern="[0-9]{5,6}" value="' . esc_attr( get_term_meta( $id, 'cleanup_code', true ) ) . '"></label></p><p>slugは公式データを確認して半角英字で設定してください。政令市の区は市を親に指定します。</p>';
}
foreach ( array( 'created_city', 'edited_city' ) as $hook ) {
    add_action( $hook, function ( $id ) {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cleanup_region_nonce'] ?? '' ) ), 'cleanup_region' ) ) { return; }
        $pref = absint( $_POST['cleanup_prefecture_id'] ?? 0 ); $code = sanitize_text_field( wp_unslash( $_POST['cleanup_code'] ?? '' ) );
        update_term_meta( $id, 'cleanup_prefecture_id', get_term( $pref, 'prefecture' ) instanceof WP_Term ? $pref : 0 );
        update_term_meta( $id, 'cleanup_code', preg_match( '/^[0-9]{5,6}$/', $code ) ? $code : '' );
    } );
}
function cleanup_invalidate_term( $id, $taxonomy ) {
    if ( ! in_array( $taxonomy, array( 'prefecture', 'city', 'article_type', 'item', 'service_type' ), true ) ) { return; }
    $ids = array( $id );
    $children = get_term_children( $id, $taxonomy ); if ( ! is_wp_error( $children ) ) { $ids = array_merge( $ids, $children ); }
    foreach ( $ids as $term_id ) { $objects = get_objects_in_term( $term_id, $taxonomy ); if ( ! is_wp_error( $objects ) ) { foreach ( $objects as $object_id ) { cleanup_invalidate( $object_id ); } } }
}
add_action( 'edited_term', function ( $id, $tt, $tax ) { cleanup_invalidate_term( $id, $tax ); }, 10, 3 );
foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $hook ) {
    add_action( $hook, function ( $meta, $id, $key ) { if ( in_array( $key, array( 'cleanup_prefecture_id', 'cleanup_code' ), true ) ) { cleanup_invalidate_term( $id, 'city' ); } }, 10, 3 );
}
add_action( 'updated_post_meta', function ( $meta, $id, $key, $value ) { if ( '_cleanup_data' === $key && isset( $value['sort_name'] ) ) { update_post_meta( $id, '_cleanup_sort_name', $value['sort_name'] ); } }, 20, 4 );
add_action( 'added_post_meta', function ( $meta, $id, $key, $value ) { if ( '_cleanup_data' === $key && isset( $value['sort_name'] ) ) { update_post_meta( $id, '_cleanup_sort_name', $value['sort_name'] ); } }, 20, 4 );
foreach ( array( 'added_post_meta', 'updated_post_meta' ) as $hook ) {
    add_action( $hook, function ( $meta, $id, $key, $value ) {
        if ( '_cleanup_data' === $key ) { update_post_meta( $id, '_cleanup_last_verified', $value['last_verified_at'] ?? '' ); }
    }, 20, 4 );
}
add_action( 'restrict_manage_posts', function ( $type ) {
    if ( ! in_array( $type, cleanup_types(), true ) ) { return; }
    $current = sanitize_key( $_GET['cleanup_check'] ?? '' );
    echo '<label class="screen-reader-text" for="cleanup-check">情報確認状態</label><select id="cleanup-check" name="cleanup_check">';
    foreach ( array( '' => 'すべての確認状態', 'needs_review' => '要確認', 'verified' => '確認済み', 'expired' => '確認期限超過（90日）' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
    echo '</select>';
} );
add_action( 'pre_get_posts', function ( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || ! in_array( $query->get( 'post_type' ), cleanup_types(), true ) ) { return; }
    $filter = sanitize_key( $_GET['cleanup_check'] ?? '' );
    if ( 'verified' === $filter ) { $query->set( 'meta_query', array( array( 'key' => '_cleanup_verification', 'value' => 'verified' ) ) ); }
    if ( 'needs_review' === $filter ) { $query->set( 'meta_query', array( 'relation' => 'OR', array( 'key' => '_cleanup_verification', 'compare' => 'NOT EXISTS' ), array( 'key' => '_cleanup_verification', 'value' => 'verified', 'compare' => '!=' ) ) ); }
    if ( 'expired' === $filter ) { $query->set( 'meta_query', array( array( 'key' => '_cleanup_last_verified', 'value' => gmdate( 'Y-m-d', strtotime( '-90 days' ) ), 'compare' => '<', 'type' => 'DATE' ), array( 'key' => '_cleanup_last_verified', 'value' => '', 'compare' => '!=' ) ) ); }
} );
