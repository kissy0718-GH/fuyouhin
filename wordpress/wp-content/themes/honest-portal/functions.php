<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('html5',['search-form','gallery','caption','style','script']);});
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('honest-portal',get_stylesheet_uri(),[],filemtime(get_stylesheet_directory().'/style.css'));if(is_page('company')){wp_enqueue_script('portal-search',get_template_directory_uri().'/assets/search.js',[],filemtime(get_template_directory().'/assets/search.js'),true);wp_localize_script('portal-search','PortalConfig',['api'=>rest_url('portal/v1/'),'demo'=>portal_demo(),'sourcePostId'=>absint($_GET['source']??0)]);}});
add_filter('wp_robots',function($robots){if(portal_demo()||is_search()||is_404()){$robots['noindex']=true;$robots['nofollow']=true;}return $robots;});
add_action('wp_head',function(){
    if(is_404()) return;
    $url=is_singular()?get_permalink():home_url('/');
    $description=is_singular()?get_the_excerpt():get_bloginfo('description');
    if(!$description) $description='不用品回収の裏側まで、正直に。処分方法・自治体情報・業者選びを整理する情報ポータル。';
    echo '<meta name="description" content="'.esc_attr(wp_strip_all_tags($description)).'"><meta property="og:title" content="'.esc_attr(wp_get_document_title()).'"><meta property="og:description" content="'.esc_attr(wp_strip_all_tags($description)).'"><meta property="og:url" content="'.esc_url($url).'"><meta property="og:type" content="'.(is_singular('article')?'article':'website').'">';
    if(!is_singular()) echo '<link rel="canonical" href="'.esc_url($url).'">';
    $schema=['@context'=>'https://schema.org','@type'=>'WebSite','name'=>get_bloginfo('name'),'url'=>home_url('/')];
    if(is_singular(['article','item','municipality'])) $schema=['@context'=>'https://schema.org','@type'=>'Article','headline'=>get_the_title(),'datePublished'=>get_the_date('c'),'dateModified'=>get_the_modified_date('c'),'mainEntityOfPage'=>$url];
    echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
});
function honest_url($path){return esc_url(home_url($path));}
function honest_breadcrumb($label){echo '<nav class="breadcrumb" aria-label="パンくず"><a href="'.honest_url('/').'">ホーム</a>　／　'.esc_html($label).'</nav>';}
function honest_article_card($post,$index=0){$terms=get_the_terms($post,'article_type');$label=$terms&&!is_wp_error($terms)?$terms[0]->name:'暮らしの整理';echo '<a class="story" href="'.esc_url(get_permalink($post)).'"><div class="story-art" aria-hidden="true">'.esc_html(['01','02','03','04'][$index%4]).'</div><div><span class="tag">'.esc_html($label).'</span><h3>'.esc_html($post->post_title).'</h3><span class="meta">'.esc_html(get_the_date('Y.m.d',$post)).'　・　編集用プレビュー</span></div></a>';}
