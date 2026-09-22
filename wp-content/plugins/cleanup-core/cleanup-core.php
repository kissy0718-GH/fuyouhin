<?php
/**
 * Plugin Name: Cleanup Core
 * Description: 不用品回収ポータルのコンテンツ管理・情報確認・公開制御。
 * Version: 0.3.0
 * Requires PHP: 8.3
 * Text Domain: cleanup-core
 */
defined( 'ABSPATH' ) || exit;
foreach ( array( 'content-types', 'metadata', 'publication-policy', 'routing', 'admin', 'seo', 'ai-workflow', 'ai-admin' ) as $module ) {
    require_once __DIR__ . '/includes/' . $module . '.php';
}
register_activation_hook( __FILE__, 'cleanup_activate' );
function cleanup_activate() {
    cleanup_register_content();
    foreach ( cleanup_article_types() as $slug => $label ) {
        if ( ! term_exists( $slug, 'article_type' ) ) {
            wp_insert_term( $label, 'article_type', array( 'slug' => $slug ) );
        }
    }
    $caps = array( 'read' => true, 'upload_files' => true );
    foreach ( array( 'edit_cleanup_contents', 'edit_others_cleanup_contents', 'edit_published_cleanup_contents', 'delete_cleanup_contents', 'delete_others_cleanup_contents', 'delete_published_cleanup_contents', 'read_private_cleanup_contents' ) as $cap ) {
        $caps[ $cap ] = true;
    }
    add_role( 'cleanup_editor', '情報編集担当', $caps );
    add_role( 'cleanup_reviewer', '公開確認担当', array_merge( $caps, array( 'publish_cleanup_contents' => true, 'cleanup_review' => true ) ) );
    foreach ( array_merge( $caps, array( 'publish_cleanup_contents' => true, 'cleanup_review' => true ) ) as $cap => $grant ) {
        get_role( 'administrator' )->add_cap( $cap, $grant );
    }
    cleanup_rewrite_rules();
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
