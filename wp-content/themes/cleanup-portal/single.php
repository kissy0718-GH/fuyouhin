<?php get_header(); ?>
<main id="main"><?php while ( have_posts() ) { the_post(); $data = function_exists( 'cleanup_data' ) ? cleanup_data( get_the_ID() ) : array(); ?>
<header class="page-header"><div class="wrap"><?php if ( function_exists( 'cleanup_render_breadcrumbs' ) ) { cleanup_render_breadcrumbs(); } ?><?php portal_badges( get_the_ID() ); ?><h1><?php the_title(); ?></h1><div class="metadata">公開：<?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?> / 更新：<?php echo esc_html( get_the_modified_date( 'Y.m.d' ) ); ?> / 著者：<?php echo esc_html( get_the_author() ); ?></div></div></header>
<article class="reading"><?php if ( ! empty( $data['last_verified_at'] ) && strtotime( $data['last_verified_at'] ) < strtotime( '-90 days' ) ) { ?><div class="notice">前回の確認から90日以上経過しています。最新の条件は公式情報でご確認ください。</div><?php } ?>
<?php the_content(); ?>
<?php if ( function_exists( 'cleanup_fields' ) && in_array( get_post_type(), array( 'company', 'municipality' ), true ) ) {
    echo '<h2>基本情報</h2><dl class="facts">';
    foreach ( array( 'prefecture' => '都道府県', 'city' => '市区町村', 'service_type' => '対応サービス', 'item' => '対応品目' ) as $tax => $label ) {
        $names = wp_get_object_terms( get_the_ID(), $tax, array( 'fields' => 'names' ) );
        if ( $names ) { echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( implode( '、', $names ) ) . '</dd>'; }
    }
    foreach ( cleanup_fields( get_post_type() ) as $key => $field ) {
        if ( in_array( $key, array( 'source_url', 'source_title', 'source_published_at', 'source_notes', 'last_verified_at', 'sort_name', 'listing_status' ), true ) || 'private' === $field[1] ) { continue; }
        $value = $data[ $key ] ?? ''; if ( 'select' === $field[1] ) { $value = $field[2][ $value ] ?? '未確認'; }
        echo '<dt>' . esc_html( $field[0] ) . '</dt><dd>';
        if ( 'url' === $field[1] && $value ) { echo '<a rel="noopener" href="' . esc_url( $value ) . '">公式情報を確認する ↗</a>'; }
        else { echo nl2br( esc_html( $value ?: '未確認' ) ); }
        echo '</dd>';
    }
    echo '</dl>';
} ?>
<?php if ( ! empty( $data['price_methodology'] ) ) { ?><h2>料金情報の調査条件</h2><p><?php echo nl2br( esc_html( $data['price_methodology'] ) ); ?></p><?php } ?>
<aside class="sources"><strong>情報源と確認日</strong><p><?php if ( ! empty( $data['source_url'] ) ) { ?><a href="<?php echo esc_url( $data['source_url'] ); ?>" rel="noopener"><?php echo esc_html( $data['source_title'] ?? '一次資料' ); ?></a><?php } ?><br>最終確認日：<?php echo esc_html( $data['last_verified_at'] ?? '未確認' ); ?><br>確認担当：<?php $reviewer = get_userdata( (int) get_post_meta( get_the_ID(), '_cleanup_verified_by', true ) ); echo esc_html( $reviewer ? $reviewer->display_name : '未確認' ); ?></p><p><?php echo nl2br( esc_html( $data['source_notes'] ?? '' ) ); ?></p></aside>
<?php if ( function_exists( 'cleanup_ai_public_sources' ) ) { $ai_sources = cleanup_ai_public_sources( get_the_ID() ); if ( count( $ai_sources ) > 1 ) { echo '<aside class="sources"><strong>制作時に参照した資料</strong><ul>'; foreach ( $ai_sources as $ai_source ) { echo '<li><a href="' . esc_url( $ai_source['url'] ) . '" rel="noopener">' . esc_html( $ai_source['title'] ) . '</a></li>'; } echo '</ul></aside>'; } } ?>
<p><a href="<?php echo esc_url( home_url( '/municipality/' ) ); ?>">自治体で処分する方法も確認する →</a></p><?php if ( function_exists( 'cleanup_related_posts' ) ) { $related = cleanup_related_posts( get_the_ID() ); if ( $related ) { echo '<aside><h2>あわせて読みたい情報</h2><ul>'; foreach ( $related as $related_post ) { echo '<li><a href="' . esc_url( get_permalink( $related_post ) ) . '">' . esc_html( $related_post->post_title ) . '</a></li>'; } echo '</ul></aside>'; } } ?></article>
<?php } portal_cta(); ?></main><?php get_footer(); ?>
