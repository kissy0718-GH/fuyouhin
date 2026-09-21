<?php defined( 'ABSPATH' ) || exit; ?>
<footer class="site-footer"><div class="wrap"><?php if ( has_nav_menu( 'footer' ) ) { wp_nav_menu( array( 'theme_location' => 'footer', 'container' => 'nav', 'menu_class' => 'footer-links', 'depth' => 1 ) ); } ?>
<?php if ( get_option( 'cleanup_operator_name', '' ) ) { echo '<p>運営者：' . esc_html( get_option( 'cleanup_operator_name' ) ) . '</p>'; } ?>
<p>不用品回収・遺品整理 悪徳業者撲滅プロジェクト<br>掲載情報は確認時点の内容です。最新の条件は自治体・事業者の公式情報をご確認ください。</p></div></footer><?php wp_footer(); ?></body></html>
