<?php
defined( 'ABSPATH' ) || exit;
function cleanup_article_types() {
    return array( 'scam' => '悪徳業者事例', 'industry' => '業界の裏話', 'staff' => '作業スタッフの本音', 'news' => '不用品回収ニュース', 'price' => '料金相場' );
}
function cleanup_types() { return array( 'article', 'municipality', 'company' ); }
add_action( 'init', 'cleanup_register_content' );
function cleanup_register_content() {
    foreach ( array( 'article' => '記事', 'municipality' => '自治体の回収情報', 'company' => '業者情報' ) as $type => $label ) {
        register_post_type( $type, array(
            'label' => $label, 'public' => true, 'show_in_rest' => true,
            'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'author' ),
            'capability_type' => array( 'cleanup_content', 'cleanup_contents' ), 'map_meta_cap' => true,
            'has_archive' => 'article' !== $type, 'rewrite' => false,
            'menu_icon' => 'company' === $type ? 'dashicons-store' : 'dashicons-media-document',
        ) );
    }
    foreach ( array( 'article_type' => '記事の種類', 'prefecture' => '都道府県', 'city' => '市区町村', 'item' => '品目', 'service_type' => 'サービス' ) as $tax => $label ) {
        register_taxonomy( $tax, 'article_type' === $tax ? array( 'article' ) : cleanup_types(), array(
            'label' => $label, 'public' => false, 'show_ui' => true, 'show_in_rest' => true,
            'show_admin_column' => true, 'hierarchical' => true, 'rewrite' => false,
            'capabilities' => array( 'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options', 'delete_terms' => 'manage_options', 'assign_terms' => 'edit_cleanup_contents' ),
        ) );
    }
}
