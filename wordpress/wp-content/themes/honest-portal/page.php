<?php get_header(); while(have_posts()):the_post(); $slug=get_post_field('post_name',get_the_ID()); honest_breadcrumb(get_the_title()); ?>
<div class="page-heading"><p class="eyebrow">KATAZUKE NOTEBOOK</p><h1><?php the_title(); ?></h1><?php if(has_excerpt())echo '<p>'.esc_html(get_the_excerpt()).'</p>'; ?></div>
<?php if($slug==='company'):get_template_part('template-parts/search');
elseif(in_array($slug,['area','municipality'],true)): ?>
<div class="area-grid"><?php foreach(portal_areas() as $key=>$name): ?><a class="area-card" href="<?php echo honest_url('/area/'.$key.'/'); ?>"><span class="tag">公式情報を確認</span><h3><?php echo esc_html($name); ?></h3><p>粗大ごみの申し込み方法と確認ポイントをまとめています。</p><span class="text-link">地域の案内を読む　→</span></a><?php endforeach; ?></div><p class="notice">現在は世田谷区・川崎市の案内を掲載しています。他の地域は情報の確認後に追加します。</p>
<?php elseif($slug==='disposal'): ?><div class="area-grid"><?php $items=get_posts(['post_type'=>'item','numberposts'=>20]);foreach($items as $item): ?><a class="area-card" href="<?php echo esc_url(get_permalink($item)); ?>"><span class="tag">品目別ガイド</span><h3><?php echo esc_html($item->post_title); ?></h3><p><?php echo esc_html($item->post_excerpt); ?></p><span class="text-link">処分方法を確認する　→</span></a><?php endforeach; ?></div>
<?php elseif(in_array($slug,['anti-scam','industry','price','staff','case','news'],true)):
$query=['post_type'=>$slug==='staff'?'interview':($slug==='case'?'case':'article'),'numberposts'=>20];
if(!in_array($slug,['staff','case'],true))$query['tax_query']=[['taxonomy'=>'article_type','field'=>'slug','terms'=>$slug]];
$posts=get_posts($query);
if($posts):foreach($posts as $i=>$post)honest_article_card($post,$i);else: ?><div class="empty-state"><p class="eyebrow" style="justify-content:center">IN PREPARATION</p><h2><?php echo $slug==='staff'?'実際の声を、取材しています。':($slug==='case'?'事例は、確認できたものから。':'確認できた情報から、お届けします。'); ?></h2><p>出典や実資料を確認してから掲載します。<br>架空の実績・料金・スタッフの発言は掲載しません。</p><a class="button-outline" href="<?php echo honest_url('/about/'); ?>">編集方針を読む　→</a></div><?php endif;
else: ?><div class="content-body"><?php the_content(); ?></div><?php endif;endwhile;get_footer(); ?>
