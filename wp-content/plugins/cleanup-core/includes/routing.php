<?php
defined( 'ABSPATH' ) || exit;
add_action( 'init', 'cleanup_rewrite_rules' );
function cleanup_rewrite_rules() {
    add_rewrite_rule( '^(scam|industry|staff|news|price)/page/([0-9]+)/?$', 'index.php?post_type=article&cleanup_article_type=$matches[1]&paged=$matches[2]', 'top' );
    add_rewrite_rule( '^(scam|industry|staff|news|price)/([^/]+)/?$', 'index.php?post_type=article&cleanup_article_type=$matches[1]&name=$matches[2]', 'top' );
    add_rewrite_rule( '^(scam|industry|staff|news|price)/?$', 'index.php?post_type=article&cleanup_article_type=$matches[1]', 'top' );
    add_rewrite_rule( '^company/detail/([^/]+)/?$', 'index.php?post_type=company&name=$matches[1]', 'top' );
    foreach ( array( 'company', 'municipality' ) as $type ) {
        add_rewrite_rule( '^' . $type . '/?$', 'index.php?post_type=' . $type, 'top' );
        add_rewrite_rule( '^' . $type . '/page/([0-9]+)/?$', 'index.php?post_type=' . $type . '&paged=$matches[1]', 'top' );
        add_rewrite_rule( '^' . $type . '/((?!detail(?:/|$)|page(?:/|$))[^/]+(?:/[^/]+){0,2})/page/([0-9]+)/?$', 'index.php?post_type=' . $type . '&cleanup_region=$matches[1]&paged=$matches[2]', 'top' );
        add_rewrite_rule( '^' . $type . '/((?!detail(?:/|$)|page(?:/|$))[^/]+(?:/[^/]+){0,2})/?$', 'index.php?post_type=' . $type . '&cleanup_region=$matches[1]', 'top' );
    }
}
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'cleanup_region'; $vars[] = 'cleanup_article_type'; $vars[] = 'service'; return $vars; } );
function cleanup_city_path( $city_id ) {
    $city = get_term( $city_id, 'city' );
    if ( ! $city || is_wp_error( $city ) ) { return ''; }
    $pref = get_term( (int) get_term_meta( $city_id, 'cleanup_prefecture_id', true ), 'prefecture' );
    if ( ! $pref || is_wp_error( $pref ) ) { return ''; }
    $slugs = array( $pref->slug );
    foreach ( array_reverse( get_ancestors( $city_id, 'city', 'taxonomy' ) ) as $parent ) { $slugs[] = get_term( $parent, 'city' )->slug; }
    $slugs[] = $city->slug;
    return implode( '/', $slugs );
}
function cleanup_resolve_region( $path ) {
    if ( ! is_string( $path ) ) { return false; }
    $parts = explode( '/', trim( $path, '/' ) );
    $pref = get_term_by( 'slug', array_shift( $parts ), 'prefecture' );
    if ( ! $pref ) { return false; }
    $parent = 0; $city = null;
    foreach ( $parts as $slug ) {
        $terms = get_terms( array( 'taxonomy' => 'city', 'slug' => $slug, 'parent' => $parent, 'hide_empty' => false, 'meta_query' => array( array( 'key' => 'cleanup_prefecture_id', 'value' => $pref->term_id ) ) ) );
        if ( is_wp_error( $terms ) || count( $terms ) !== 1 ) { return false; }
        $city = $terms[0]; $parent = $city->term_id;
    }
    return array( 'prefecture' => $pref, 'city' => $city );
}
add_filter( 'post_type_link', function ( $url, $post ) {
    if ( 'article' === $post->post_type ) {
        $types = wp_get_object_terms( $post->ID, 'article_type', array( 'fields' => 'slugs' ) );
        if ( count( $types ) === 1 && isset( cleanup_article_types()[ $types[0] ] ) ) { return home_url( '/' . $types[0] . '/' . $post->post_name . '/' ); }
    }
    if ( 'company' === $post->post_type ) { return home_url( '/company/detail/' . $post->post_name . '/' ); }
    if ( 'municipality' === $post->post_type ) {
        $cities = wp_get_object_terms( $post->ID, 'city', array( 'fields' => 'ids' ) );
        if ( count( $cities ) === 1 && cleanup_city_path( $cities[0] ) ) { return home_url( '/municipality/' . cleanup_city_path( $cities[0] ) . '/' ); }
    }
    return $url;
}, 10, 2 );
add_action( 'parse_request', function ( $wp ) {
    foreach ( array( 'cleanup_region', 'cleanup_article_type', 'service' ) as $key ) {
        if ( isset( $wp->query_vars[ $key ] ) && ! is_string( $wp->query_vars[ $key ] ) ) { $wp->query_vars = array( 'error' => '404' ); return; }
    }
    if ( empty( $wp->query_vars['cleanup_region'] ) ) { return; }
    $region = cleanup_resolve_region( $wp->query_vars['cleanup_region'] );
    if ( ! $region ) { $wp->query_vars = array( 'error' => '404' ); return; }
    if ( 'municipality' === ( $wp->query_vars['post_type'] ?? '' ) && $region['city'] ) {
        $posts = get_posts( array( 'post_type' => 'municipality', 'post_status' => 'publish', 'tax_query' => array( array( 'taxonomy' => 'city', 'terms' => $region['city']->term_id, 'include_children' => false ) ), 'numberposts' => 1 ) );
        if ( $posts && empty( $wp->query_vars['paged'] ) ) { $wp->query_vars['p'] = $posts[0]->ID; }
        else { $wp->query_vars = array( 'error' => '404' ); }
    }
} );
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) { return; }
    if ( $query->get( 'cleanup_article_type' ) ) {
        $query->set( 'tax_query', array( array( 'taxonomy' => 'article_type', 'field' => 'slug', 'terms' => $query->get( 'cleanup_article_type' ) ) ) );
        if ( ! $query->get( 'name' ) ) {
            $query->is_home = false;
            $query->is_archive = true;
            $query->is_post_type_archive = true;
        }
    }
    $region_path = $query->get( 'cleanup_region' );
    if ( $region_path && ! $query->get( 'p' ) ) {
        $region = cleanup_resolve_region( $region_path );
        if ( $region ) {
            $tax = array( array( 'taxonomy' => 'prefecture', 'terms' => $region['prefecture']->term_id ) );
            if ( $region['city'] ) { $tax[] = array( 'taxonomy' => 'city', 'terms' => $region['city']->term_id, 'include_children' => true ); }
            $query->set( 'tax_query', $tax );
        }
    }
    if ( $query->is_search() ) { $query->set( 'post_type', cleanup_types() ); }
    if ( 'company' === $query->get( 'post_type' ) && ! $query->is_singular() && $query->get( 'service' ) ) {
        $tax = (array) $query->get( 'tax_query' );
        $tax[] = array( 'taxonomy' => 'service_type', 'field' => 'slug', 'terms' => sanitize_title( $query->get( 'service' ) ) );
        $query->set( 'tax_query', $tax );
    }
    if ( ! $query->is_singular() && in_array( $query->get( 'post_type' ), array( 'company', 'municipality' ), true ) ) {
        $query->set( 'orderby', 'title' ); $query->set( 'order', 'ASC' );
        if ( 'company' === $query->get( 'post_type' ) ) { $query->set( 'meta_key', '_cleanup_sort_name' ); $query->set( 'orderby', 'meta_value' ); }
    }
} );
