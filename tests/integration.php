<?php
if ( ! defined( 'CLEANUP_TESTING' ) ) { throw new RuntimeException( 'Disposable test environment only. Define CLEANUP_TESTING explicitly.' ); }
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
$checks = 0;
function check( $value, $message ) { global $checks; ++$checks; if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $message ); } echo 'PASS ' . $message . "\n"; }
foreach ( array( WP_PLUGIN_DIR . '/cleanup-core', get_theme_root() . '/cleanup-portal' ) as $directory ) {
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
        if ( $file->isFile() && 'php' === $file->getExtension() ) { token_get_all( file_get_contents( $file->getPathname() ), TOKEN_PARSE ); }
    }
}
check( true, 'all plugin and theme PHP files parse' );
check( $GLOBALS['wp_version'] === '7.1', 'pinned stable WordPress version' );
function fixture( $type, $slug ) {
    $id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'draft', 'post_title' => '検証専用 ' . $slug, 'post_name' => $slug, 'post_content' => '公開用ではない動作検証レコードです。' ) );
    $data = array( 'source_url' => 'https://example.org/source', 'source_title' => 'TEST ONLY', 'source_notes' => 'テスト用の根拠。実資料ではありません。', 'last_verified_at' => current_time( 'Y-m-d' ) );
    update_post_meta( $id, '_cleanup_data', cleanup_validate_data( $data, $type ) );
    return $id;
}
foreach ( cleanup_types() as $type ) { check( post_type_exists( $type ), 'CPT ' . $type ); }
check( count( get_terms( array( 'taxonomy' => 'article_type', 'hide_empty' => false ) ) ) === 5, 'five article types' );
cleanup_activate();
check( count( get_terms( array( 'taxonomy' => 'article_type', 'hide_empty' => false ) ) ) === 5, 'activation is idempotent' );
check( is_wp_error( cleanup_validate_data( array( 'source_url' => 'javascript:alert(1)' ), 'article' ) ), 'unsafe URL rejected' );
check( is_wp_error( cleanup_validate_data( array( 'last_verified_at' => '2099-01-01' ), 'article' ) ), 'future date rejected' );
check( is_wp_error( cleanup_validate_data( array( 'last_verified_at' => '2026-02-30' ), 'article' ) ), 'invalid calendar date rejected' );
check( is_wp_error( cleanup_validate_data( array( 'same_day' => 'maybe' ), 'company' ) ), 'invalid enum rejected' );
$article = fixture( 'article', 'test-scam' );
wp_update_post( array( 'ID' => $article, 'post_status' => 'publish' ) );
check( get_post_status( $article ) === 'pending', 'unreviewed publication blocked' );
check( is_wp_error( cleanup_approve( $article, cleanup_fingerprint( $article ) ) ), 'missing article type blocked' );
wp_set_object_terms( $article, array( 'scam', 'price' ), 'article_type' );
check( is_wp_error( cleanup_approve( $article, cleanup_fingerprint( $article ) ) ), 'multiple article types blocked' );
wp_set_object_terms( $article, 'scam', 'article_type' );
check( ! is_wp_error( cleanup_approve( $article, cleanup_fingerprint( $article ) ) ), 'human approval accepted' );
check( get_post_status( $article ) === 'publish', 'reviewed article published' );
check( str_contains( get_permalink( $article ), '/scam/test-scam/' ), 'article permalink' );
wp_update_post( array( 'ID' => $article, 'post_content' => '変更された内容' ) );
check( get_post_status( $article ) === 'pending', 'content change revokes publication' );
cleanup_approve( $article, cleanup_fingerprint( $article ) );
$data = cleanup_data( $article ); $data['source_notes'] = '別の出典説明'; update_post_meta( $article, '_cleanup_data', $data );
check( get_post_status( $article ) === 'pending', 'metadata change revokes publication' );
cleanup_approve( $article, cleanup_fingerprint( $article ) );
wp_set_object_terms( $article, 'staff', 'article_type' );
check( get_post_status( $article ) === 'pending', 'taxonomy change revokes publication' );
check( is_wp_error( cleanup_approve( $article, cleanup_fingerprint( $article ) ) ), 'invented staff testimony cannot publish' );
wp_publish_post( $article );
check( get_post_status( $article ) === 'pending', 'cron/direct wp_publish_post blocked' );
$editor = wp_insert_user( array( 'user_login' => 'test-editor', 'user_pass' => wp_generate_password(), 'role' => 'cleanup_editor' ) );
wp_set_current_user( $editor );
check( is_wp_error( cleanup_approve( $article, cleanup_fingerprint( $article ) ) ), 'editor cannot approve' );
wp_set_current_user( 1 );
wp_set_object_terms( $article, 'scam', 'article_type' );
check( is_wp_error( cleanup_approve( $article, 'outdated' ) ), 'stale approval rejected' );
$pref = wp_insert_term( '検証県', 'prefecture', array( 'slug' => 'test-pref' ) )['term_id'];
$city = wp_insert_term( '検証市', 'city', array( 'slug' => 'test-city' ) )['term_id'];
update_term_meta( $city, 'cleanup_prefecture_id', $pref ); update_term_meta( $city, 'cleanup_code', '99999' );
check( cleanup_city_path( $city ) === 'test-pref/test-city', 'region path' );
check( false !== cleanup_resolve_region( 'test-pref/test-city' ), 'valid region resolved' );
check( false === cleanup_resolve_region( 'wrong-pref/test-city' ), 'wrong prefecture rejected' );
$municipality = fixture( 'municipality', 'test-city-page' );
wp_set_object_terms( $municipality, array( $pref ), 'prefecture' ); wp_set_object_terms( $municipality, array( $city ), 'city' );
check( is_wp_error( cleanup_approve( $municipality, cleanup_fingerprint( $municipality ) ) ), 'incomplete municipality blocked' );
$data = cleanup_data( $municipality );
foreach ( cleanup_fields( 'municipality' ) as $key => $field ) { if ( empty( $data[ $key ] ) && ! in_array( $field[1], array( 'date' ), true ) ) { $data[ $key ] = 'url' === $field[1] ? 'https://example.org/test' : 'TEST ONLY'; } }
update_post_meta( $municipality, '_cleanup_data', $data );
check( ! is_wp_error( cleanup_approve( $municipality, cleanup_fingerprint( $municipality ) ) ), 'complete municipality accepted' );
check( get_post_status( $municipality ) === 'publish', 'municipality published' );
check( str_contains( get_permalink( $municipality ), '/municipality/test-pref/test-city/' ), 'municipality permalink' );
update_term_meta( $city, 'cleanup_prefecture_id', 0 );
check( get_post_status( $municipality ) === 'pending', 'region metadata change revokes publication' );
update_term_meta( $city, 'cleanup_prefecture_id', $pref );
$company = fixture( 'company', 'test-company' );
wp_set_object_terms( $company, array( $pref ), 'prefecture' );
wp_set_object_terms( $company, '検証サービス', 'service_type' );
$data = cleanup_data( $company );
foreach ( array( 'company_name', 'brand_name', 'sort_name', 'business_hours', 'pricing', 'payment_methods' ) as $key ) { $data[ $key ] = 'TEST ONLY'; }
$data['website'] = 'https://example.org/test'; $data['listing_status'] = 'active'; $data['ownership_relation'] = 'own'; $data['partner_status'] = 'partner';
update_post_meta( $company, '_cleanup_data', $data );
check( ! is_wp_error( cleanup_approve( $company, cleanup_fingerprint( $company ) ) ), 'company accepted under same review rules' );
check( get_post_status( $company ) === 'publish', 'company published' );
check( str_contains( get_permalink( $company ), '/company/detail/test-company/' ), 'company URL distinct from regional directory' );
$data['listing_status'] = 'suspended'; update_post_meta( $company, '_cleanup_data', $data );
check( get_post_status( $company ) === 'pending', 'suspended company unpublished' );
// Actual REST dispatch verifies capabilities and private data visibility.
wp_set_current_user( 0 );
$request = new WP_REST_Request( 'GET', '/wp/v2/article/' . $article );
$response = rest_do_request( $request );
check( $response->get_status() === 401 || $response->get_status() === 403, 'anonymous draft REST denied' );
wp_set_current_user( 1 ); cleanup_approve( $article, cleanup_fingerprint( $article ) ); wp_set_current_user( 0 );
$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/article/' . $article ) );
check( $response->get_status() === 200 && ! isset( $response->get_data()['cleanup_data'] ), 'public REST excludes editorial metadata' );
wp_set_current_user( $editor );
$request = new WP_REST_Request( 'POST', '/wp/v2/article/' . $article ); $request->set_param( 'content', 'REST changed content' );
$response = rest_do_request( $request );
check( $response->get_status() === 200 && get_post_status( $article ) === 'pending', 'REST edit invalidates published approval' );
wp_set_current_user( 1 );
// Keep only these disposable fixtures for the HTTP checks; preview never loads them.
cleanup_approve( $article, cleanup_fingerprint( $article ) );
cleanup_approve( $municipality, cleanup_fingerprint( $municipality ) );
$data = cleanup_data( $company ); $data['listing_status'] = 'active'; update_post_meta( $company, '_cleanup_data', $data ); cleanup_approve( $company, cleanup_fingerprint( $company ) );
$news = fixture( 'article', 'test-news' ); wp_update_post( array( 'ID' => $news, 'post_title' => 'TEST_NEWS_ONLY' ) ); wp_set_object_terms( $news, 'news', 'article_type' ); cleanup_approve( $news, cleanup_fingerprint( $news ) );
$fresh = wp_insert_post( array( 'post_type' => 'article', 'post_title' => 'Automatic slug', 'post_status' => 'draft' ) );
check( get_post_field( 'post_name', $fresh ) !== '', 'draft gets stable slug before review' );
require '/cleanup-tests/seo.php';
require '/cleanup-tests/ai.php';
require '/cleanup-tests/initial.php';
echo 'ALL TESTS PASSED (' . $checks . ")\n";
