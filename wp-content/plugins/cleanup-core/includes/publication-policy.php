<?php
defined( 'ABSPATH' ) || exit;
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
    if ( in_array( $data['post_type'], cleanup_types(), true ) && 'auto-draft' !== $data['post_status'] && '' !== trim( $data['post_title'] ) ) {
        $slug = $data['post_name'] ?: sanitize_title( $data['post_title'] );
        $data['post_name'] = wp_unique_post_slug( $slug, (int) ( $postarr['ID'] ?? 0 ), 'publish', $data['post_type'], 0 );
    }
    return $data;
}, 5, 2 );
function cleanup_fingerprint( $id, $override = array() ) {
    $post = get_post( $id );
    if ( ! $post ) { return ''; }
    $parts = array();
    foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_author' ) as $field ) { $parts[ $field ] = (string) ( $override[ $field ] ?? $post->$field ); }
    $parts['data'] = cleanup_data( $id );
    foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
        $ids = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
        if ( is_wp_error( $ids ) ) { $ids = array(); }
        sort( $ids );
        $parts[ $tax ] = $ids;
    }
    return hash( 'sha256', wp_json_encode( $parts ) );
}
function cleanup_review_errors( $id, $context = 'publish' ) {
    $post = get_post( $id ); $data = cleanup_data( $id ); $errors = array();
    if ( ! $post || ! in_array( $post->post_type, cleanup_types(), true ) ) { return array( '対象の記事がありません。' ); }
    if ( '' === trim( $post->post_title ) || '' === trim( wp_strip_all_tags( $post->post_content ) ) ) { $errors[] = 'タイトルと本文が必要です。'; }
    $valid = cleanup_validate_data( $data, $post->post_type );
    if ( is_wp_error( $valid ) ) { $errors[] = $valid->get_error_message(); }
    foreach ( array( 'source_url', 'source_title', 'last_verified_at', 'source_notes' ) as $key ) { if ( empty( $data[ $key ] ) ) { $errors[] = cleanup_fields( $post->post_type )[ $key ][0] . 'が必要です。'; } }
    $types = wp_get_object_terms( $id, 'article_type', array( 'fields' => 'slugs' ) );
    if ( 'article' === $post->post_type ) {
        if ( count( $types ) !== 1 || ! isset( cleanup_article_types()[ $types[0] ] ) ) { $errors[] = '記事の種類を規定の5種類から一つ選んでください。'; }
        if ( in_array( 'staff', $types, true ) && ( empty( $data['interview_reference'] ) || 'yes' !== ( $data['interview_permission'] ?? '' ) ) ) { $errors[] = '実取材の管理番号と掲載許諾が必要です。'; }
        if ( in_array( 'price', $types, true ) && empty( $data['price_methodology'] ) ) { $errors[] = '料金の調査条件・変動要因が必要です。'; }
    }
    $pref = wp_get_object_terms( $id, 'prefecture', array( 'fields' => 'ids' ) );
    $cities = wp_get_object_terms( $id, 'city' );
    if ( in_array( $post->post_type, array( 'municipality', 'company' ), true ) && empty( $pref ) ) { $errors[] = '都道府県が必要です。'; }
    if ( 'municipality' === $post->post_type && ( count( $pref ) !== 1 || count( $cities ) !== 1 ) ) { $errors[] = '自治体は都道府県と市区町村をそれぞれ一つ選んでください。'; }
    foreach ( $cities as $city ) {
        $pref_id = (int) get_term_meta( $city->term_id, 'cleanup_prefecture_id', true );
        if ( ! in_array( $pref_id, $pref, true ) || ! get_term_meta( $city->term_id, 'cleanup_code', true ) ) { $errors[] = '市区町村の所属都道府県・自治体コードを確認してください。'; }
        foreach ( get_ancestors( $city->term_id, 'city', 'taxonomy' ) as $parent ) { if ( $pref_id !== (int) get_term_meta( $parent, 'cleanup_prefecture_id', true ) ) { $errors[] = '市区町村の親階層が別の都道府県です。'; } }
    }
    if ( 'municipality' === $post->post_type ) {
        foreach ( array( 'official_url', 'bulky_waste_url', 'collection_method', 'application_method', 'accepted_items', 'prohibited_items', 'fee_information', 'carry_in_information', 'appliance_recycling_information' ) as $key ) { if ( empty( $data[ $key ] ) ) { $errors[] = cleanup_fields( 'municipality' )[ $key ][0] . 'が必要です（該当なしの場合は根拠を明記）。'; } }
        if ( count( $cities ) === 1 ) {
            $duplicates = get_posts( array( 'post_type' => 'municipality', 'post_status' => array( 'publish', 'future' ), 'post__not_in' => array( $id ), 'tax_query' => array( array( 'taxonomy' => 'city', 'terms' => array( $cities[0]->term_id ), 'include_children' => false ) ), 'fields' => 'ids' ) );
            if ( $duplicates ) { $errors[] = '同じ自治体の公開レコードが既にあります。'; }
        }
    }
    if ( 'company' === $post->post_type ) {
        foreach ( array( 'company_name', 'brand_name', 'sort_name', 'website', 'business_hours', 'pricing', 'payment_methods' ) as $key ) { if ( empty( $data[ $key ] ) ) { $errors[] = cleanup_fields( 'company' )[ $key ][0] . 'が必要です。'; } }
        if ( 'active' !== ( $data['listing_status'] ?? '' ) || 'unknown' === ( $data['ownership_relation'] ?? 'unknown' ) ) { $errors[] = '掲載可の状態と運営者との関係を確認してください。'; }
        if ( ! wp_get_object_terms( $id, 'service_type', array( 'fields' => 'ids' ) ) ) { $errors[] = '対応サービスを指定してください。'; }
    }
    return array_unique( apply_filters( 'cleanup_review_errors', $errors, $id, $context ) );
}
function cleanup_is_approved( $id, $override = array() ) {
    $hash = get_post_meta( $id, '_cleanup_approved_hash', true );
    return $hash && hash_equals( $hash, cleanup_fingerprint( $id, $override ) ) && ! cleanup_review_errors( $id );
}
function cleanup_invalidate( $id ) {
    static $busy = false;
    if ( $busy || ! in_array( get_post_type( $id ), cleanup_types(), true ) ) { return; }
    $busy = true;
    delete_post_meta( $id, '_cleanup_approved_hash' );
    delete_post_meta( $id, '_cleanup_index_hash' );
    update_post_meta( $id, '_cleanup_verification', 'needs_review' );
    if ( in_array( get_post_status( $id ), array( 'publish', 'future' ), true ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) ); }
    $busy = false;
}
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
    if ( in_array( $data['post_type'], cleanup_types(), true ) && in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
        if ( empty( $postarr['ID'] ) || ! cleanup_is_approved( $postarr['ID'], wp_unslash( $data ) ) ) { $data['post_status'] = 'pending'; }
    }
    return $data;
}, 99, 2 );
add_action( 'wp_after_insert_post', function ( $id, $post ) {
    if ( in_array( $post->post_type, cleanup_types(), true ) && get_post_meta( $id, '_cleanup_approved_hash', true ) && ! cleanup_is_approved( $id ) ) { cleanup_invalidate( $id ); }
}, 99, 2 );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
    add_action( $hook, function ( $meta_id, $id, $key ) { if ( '_cleanup_data' === $key ) { cleanup_invalidate( $id ); } }, 10, 3 );
}
add_action( 'set_object_terms', function ( $id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
    sort( $tt_ids ); sort( $old_tt_ids );
    if ( $tt_ids !== $old_tt_ids ) { cleanup_invalidate( $id ); }
}, 10, 6 );
add_action( 'deleted_term_relationships', function ( $id ) { cleanup_invalidate( $id ); } );
// wp_publish_post (cron) bypasses wp_insert_post_data; enforce again here.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
    if ( 'publish' === $new && in_array( $post->post_type, cleanup_types(), true ) && ! cleanup_is_approved( $post->ID ) ) { cleanup_invalidate( $post->ID ); }
}, 1, 3 );
function cleanup_approve( $id, $expected_hash ) {
    if ( ! current_user_can( 'cleanup_review' ) || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'forbidden', '公開確認権限がありません。' ); }
    if ( ! hash_equals( cleanup_fingerprint( $id ), (string) $expected_hash ) ) { return new WP_Error( 'conflict', '確認画面を開いた後に内容が変わりました。再読込してください。' ); }
    $errors = cleanup_review_errors( $id );
    if ( $errors ) { return new WP_Error( 'incomplete', implode( ' ', $errors ) ); }
    update_post_meta( $id, '_cleanup_approved_hash', $expected_hash );
    update_post_meta( $id, '_cleanup_verified_by', get_current_user_id() );
    update_post_meta( $id, '_cleanup_verified_at', gmdate( 'c' ) );
    update_post_meta( $id, '_cleanup_verification', 'verified' );
    add_post_meta( $id, '_cleanup_review_history', array( 'hash' => $expected_hash, 'actor' => get_current_user_id(), 'at' => gmdate( 'c' ) ) );
    return wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
}
