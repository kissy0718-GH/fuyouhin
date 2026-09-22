<?php
defined( 'ABSPATH' ) || exit;

/** One eligibility rule for robots and sitemaps; approval binds to exact content. */
function cleanup_set_indexable( $id, $enabled ) {
    if ( ! current_user_can( 'cleanup_review' ) || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'forbidden', '公開確認権限が必要です。' ); }
    if ( ! $enabled ) { delete_post_meta( $id, '_cleanup_index_hash' ); return true; }
    if ( 'publish' !== get_post_status( $id ) || ! cleanup_is_approved( $id ) ) { return new WP_Error( 'unreviewed', '内容を確認して公開してください。' ); }
    update_post_meta( $id, '_cleanup_index_hash', cleanup_fingerprint( $id ) );
    return true;
}
function cleanup_indexable_post( $id ) {
    if ( 'publish' !== get_post_status( $id ) || ! in_array( get_post_type( $id ), cleanup_types(), true ) || ! cleanup_is_approved( $id ) ) { return false; }
    $date = cleanup_data( $id )['last_verified_at'] ?? '';
    if ( ! $date || $date < wp_date( 'Y-m-d', strtotime( '-90 days' ) ) ) { return false; }
    $hash = get_post_meta( $id, '_cleanup_index_hash', true );
    return $hash && hash_equals( $hash, cleanup_fingerprint( $id ) );
}
function cleanup_section_labels() { return array_merge( cleanup_article_types(), array( 'municipality' => '自治体の回収情報', 'company' => '全国業者一覧' ) ); }

/** Returns query arguments only for real, supported archive routes. */
function cleanup_archive_args( $path ) {
    if ( '/' === $path ) { return array( 'post_type' => cleanup_types() ); }
    if ( ! preg_match( '#^/([a-z-]+)(?:/([a-z0-9/-]+))?/$#', $path, $match ) ) { return false; }
    $section = $match[1]; $region_path = trim( $match[2] ?? '', '/' );
    if ( isset( cleanup_article_types()[ $section ] ) && ! $region_path ) { return array( 'post_type' => 'article', 'tax_query' => array( array( 'taxonomy' => 'article_type', 'field' => 'slug', 'terms' => $section ) ) ); }
    if ( ! in_array( $section, array( 'company', 'municipality' ), true ) ) { return false; }
    $args = array( 'post_type' => $section );
    if ( $region_path ) {
        $region = cleanup_resolve_region( $region_path );
        if ( ! $region || ( 'municipality' === $section && $region['city'] ) ) { return false; }
        $args['tax_query'] = array( array( 'taxonomy' => 'prefecture', 'terms' => $region['prefecture']->term_id ) );
        if ( $region['city'] ) { $args['tax_query'][] = array( 'taxonomy' => 'city', 'terms' => $region['city']->term_id ); }
    }
    return $args;
}
function cleanup_archive_members( $path, $indexable_only = true ) {
    $args = cleanup_archive_args( $path );
    if ( false === $args ) { return array(); }
    $ids = get_posts( array_merge( $args, array( 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) ) );
    return array_values( array_filter( $ids, $indexable_only ? 'cleanup_indexable_post' : 'cleanup_is_approved' ) );
}
function cleanup_archive_hash( $path, $record ) {
    $members = array();
    foreach ( cleanup_archive_members( $path, false ) as $id ) { $members[ $id ] = array( cleanup_fingerprint( $id ), (bool) cleanup_indexable_post( $id ) ); }
    return hash( 'sha256', wp_json_encode( array( $path, $record['title'] ?? '', $record['intro'] ?? '', $members ) ) );
}
function cleanup_review_archive( $path, $title, $intro ) {
    if ( ! current_user_can( 'cleanup_review' ) ) { return new WP_Error( 'forbidden', '公開確認権限が必要です。' ); }
    $path = '/' . trim( $path, '/' ) . '/'; if ( '//' === $path ) { $path = '/'; }
    if ( false === cleanup_archive_args( $path ) || ! cleanup_archive_members( $path ) || ! trim( $title ) || ! trim( $intro ) ) { return new WP_Error( 'incomplete', '有効な一覧URL、検索掲載対象の情報、固有のタイトル・紹介文が必要です。' ); }
    $record = array( 'title' => sanitize_text_field( $title ), 'intro' => sanitize_textarea_field( $intro ), 'reviewer' => get_current_user_id(), 'at' => gmdate( 'c' ) );
    $record['hash'] = cleanup_archive_hash( $path, $record );
    $records = get_option( 'cleanup_seo_archives', array() ); $records[ $path ] = $record;
    update_option( 'cleanup_seo_archives', $records, false );
    return true;
}
function cleanup_indexable_archive( $path ) {
    $record = get_option( 'cleanup_seo_archives', array() )[ $path ] ?? array();
    return ! empty( $record['hash'] ) && cleanup_archive_members( $path ) && hash_equals( $record['hash'], cleanup_archive_hash( $path, $record ) );
}
function cleanup_current_archive_path() {
    if ( is_front_page() ) { return '/'; }
    if ( is_singular() || is_404() || is_search() ) { return ''; }
    $section = get_query_var( 'cleanup_article_type' ) ?: get_query_var( 'post_type' );
    if ( ! is_string( $section ) || ! isset( cleanup_section_labels()[ $section ] ) ) { return ''; }
    $region = get_query_var( 'cleanup_region' );
    return '/' . $section . '/' . ( $region ? trim( $region, '/' ) . '/' : '' );
}
function cleanup_canonical_url() {
    if ( is_404() || is_search() || is_preview() || is_feed() ) { return ''; }
    if ( is_singular( cleanup_types() ) ) { return get_permalink( get_queried_object_id() ); }
    $path = cleanup_current_archive_path();
    if ( ! $path ) { return ''; }
    $page = max( 1, (int) get_query_var( 'paged' ) );
    return home_url( $path . ( $page > 1 ? 'page/' . $page . '/' : '' ) );
}
function cleanup_current_indexable() {
    if ( ! get_option( 'blog_public' ) || is_404() || is_search() || is_preview() || is_feed() || ! empty( $_GET ) ) { return false; }
    if ( is_singular( cleanup_types() ) ) { return cleanup_indexable_post( get_queried_object_id() ); }
    global $wp_query;
    $path = cleanup_current_archive_path();
    return $path && ( '/' === $path || $wp_query->post_count > 0 ) && cleanup_indexable_archive( $path );
}
add_filter( 'wp_robots', function ( $robots ) {
    if ( cleanup_current_indexable() ) { unset( $robots['noindex'], $robots['nofollow'] ); $robots['index'] = true; $robots['follow'] = true; }
    else { unset( $robots['index'] ); $robots['noindex'] = true; }
    return $robots;
}, 99 );

function cleanup_breadcrumbs() {
    $items = array( array( 'name' => 'ホーム', 'url' => home_url( '/' ) ) );
    $url = cleanup_canonical_url();
    if ( ! $url || is_front_page() ) { return $items; }
    $relative = trim( str_replace( trailingslashit( home_url( '/' ) ), '', $url ), '/' );
    $parts = explode( '/', $relative ); $section = $parts[0];
    if ( isset( cleanup_section_labels()[ $section ] ) ) { $items[] = array( 'name' => cleanup_section_labels()[ $section ], 'url' => home_url( '/' . $section . '/' ) ); }
    if ( in_array( $section, array( 'municipality', 'company' ), true ) && isset( $parts[1] ) && 'detail' !== $parts[1] && 'page' !== $parts[1] ) {
        $prefix = '';
        foreach ( array_slice( $parts, 1 ) as $part ) {
            if ( 'page' === $part ) { break; }
            $prefix .= ( $prefix ? '/' : '' ) . $part; $region = cleanup_resolve_region( $prefix );
            if ( $region ) { $items[] = array( 'name' => $region['city'] ? $region['city']->name : $region['prefecture']->name, 'url' => home_url( '/' . $section . '/' . $prefix . '/' ) ); }
        }
    }
    if ( is_singular() ) { $items[] = array( 'name' => get_the_title( get_queried_object_id() ), 'url' => $url ); }
    $unique = array(); foreach ( $items as $item ) { $unique[ $item['url'] ] = $item; }
    return array_values( $unique );
}
function cleanup_render_breadcrumbs() {
    $items = cleanup_breadcrumbs(); echo '<nav class="breadcrumbs" aria-label="パンくず">';
    foreach ( $items as $index => $item ) {
        if ( $index ) { echo ' / '; }
        if ( $index === count( $items ) - 1 ) { echo '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>'; }
        else { echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>'; }
    }
    echo '</nav>';
}
add_action( 'wp', function () { if ( cleanup_canonical_url() ) { remove_action( 'wp_head', 'rel_canonical' ); } } );
add_action( 'wp_head', function () {
    $url = cleanup_canonical_url(); if ( ! $url ) { return; }
    echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
    $archive = get_option( 'cleanup_seo_archives', array() )[ cleanup_current_archive_path() ] ?? array();
    $description = is_singular() ? get_post_field( 'post_excerpt', get_queried_object_id() ) : ( $archive['intro'] ?? get_bloginfo( 'description' ) );
    if ( is_singular() && ! $description ) { $description = wp_html_excerpt( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', get_queried_object_id() ) ) ), 160, '…' ); }
    if ( $description ) { echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $description ) ) . '">' . "\n"; }
    $graph = array(); $crumbs = array();
    foreach ( cleanup_breadcrumbs() as $i => $item ) { $crumbs[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => $item['url'] ); }
    if ( count( $crumbs ) > 1 ) { $graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs ); }
    if ( is_singular( 'article' ) && cleanup_is_approved( get_queried_object_id() ) ) {
        $id = get_queried_object_id(); $author = get_userdata( (int) get_post_field( 'post_author', $id ) );
        $article = array( '@type' => 'Article', 'headline' => get_the_title( $id ), 'mainEntityOfPage' => $url, 'datePublished' => get_post_time( DATE_W3C, true, $id ), 'dateModified' => get_post_modified_time( DATE_W3C, true, $id ) );
        if ( $author ) { $article['author'] = array( '@type' => 'Person', 'name' => $author->display_name ); }
        $graph[] = $article;
    }
    // Only a human-supplied operator name may become Organization markup.
    $operator = get_option( 'cleanup_operator_name', '' );
    if ( $operator && is_front_page() ) { $graph[] = array( '@type' => 'Organization', 'name' => $operator, 'url' => home_url( '/' ) ); }
    if ( $graph ) { echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n"; }
}, 5 );
add_filter( 'document_title_parts', function ( $parts ) {
    $record = get_option( 'cleanup_seo_archives', array() )[ cleanup_current_archive_path() ] ?? array();
    if ( ! empty( $record['title'] ) ) { $parts['title'] = $record['title']; }
    return $parts;
} );

/** Preserve only previously approved public paths. Never redirect to drafts. */
add_action( 'init', function () {
    if ( get_option( 'cleanup_url_history_ready' ) ) { return; }
    $posts = get_posts( array( 'post_type' => cleanup_types(), 'post_status' => 'publish', 'numberposts' => 50, 'meta_query' => array( array( 'key' => '_cleanup_last_public_path', 'compare' => 'NOT EXISTS' ) ) ) );
    foreach ( $posts as $post ) { update_post_meta( $post->ID, '_cleanup_last_public_path', wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ); }
    if ( count( $posts ) < 50 ) { update_option( 'cleanup_url_history_ready', 1, false ); }
}, 30 );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
    if ( 'publish' !== $new || ! in_array( $post->post_type, cleanup_types(), true ) || ! cleanup_is_approved( $post->ID ) ) { return; }
    $path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
    $last = get_post_meta( $post->ID, '_cleanup_last_public_path', true );
    if ( $last && $last !== $path ) { add_post_meta( $post->ID, '_cleanup_old_path', $last ); }
    update_post_meta( $post->ID, '_cleanup_last_public_path', $path );
}, 20, 3 );
function cleanup_redirect_target( $path ) {
    $posts = get_posts( array( 'post_type' => cleanup_types(), 'post_status' => 'publish', 'meta_key' => '_cleanup_old_path', 'meta_value' => $path, 'numberposts' => 2 ) );
    if ( count( $posts ) !== 1 || ! cleanup_is_approved( $posts[0]->ID ) ) { return ''; }
    $target = get_permalink( $posts[0] );
    return wp_parse_url( $target, PHP_URL_PATH ) !== $path ? $target : '';
}
add_action( 'template_redirect', function () {
    if ( ! is_404() ) { return; }
    $path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
    $target = cleanup_redirect_target( $path );
    if ( $target ) { wp_safe_redirect( $target, 301 ); exit; }
}, 1 );

class Cleanup_Sitemap_Provider extends WP_Sitemaps_Provider {
    public function __construct() { $this->name = 'cleanup'; $this->object_type = 'cleanup'; }
    private function entries() {
        $entries = array();
        foreach ( cleanup_archive_members( '/' ) as $id ) { $entries[] = array( 'loc' => get_permalink( $id ), 'lastmod' => get_post_modified_time( DATE_W3C, true, $id ) ); }
        foreach ( get_option( 'cleanup_seo_archives', array() ) as $path => $record ) { if ( cleanup_indexable_archive( $path ) ) { $entries[] = array( 'loc' => home_url( $path ) ); } }
        return $entries;
    }
    public function get_url_list( $page_num, $object_subtype = '' ) { return array_slice( $this->entries(), ( max( 1, (int) $page_num ) - 1 ) * 1000, 1000 ); }
    public function get_max_num_pages( $object_subtype = '' ) { return (int) ceil( count( $this->entries() ) / 1000 ); }
}
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) { return 'cleanup' === $name ? $provider : false; }, 10, 2 );
add_action( 'init', function () { wp_register_sitemap_provider( 'cleanup', new Cleanup_Sitemap_Provider() ); }, 20 );

function cleanup_related_posts( $id ) {
    $tax_query = array( 'relation' => 'OR' );
    foreach ( array( 'item', 'city', 'service_type', 'article_type' ) as $tax ) {
        $ids = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
        if ( $ids && ! is_wp_error( $ids ) ) { $tax_query[] = array( 'taxonomy' => $tax, 'terms' => $ids ); }
    }
    if ( count( $tax_query ) === 1 ) { return array(); }
    return get_posts( array( 'post_type' => 'article', 'post_status' => 'publish', 'post__not_in' => array( $id ), 'posts_per_page' => 3, 'tax_query' => $tax_query ) );
}

add_action( 'admin_menu', function () { add_submenu_page( 'cleanup-dashboard', 'SEO公開管理', 'SEO公開管理', 'cleanup_review', 'cleanup-seo', 'cleanup_seo_screen' ); } );
function cleanup_seo_screen() {
    if ( ! current_user_can( 'cleanup_review' ) ) { return; }
    if ( isset( $_POST['cleanup_seo_nonce'] ) ) {
        check_admin_referer( 'cleanup_seo', 'cleanup_seo_nonce' );
        $path = sanitize_text_field( wp_unslash( $_POST['archive_path'] ?? '' ) );
        if ( 'remove' === ( $_POST['operation'] ?? '' ) ) {
            $records = get_option( 'cleanup_seo_archives', array() ); unset( $records[ $path ] ); update_option( 'cleanup_seo_archives', $records, false ); $result = true;
        } else {
            $result = cleanup_review_archive( $path, sanitize_text_field( wp_unslash( $_POST['archive_title'] ?? '' ) ), sanitize_textarea_field( wp_unslash( $_POST['archive_intro'] ?? '' ) ) );
        }
        echo '<div class="notice"><p>' . esc_html( is_wp_error( $result ) ? $result->get_error_message() : '更新しました。' ) . '</p></div>';
    }
    if ( isset( $_POST['operator_nonce'] ) && current_user_can( 'manage_options' ) ) {
        check_admin_referer( 'cleanup_operator', 'operator_nonce' ); update_option( 'cleanup_operator_name', sanitize_text_field( wp_unslash( $_POST['operator_name'] ?? '' ) ) );
    }
    echo '<div class="wrap"><h1>SEO公開管理</h1><p>サイト全体の検索エンジン設定：' . esc_html( get_option( 'blog_public' ) ? '掲載許可' : '掲載拒否（noindex）' ) . '</p><p>個別情報は公開確認画面で検索掲載対象に指定します。一覧は固有の説明文と掲載対象の情報が揃ってから審査してください。一覧の対象内容が変わると再審査が必要です。</p><form method="post">';
    wp_nonce_field( 'cleanup_seo', 'cleanup_seo_nonce' );
    echo '<p><label>一覧パス（例 /company/tokyo/）<br><input name="archive_path" class="regular-text" required></label></p><p><label>タイトル<br><input name="archive_title" class="large-text" required></label></p><p><label>この地域・カテゴリー固有の紹介文<br><textarea name="archive_intro" class="large-text" rows="5" required></textarea></label></p><p><label><input type="checkbox" required>対象情報と固有の解説を確認しました</label></p>';
    submit_button( '一覧を検索掲載対象として審査' ); echo '</form><h2>審査した一覧</h2>';
    foreach ( get_option( 'cleanup_seo_archives', array() ) as $path => $record ) {
        echo '<p><a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $path ) . '</a>：' . esc_html( cleanup_indexable_archive( $path ) ? '審査済み' : '再審査が必要' ) . '</p><form method="post">';
        wp_nonce_field( 'cleanup_seo', 'cleanup_seo_nonce' ); echo '<input type="hidden" name="archive_path" value="' . esc_attr( $path ) . '"><input type="hidden" name="operation" value="remove"><button class="button">検索掲載対象から外す</button></form>';
    }
    if ( current_user_can( 'manage_options' ) ) {
        echo '<h2>運営者（Organization）</h2><p>運営者情報ページと一致する実際の名称を入力してください。空欄なら構造化データを出力しません。</p><form method="post">'; wp_nonce_field( 'cleanup_operator', 'operator_nonce' );
        echo '<label>運営者名 <input name="operator_name" class="regular-text" value="' . esc_attr( get_option( 'cleanup_operator_name', '' ) ) . '"></label>'; submit_button( '保存' ); echo '</form>';
    }
    echo '</div>';
}
