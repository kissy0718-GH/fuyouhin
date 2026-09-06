<?php
defined('ABSPATH') || exit;
function portal_register_content() {
    $types = ['article'=>'記事','area'=>'地域','municipality'=>'自治体','item'=>'品目','case'=>'回収事例','company'=>'業者','price'=>'料金','interview'=>'インタビュー','faq'=>'FAQ'];
    foreach ($types as $key=>$label) {
        $slug = ['article'=>'read','item'=>'disposal','interview'=>'staff','company'=>'company/detail'][$key] ?? $key;
        register_post_type($key, ['label'=>$label,'public'=>$key!=='faq','show_ui'=>true,'show_in_rest'=>true,'hierarchical'=>$key==='area','has_archive'=>false,'rewrite'=>['slug'=>$slug,'with_front'=>false],'supports'=>['title','editor','excerpt','thumbnail','revisions','page-attributes'],'menu_icon'=>'dashicons-media-document']);
    }
    foreach (['portal_area','prefecture','city','item_category','article_type','content_tag','service_type'] as $tax) register_taxonomy($tax, array_keys($types), ['label'=>$tax,'public'=>false,'show_ui'=>true,'show_in_rest'=>true,'rewrite'=>false,'hierarchical'=>in_array($tax,['portal_area','prefecture','city','item_category'])]);
}

function portal_meta_boxes() {
    foreach (['article','area','municipality','item','case','company','price','interview','faq'] as $type) add_meta_box('portal-evidence','公開審査・出典','portal_meta_box',$type,'normal','high');
}
function portal_meta_box($post) {
    wp_nonce_field('portal_content','portal_content_nonce');
    foreach (['source_url'=>'確認した出典URL','verified_at'=>'情報確認日（YYYY-MM-DD）','quality_score'=>'品質スコア（0〜100）'] as $key=>$label) {
        echo '<p><label>'.esc_html($label).'<br><input class="widefat" name="portal_'.$key.'" value="'.esc_attr(get_post_meta($post->ID,'_portal_'.$key,true)).'"></label></p>';
    }
    echo '<p><label><input type="checkbox" name="portal_approved" value="1"> この原稿の出典・独自性・内容を人が確認しました（公開時に毎回必要）</label></p><p>80点未満、出典・確認日なしは下書きに戻ります。事例・インタビューは実資料と掲載同意の確認も必須です。</p>';
    echo '<label><input type="checkbox" name="portal_primary_verified" value="1"> 実資料・本人確認・掲載同意を確認しました</label>';
}
function portal_publication_gate($data, $postarr) {
    if (!in_array($data['post_type'], ['article','area','municipality','item','case','company','price','interview','faq'],true) || $data['post_status']!=='publish') return $data;
    if (defined('PORTAL_SEEDING') && PORTAL_SEEDING) return $data;
    $ok = isset($_POST['portal_content_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['portal_content_nonce'])),'portal_content') && portal_can_manage() && !empty($_POST['portal_approved']) && (int)($_POST['portal_quality_score']??0)>=80 && (int)($_POST['portal_quality_score']??0)<=100 && filter_var(wp_unslash($_POST['portal_source_url']??''),FILTER_VALIDATE_URL) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$_POST['portal_verified_at']??'');
    if (in_array($data['post_type'],['case','interview'],true) && empty($_POST['portal_primary_verified'])) $ok=false;
    if (!$ok) $data['post_status']='draft';
    return $data;
}
function portal_save_metadata($id) {
    if (wp_is_post_revision($id) || !isset($_POST['portal_content_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['portal_content_nonce'])),'portal_content') || !current_user_can('edit_post',$id)) return;
    update_post_meta($id,'_portal_source_url',esc_url_raw(wp_unslash($_POST['portal_source_url']??'')));
    update_post_meta($id,'_portal_verified_at',sanitize_text_field(wp_unslash($_POST['portal_verified_at']??'')));
    update_post_meta($id,'_portal_quality_score',max(0,min(100,(int)($_POST['portal_quality_score']??0))));
    if (portal_can_manage() && !empty($_POST['portal_approved'])) update_post_meta($id,'_portal_reviewed_by',get_current_user_id());
}
