<?php
// Run inside WordPress using WP-CLI eval-file --user=<editor>, or the local seed flag.
defined( 'ABSPATH' ) || exit;
function cleanup_import_initial_pack() {
    if ( ! function_exists( 'cleanup_ai_human' ) || ! cleanup_ai_human() ) { throw new RuntimeException( '編集権限で実行してください。' ); }
    $bundle = json_decode( file_get_contents( __DIR__ . '/bundle.json' ), true, 512, JSON_THROW_ON_ERROR );
    if ( 1 !== $bundle['version'] || count( $bundle['records'] ) !== 7 ) { throw new RuntimeException( '原稿パックの形式が不正です。' ); }
    $report = array();
    foreach ( $bundle['records'] as $record ) {
        $key = 'cleanup_initial_v1_' . $record['key'];
        $saved = get_option( $key );
        if ( $saved && 'complete' === ( $saved['state'] ?? '' ) && ! empty( $saved['post_id'] ) && get_post( $saved['post_id'] ) ) {
            $report[] = array( 'key' => $record['key'], 'id' => $saved['post_id'], 'result' => 'preserved' ); continue;
        }
        // Keep an interrupted import locked for investigation rather than creating duplicates.
        if ( ! add_option( $key, array( 'state' => 'importing' ), '', false ) ) { throw new RuntimeException( '取り込み途中の記録を確認してください：' . $key ); }
        $source = $record['sources'][0];
        if ( 'article' === $record['post_type'] ) {
            $job = cleanup_ai_create_job( array( 'keyword' => $record['title'], 'intent' => $record['excerpt'], 'decision' => 'CREATE', 'reason' => '初期公開に向けた全国向け解説原稿', 'unique_value' => '一次資料に基づく説明と、依頼前に使える確認手順をまとめる。', 'outline' => wp_strip_all_tags( $record['content'] ), 'article_type' => $record['article_type'], 'sources' => $record['sources'] ) );
            if ( is_wp_error( $job ) ) { throw new RuntimeException( $job->get_error_message() ); }
            update_option( $key, array( 'state' => 'importing', 'job_id' => $job ), false );
            $id = cleanup_ai_submit( $job, array( 'title' => $record['title'], 'content' => $record['content'], 'excerpt' => $record['excerpt'], 'provider' => 'OpenAI Codex', 'model' => 'model identifier not recorded', 'claims' => $record['claims'] ) );
        } else {
            $id = wp_insert_post( array( 'post_type' => 'company', 'post_status' => 'draft', 'post_title' => $record['title'], 'post_content' => wp_kses_post( $record['content'] ), 'post_excerpt' => $record['excerpt'], 'post_author' => get_current_user_id() ), true );
        }
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        update_option( $key, array( 'state' => 'metadata_pending', 'post_id' => $id ), false );
        wp_update_post( array( 'ID' => $id, 'post_name' => $record['key'] ) );
        $data = cleanup_validate_data( array_merge( $record['data'], array( 'source_url' => $source['url'], 'source_title' => $source['title'], 'source_notes' => implode( "\n", array_map( function ( $s ) { return $s['url'] . ' ' . $s['excerpt']; }, $record['sources'] ) ), 'last_verified_at' => '' ) ), $record['post_type'] );
        if ( is_wp_error( $data ) ) { throw new RuntimeException( $data->get_error_message() ); }
        update_post_meta( $id, '_cleanup_data', $data );
        update_post_meta( $id, '_cleanup_initial_research', array( 'date' => $bundle['research_date'], 'sources' => $record['sources'], 'pending' => $record['todo'] ?? array( '本文全体と出典の人による照合', '主張リストの網羅性', '公開確認日と検索掲載審査' ) ) );
        update_option( $key, array( 'state' => 'complete', 'post_id' => $id ), false );
        $report[] = array( 'key' => $record['key'], 'id' => $id, 'result' => 'draft_created' );
    }
    return $report;
}
if ( defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::log( wp_json_encode( cleanup_import_initial_pack(), JSON_UNESCAPED_UNICODE ) ); }
