<?php
get_header(); $type = get_query_var( 'post_type' ); $article_type = get_query_var( 'cleanup_article_type' );
$title = portal_categories()[ is_string( $type ) ? $type : '' ] ?? '記事一覧';
if ( is_string( $article_type ) && isset( portal_categories()[ $article_type ] ) ) { $title = portal_categories()[ $article_type ]; }
$region = function_exists( 'cleanup_resolve_region' ) && get_query_var( 'cleanup_region' ) ? cleanup_resolve_region( get_query_var( 'cleanup_region' ) ) : false;
if ( $region ) { $title = ( $region['city'] ? $region['city']->name : $region['prefecture']->name ) . 'の' . $title; }
$seo_record = function_exists( 'cleanup_current_archive_path' ) ? ( get_option( 'cleanup_seo_archives', array() )[ cleanup_current_archive_path() ] ?? array() ) : array();
if ( ! empty( $seo_record['title'] ) ) { $title = $seo_record['title']; }
?>
<main id="main"><header class="page-header"><div class="wrap"><?php if ( function_exists( 'cleanup_render_breadcrumbs' ) ) { cleanup_render_breadcrumbs(); } ?><h1><?php echo esc_html( $title ); ?></h1><p>出典と確認日を確かめながら、ご自身の条件に合う情報をお探しください。</p></div></header><section class="section"><div class="wrap">
<?php if ( ! empty( $seo_record['intro'] ) ) { echo '<div class="archive-intro">' . wpautop( esc_html( $seo_record['intro'] ) ) . '</div>'; } ?>
<?php if ( 'company' === $type && taxonomy_exists( 'service_type' ) ) {
    $services = get_terms( array( 'taxonomy' => 'service_type', 'hide_empty' => true ) );
    if ( $services ) {
        $filter_path = '/company/' . ( get_query_var( 'cleanup_region' ) ? trim( get_query_var( 'cleanup_region' ), '/' ) . '/' : '' );
        echo '<form class="filter" method="get" action="' . esc_url( home_url( $filter_path ) ) . '"><label for="service">サービスから絞り込む<select id="service" name="service"><option value="">すべてのサービス</option>';
        foreach ( $services as $service ) { echo '<option value="' . esc_attr( $service->slug ) . '" ' . selected( get_query_var( 'service' ), $service->slug, false ) . '>' . esc_html( $service->name ) . '</option>'; }
        echo '</select></label><button class="button" type="submit">絞り込む</button></form>';
    }
} ?>
<?php if ( in_array( $type, array( 'company', 'municipality' ), true ) && taxonomy_exists( 'prefecture' ) ) {
    $prefs = get_terms( array( 'taxonomy' => 'prefecture', 'hide_empty' => true ) );
    if ( $prefs ) { echo '<nav class="region-links" aria-label="都道府県から探す">'; foreach ( $prefs as $pref ) { echo '<a href="' . esc_url( home_url( '/' . $type . '/' . $pref->slug . '/' ) ) . '">' . esc_html( $pref->name ) . '</a>'; } echo '</nav><br>'; }
    if ( $region && ! $region['city'] ) {
        $cities = get_terms( array( 'taxonomy' => 'city', 'hide_empty' => true, 'meta_query' => array( array( 'key' => 'cleanup_prefecture_id', 'value' => $region['prefecture']->term_id ) ) ) );
        if ( $cities ) { echo '<nav class="region-links" aria-label="市区町村から探す">'; foreach ( $cities as $city ) { echo '<a href="' . esc_url( home_url( '/' . $type . '/' . cleanup_city_path( $city->term_id ) . '/' ) ) . '">' . esc_html( $city->name ) . '</a>'; } echo '</nav><br>'; }
    }
}
global $wp_query; portal_cards( $wp_query );
echo '<nav class="pagination" aria-label="ページ送り">' . wp_kses_post( paginate_links() ) . '</nav>';
?></div></section><?php portal_cta(); ?></main><?php get_footer(); ?>
