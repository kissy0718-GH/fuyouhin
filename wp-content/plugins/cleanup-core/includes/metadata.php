<?php
defined( 'ABSPATH' ) || exit;
/** Field definitions are shared by validation, editing and public display. */
function cleanup_fields( $type ) {
    $fields = array(
        'source_url' => array( '一次資料URL', 'url' ),
        'source_title' => array( '資料名・発行主体', 'text' ),
        'source_published_at' => array( '資料の公開日（不明は空欄）', 'date' ),
        'last_verified_at' => array( '情報を確認した日', 'date' ),
        'source_notes' => array( '資料が裏付ける記述・追加資料URL', 'textarea' ),
    );
    if ( 'article' === $type ) {
        $fields['interview_reference'] = array( '実取材原本の管理番号（非公開）', 'private' );
        $fields['interview_permission'] = array( '取材内容の掲載許諾', 'select', array( 'unknown' => '未確認', 'yes' => '確認済み' ) );
        $fields['price_methodology'] = array( '料金の調査対象・期間・条件・変動要因', 'textarea' );
    }
    if ( 'municipality' === $type ) {
        foreach ( array( 'official_url' => '自治体公式URL', 'bulky_waste_url' => '粗大ごみ公式URL' ) as $key => $label ) {
            $fields[ $key ] = array( $label, 'url' );
        }
        foreach ( array( 'collection_method' => '回収方法', 'application_method' => '申込方法', 'accepted_items' => '回収できる品目', 'prohibited_items' => '回収できない品目', 'fee_information' => '料金と条件', 'carry_in_information' => '持ち込み情報', 'appliance_recycling_information' => '家電リサイクル情報' ) as $key => $label ) {
            $fields[ $key ] = array( $label, 'textarea' );
        }
    }
    if ( 'company' === $type ) {
        foreach ( array( 'company_name' => '法人名', 'brand_name' => 'サービス名', 'sort_name' => 'サービス名のよみ（ひらがな）', 'business_hours' => '営業時間・休業日', 'payment_methods' => '支払い方法' ) as $key => $label ) {
            $fields[ $key ] = array( $label, 'text' );
        }
        $fields['website'] = array( '公式サイト', 'url' );
        $fields['pricing'] = array( '料金・追加料金の条件', 'textarea' );
        foreach ( array( 'same_day' => '即日対応', 'night_service' => '夜間対応' ) as $key => $label ) {
            $fields[ $key ] = array( $label, 'select', array( 'unknown' => '未確認', 'yes' => '対応', 'no' => '非対応' ) );
        }
        $fields['listing_status'] = array( '掲載状態', 'select', array( 'draft' => '準備中', 'active' => '掲載可', 'suspended' => '掲載停止', 'archived' => '終了' ) );
        $fields['partner_status'] = array( '広告・提携区分', 'select', array( 'none' => '一般掲載', 'partner' => '提携業者（紹介料あり）', 'sponsor' => 'スポンサー', 'advertisement' => '広告掲載' ) );
        $fields['ownership_relation'] = array( '運営者との関係', 'select', array( 'unknown' => '未確認', 'own' => '運営者の自社サービス', 'affiliated' => '関連会社', 'independent' => '自社・関連会社以外' ) );
    }
    return $fields;
}
function cleanup_data( $id ) { $data = get_post_meta( $id, '_cleanup_data', true ); return is_array( $data ) ? $data : array(); }
function cleanup_validate_data( $input, $type ) {
    if ( ! is_array( $input ) ) { return new WP_Error( 'invalid_data', '項目の形式が不正です。' ); }
    $data = array();
    foreach ( cleanup_fields( $type ) as $key => $field ) {
        $value = $input[ $key ] ?? '';
        if ( ! is_scalar( $value ) || strlen( (string) $value ) > 12000 ) { return new WP_Error( 'invalid_field', $field[0] . 'の形式・長さを確認してください。' ); }
        $value = trim( (string) $value );
        if ( 'select' === $field[1] ) {
            $value = '' === $value ? array_key_first( $field[2] ) : $value;
            if ( ! array_key_exists( $value, $field[2] ) ) { return new WP_Error( 'invalid_choice', $field[0] . 'の値が不正です。' ); }
        } elseif ( 'url' === $field[1] && '' !== $value ) {
            if ( 'https' !== wp_parse_url( $value, PHP_URL_SCHEME ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) { return new WP_Error( 'invalid_url', $field[0] . 'は有効なHTTPS URLにしてください。' ); }
            $value = esc_url_raw( $value, array( 'https' ) );
        } elseif ( 'date' === $field[1] && '' !== $value ) {
            $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
            if ( ! $date || $date->format( 'Y-m-d' ) !== $value || $value > current_time( 'Y-m-d' ) ) { return new WP_Error( 'invalid_date', $field[0] . 'は実在する過去または今日の日付にしてください。' ); }
        } else {
            $value = 'textarea' === $field[1] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
        }
        $data[ $key ] = $value;
    }
    return $data;
}
add_action( 'rest_api_init', function () {
    foreach ( cleanup_types() as $type ) {
        register_rest_field( $type, 'cleanup_data', array(
            'schema' => array( 'type' => 'object', 'context' => array( 'edit' ), 'description' => '編集用の構造化情報。公開確認は管理画面で行います。' ),
            'get_callback' => function ( $post ) { return current_user_can( 'edit_post', $post['id'] ) ? cleanup_data( $post['id'] ) : array(); },
            'update_callback' => function ( $value, $post ) {
                if ( ! current_user_can( 'edit_post', $post->ID ) ) { return new WP_Error( 'forbidden', '編集権限がありません。', array( 'status' => 403 ) ); }
                $data = cleanup_validate_data( $value, $post->post_type );
                if ( is_wp_error( $data ) ) { return $data; }
                update_post_meta( $post->ID, '_cleanup_data', $data );
                return true;
            },
        ) );
    }
} );
