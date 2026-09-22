<?php get_header(); ?>
<main id="main"><section class="hero"><div class="wrap hero-grid"><div><p class="eyebrow">知ることから、トラブルを防ぐ。</p><h1><span class="hero-phrase">不用品回収の</span><span class="hero-phrase">裏側まで、</span><br>正直に。</h1><p>「無料」の条件、見積もりの内訳、自治体の処分方法。<br>依頼の前に確かめたい情報を、一つずつ。</p><a class="button button-yellow" href="<?php echo esc_url( home_url( '/scam/' ) ); ?>">トラブルの手口を知る →</a></div><div class="hero-guide"><a href="<?php echo esc_url( home_url( '/municipality/' ) ); ?>"><span>01</span>自治体で処分する方法を探す<b>→</b></a><a href="<?php echo esc_url( home_url( '/price/' ) ); ?>"><span>02</span>料金と見積もりの考え方を知る<b>→</b></a><a href="<?php echo esc_url( home_url( '/company/' ) ); ?>"><span>03</span>地域の業者を探す<b>→</b></a></div></div></section>
<section class="section"><div class="wrap intro"><div><p class="kicker">このプロジェクトについて</p><h2>不安をあおらず、<br>判断できる情報を。</h2></div><div><p>不用品回収・遺品整理で失敗しないために。トラブルの手口だけでなく、料金の仕組みや自治体の回収方法も伝える情報サイトです。</p><p>一次資料や実際の取材をもとに情報を確認し、出典を示します。業者の掲載では広告・提携関係を明示し、紹介料の額で優劣を決めません。</p></div></div></section>
<?php
if ( function_exists( 'cleanup_current_archive_path' ) ) {
    $home_seo = get_option( 'cleanup_seo_archives', array() )['/'] ?? array();
    if ( ! empty( $home_seo['intro'] ) ) { echo '<section class="section"><div class="wrap">' . wpautop( esc_html( $home_seo['intro'] ) ) . '</div></section>'; }
}
portal_section( 'scam', '知っておきたい、トラブルの手口', '追加料金や「無料」の条件など、依頼の前に確認したい事例を紹介します。', true );
portal_section( 'industry', '業界の裏話', '見積もりや料金の仕組みを、資料と現場の情報から読み解きます。' );
portal_section( 'staff', '作業スタッフの本音', '実際の取材に基づく、現場スタッフの声を届けます。', true );
portal_section( 'news', '不用品回収ニュース', '自治体制度、リユース、廃棄物に関する一次情報を確認します。' );
portal_section( 'municipality', 'まずは、自治体の回収情報から', '住んでいる地域の申込方法、対象品目、料金を確認しましょう。', true );
portal_section( 'price', '料金相場と見積もり', '相場は確定料金ではありません。金額が変わる条件も確認しましょう。' );
portal_section( 'company', '全国の不用品回収・遺品整理業者', '対応地域とサービス、料金条件、掲載区分を比較できます。', true );
portal_cta(); ?></main><?php get_footer(); ?>
