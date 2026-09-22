<?php
defined( 'ABSPATH' ) || exit;
function cleanup_import_policy_drafts() {
    if ( ! current_user_can( 'edit_pages' ) ) { throw new RuntimeException( '固定ページ編集権限が必要です。' ); }
    $pages = json_decode( file_get_contents( __DIR__ . '/pages.json' ), true, 512, JSON_THROW_ON_ERROR );
    $report = array();
    foreach ( $pages as $page ) {
        $existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
        if ( $existing ) { $report[] = array( 'id' => $existing->ID, 'result' => 'preserved' ); continue; }
        $key = 'cleanup_policy_import_' . $page['slug'];
        if ( ! add_option( $key, 'importing', '', false ) ) { throw new RuntimeException( '取り込み途中の記録を確認してください：' . $key ); }
        $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_name' => $page['slug'], 'post_title' => $page['title'], 'post_content' => wp_kses_post( $page['content'] ), 'post_author' => get_current_user_id() ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        update_post_meta( $id, '_cleanup_policy_review', '運営者による方針確認、実際の運用との照合、問い合わせ窓口の整備が必要。' );
        update_option( $key, $id, false );
        $report[] = array( 'id' => $id, 'result' => 'draft_created' );
    }
    return $report;
}
if ( defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::log( wp_json_encode( cleanup_import_policy_drafts() ) ); }
