<?php
defined( 'ABSPATH' ) || exit;
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' ); add_theme_support( 'post-thumbnails' ); add_theme_support( 'responsive-embeds' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    register_nav_menus( array( 'footer' => 'フッターの案内' ) );
} );
add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'cleanup-portal', get_stylesheet_uri(), array(), '0.1.0' ); } );
function portal_categories() {
    return array( 'scam' => '悪徳業者事例', 'industry' => '業界の裏話', 'staff' => 'スタッフの本音', 'news' => 'ニュース', 'municipality' => '自治体の回収情報', 'price' => '料金相場', 'company' => '全国業者一覧' );
}
function portal_badges( $id ) {
    if ( ! function_exists( 'cleanup_data' ) || 'company' !== get_post_type( $id ) ) { return; }
    $data = cleanup_data( $id ); $fields = cleanup_fields( 'company' );
    foreach ( array( 'partner_status', 'ownership_relation' ) as $key ) {
        $value = $data[ $key ] ?? '';
        if ( isset( $fields[ $key ][2][ $value ] ) && ! in_array( $value, array( 'none', 'independent' ), true ) ) { echo '<span class="badge badge-ad">' . esc_html( $fields[ $key ][2][ $value ] ) . '</span>'; }
    }
}
function portal_cards( $query ) {
    if ( ! $query->have_posts() ) { echo '<div class="empty"><p>掲載情報を準備しています。一次資料を確認できた情報から順次公開します。</p></div>'; return; }
    echo '<div class="card-grid">';
    while ( $query->have_posts() ) {
        $query->the_post();
        echo '<article class="card">'; portal_badges( get_the_ID() );
        echo '<h3><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3><p>' . esc_html( wp_trim_words( get_the_excerpt(), 42 ) ) . '</p><div class="metadata">更新：' . esc_html( get_the_modified_date( 'Y.m.d' ) ) . '</div></article>';
    }
    echo '</div>'; wp_reset_postdata();
}
function portal_section( $slug, $label, $description, $soft = false ) {
    $type = in_array( $slug, array( 'company', 'municipality' ), true ) ? $slug : 'article';
    $args = array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 3, 'no_found_rows' => true );
    if ( 'article' === $type ) { $args['tax_query'] = array( array( 'taxonomy' => 'article_type', 'field' => 'slug', 'terms' => $slug ) ); }
    if ( 'company' === $type ) { $args['meta_key'] = '_cleanup_sort_name'; $args['orderby'] = 'meta_value'; $args['order'] = 'ASC'; }
    echo '<section class="section' . ( $soft ? ' soft' : '' ) . '"><div class="wrap"><div class="section-head"><h2>' . esc_html( $label ) . '</h2><a href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">一覧を見る →</a></div><p>' . esc_html( $description ) . '</p>';
    portal_cards( new WP_Query( $args ) ); echo '</div></section>';
}
function portal_cta() {
    echo '<section class="cta"><div class="wrap"><div><h2>条件を確かめて、納得できる依頼へ。</h2><p>対応地域・料金条件・掲載区分を確認して業者を選びましょう。</p></div><a class="button button-yellow" href="' . esc_url( home_url( '/company/' ) ) . '">地域の業者を探す →</a></div></section>';
}
