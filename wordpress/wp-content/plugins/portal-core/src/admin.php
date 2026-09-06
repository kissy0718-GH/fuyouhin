<?php
defined('ABSPATH') || exit;
function portal_admin_menu() { add_menu_page('Portal Dashboard','ポータル管理','manage_portal','portal-dashboard','portal_admin_page','dashicons-chart-area',3); }
function portal_admin_page() {
    if (!portal_can_manage()) return;
    global $wpdb;
    $data=portal_dashboard_data();
    $partners=$wpdb->get_results('SELECT * FROM '.portal_table('partners').' ORDER BY id',ARRAY_A);
    $leads=$wpdb->get_results('SELECT l.*,p.name AS partner_name FROM '.portal_table('leads').' l LEFT JOIN '.portal_table('partners').' p ON p.id=l.partner_id ORDER BY l.id DESC LIMIT 100',ARRAY_A);
    echo '<div class="wrap"><h1>AI SEO Dashboard <small>／ ポータル管理</small></h1><p>試験運用の保存データです。送客は状態を記録するだけで、外部への配信は行いません。</p>';
    if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>保存しました。</p></div>';
    echo '<div style="display:flex;gap:20px;flex-wrap:wrap;margin:24px 0">';
    foreach (['問い合わせ'=>$data['leads'].'件','成約'=>$data['converted'].'件','紹介料台帳'=>'¥'.number_format($data['revenue']),'Search Console / GA4'=>'未接続','AI生成'=>'未接続'] as $label=>$value) echo '<div style="background:#fff;padding:22px;border:1px solid #ddd;min-width:130px"><div>'.esc_html($label).'</div><strong style="font-size:26px">'.esc_html($value).'</strong></div>';
    echo '</div><h2>問い合わせ・成約</h2><p>Lead IDで履歴を追跡します。成約時の金額は円単位。キャンセルすると計上済み紹介料を反対仕訳します。</p>';
    if (!$leads) echo '<p>問い合わせはまだありません。サイトの「業者を探す」から動作確認できます。</p>';
    echo '<div style="overflow:auto"><table class="widefat striped"><thead><tr><th>受付番号 / 日時</th><th>地域・品目</th><th>連絡先（管理者のみ）</th><th>候補業者</th><th>現在の状況</th><th>更新</th></tr></thead><tbody>';
    foreach ($leads as $lead) {
        $enc=$wpdb->get_var($wpdb->prepare('SELECT encrypted_payload FROM '.portal_table('contacts').' WHERE lead_id=%d AND expires_at>%s',$lead['id'],portal_now()));
        $contact=$enc?portal_decrypt($enc):[];
        echo '<tr><td><strong>'.esc_html($lead['public_id']).'</strong><br>'.esc_html($lead['created_at']).' UTC</td><td>'.esc_html(portal_areas()[$lead['area']]??$lead['area']).'<br>'.esc_html(implode('・',array_map(fn($i)=>portal_items()[$i]??$i,json_decode($lead['items'],true)))).'</td><td>'.esc_html($contact['name']??'保管期限終了').'<br>'.esc_html($contact['email']??'').'</td><td>'.esc_html($lead['partner_name']).'</td><td>'.esc_html(portal_statuses()[$lead['status']]??$lead['status']).'</td><td>';
        $next=array_filter(array_keys(portal_statuses()),fn($status)=>portal_valid_transition($lead['status'],$status));
        if ($next) {
            echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="portal_lead_status"><input type="hidden" name="lead_id" value="'.(int)$lead['id'].'"><input type="hidden" name="version" value="'.(int)$lead['version'].'">';
            wp_nonce_field('portal_lead_status');
            echo '<label>次の状況 <select name="status">';
            foreach ($next as $status) echo '<option value="'.esc_attr($status).'">'.esc_html(portal_statuses()[$status]).'</option>';
            echo '</select></label><br><label>成約額 <input style="width:110px" name="conversion_value" type="number" min="0" max="100000000" value="0"> 円</label><br><button class="button button-primary">更新</button></form>';
        } else echo '処理完了';
        echo '</td></tr>';
    }
    echo '</tbody></table></div><h2>記事別の紹介料</h2><table class="widefat striped"><tr><th>起点ページ</th><th>問い合わせ</th><th>台帳合計</th></tr>';
    $report=$wpdb->get_results('SELECT l.source_post_id,COUNT(DISTINCT l.id) AS leads,COALESCE(SUM(f.amount),0) AS revenue FROM '.portal_table('leads').' l LEFT JOIN (SELECT lead_id,SUM(amount_yen) AS amount FROM '.portal_table('ledger').' GROUP BY lead_id) f ON f.lead_id=l.id GROUP BY l.source_post_id',ARRAY_A);
    foreach ($report as $row) echo '<tr><td>'.esc_html($row['source_post_id']?get_the_title($row['source_post_id']):'業者検索から直接').'</td><td>'.(int)$row['leads'].'</td><td>¥'.number_format((int)$row['revenue']).'</td></tr>';
    echo '</table><h2>登録Partner</h2><p>紹介料は契約条件として保存され、利用者向けの順位には使いません。MVPは試験用Partnerのみ受付できます。</p>';
    foreach ($partners as $partner) portal_partner_form($partner);
    portal_partner_form(['id'=>0,'name'=>'','areas'=>'[]','items'=>'[]','status'=>'inactive','partner_type'=>'external','monthly_limit'=>10,'fee_type'=>'conversion_fixed','fee_rate'=>0,'night'=>0,'same_day'=>0,'verified_at'=>null,'is_demo'=>1,'current_leads'=>0]);
    echo '<h2>情報確認が必要なコンテンツ</h2><ul>';
    $posts=get_posts(['post_type'=>['municipality','article','item','interview','case'],'post_status'=>['draft','publish'],'numberposts'=>100]);
    $needs=0;
    foreach ($posts as $post) {
        $date=get_post_meta($post->ID,'_portal_verified_at',true);
        if ($post->post_status==='draft' || !$date || strtotime($date)<time()-90*DAY_IN_SECONDS) { $needs++; echo '<li><a href="'.esc_url(get_edit_post_link($post->ID)).'">'.esc_html($post->post_title).'</a> — '.($post->post_status==='draft'?'下書き・審査待ち':'確認期限超過').'</li>'; }
    }
    if (!$needs) echo '<li>現在、確認期限を超えたコンテンツはありません。</li>';
    echo '</ul></div>';
}
function portal_partner_form($p) {
    echo '<details style="background:white;padding:16px;border:1px solid #ddd;margin:12px 0"><summary style="cursor:pointer;font-weight:bold">'.esc_html($p['name']?:'＋ Partnerを登録').($p['id']?' ／ '.$p['current_leads'].'件受付':'').'</summary><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="portal_partner"><input type="hidden" name="id" value="'.(int)$p['id'].'">';
    wp_nonce_field('portal_partner');
    echo '<p><label>表示名 <input name="name" required maxlength="190" value="'.esc_attr($p['name']).'"></label></p><p><label>区分 <select name="partner_type">';
    foreach (['external'=>'外部','internal'=>'自社'] as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected($p['partner_type'],$value,false).'>'.esc_html($label).'</option>';
    echo '</select></label> <label>状態 <select name="status">';
    foreach (['inactive'=>'停止','active'=>'受付中'] as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected($p['status'],$value,false).'>'.esc_html($label).'</option>';
    echo '</select></label></p><fieldset><legend>対応地域</legend>';
    foreach (portal_areas() as $value=>$label) echo '<label style="margin-right:20px"><input type="checkbox" name="areas[]" value="'.esc_attr($value).'" '.checked(in_array($value,json_decode($p['areas'],true)),true,false).'>'.esc_html($label).'</label>';
    echo '</fieldset><fieldset><legend>対応品目</legend>';
    foreach (portal_items() as $value=>$label) echo '<label style="margin-right:20px"><input type="checkbox" name="items[]" value="'.esc_attr($value).'" '.checked(in_array($value,json_decode($p['items'],true)),true,false).'>'.esc_html($label).'</label>';
    echo '</fieldset><p><label><input type="checkbox" name="night" '.checked($p['night'],1,false).'>夜間</label> <label><input type="checkbox" name="same_day" '.checked($p['same_day'],1,false).'>即日</label> <label><input type="checkbox" name="verified" '.checked(!empty($p['verified_at']),true,false).'>対応条件を確認済み</label></p><p><label>月次受付上限 <input name="monthly_limit" type="number" min="0" max="10000" value="'.(int)$p['monthly_limit'].'"></label></p><p><label>紹介料方式 <select name="fee_type">';
    foreach (['lead'=>'送客単価（円）','conversion_fixed'=>'成約単価（円）','conversion_percent'=>'成約割合（bps: 1000=10%）'] as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected($p['fee_type'],$value,false).'>'.esc_html($label).'</option>';
    echo '</select></label> <label>単価 / 率 <input name="fee_rate" type="number" min="0" value="'.(int)$p['fee_rate'].'"></label></p><p><label><input type="checkbox" name="is_demo" '.checked($p['is_demo'],1,false).'>動作確認用（架空）</label></p><button class="button button-primary">保存</button></form></details>';
}
function portal_save_partner() {
    if (!portal_can_manage()) wp_die('権限がありません。',403);
    check_admin_referer('portal_partner');
    global $wpdb;
    $areas=array_values(array_intersect(array_keys(portal_areas()),(array)($_POST['areas']??[])));
    $items=array_values(array_intersect(array_keys(portal_items()),(array)($_POST['items']??[])));
    $type=sanitize_key($_POST['fee_type']??'');
    $rate=absint($_POST['fee_rate']??0);
    if (!in_array($type,['lead','conversion_fixed','conversion_percent'],true) || ($type==='conversion_percent' && $rate>10000) || $rate>100000000) wp_die('紹介料条件が不正です。');
    $name=sanitize_text_field(wp_unslash($_POST['name']??''));
    if (!$name || mb_strlen($name)>190) wp_die('表示名を確認してください。');
    $status=($_POST['status']??'')==='active'?'active':'inactive';
    if ($status==='active' && (!$areas || !$items || empty($_POST['verified']))) wp_die('受付開始には対応地域・品目・確認済みチェックが必要です。');
    $data=['name'=>$name,'partner_type'=>($_POST['partner_type']??'')==='internal'?'internal':'external','areas'=>wp_json_encode($areas),'items'=>wp_json_encode($items),'status'=>$status,'night'=>empty($_POST['night'])?0:1,'same_day'=>empty($_POST['same_day'])?0:1,'verified_at'=>empty($_POST['verified'])?null:portal_now(),'monthly_limit'=>min(10000,absint($_POST['monthly_limit']??0)),'fee_type'=>$type,'fee_rate'=>$rate,'is_demo'=>empty($_POST['is_demo'])?0:1];
    $id=absint($_POST['id']??0);
    $result=$id?$wpdb->update(portal_table('partners'),$data,['id'=>$id]):$wpdb->insert(portal_table('partners'),array_merge($data,['usage_month'=>wp_date('Y-m')]));
    if ($result===false) wp_die('保存に失敗しました。');
    wp_safe_redirect(admin_url('admin.php?page=portal-dashboard&saved=1')); exit;
}
function portal_change_status($id,$version,$next,$conversion) {
    global $wpdb;
    $table=portal_table('leads');
    $wpdb->query('START TRANSACTION');
    try {
        $lead=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$lead || !portal_valid_transition($lead['status'],$next)) throw new RuntimeException('この状態には変更できません。');
        if ($conversion<0 || $conversion>100000000 || ($next==='converted' && $conversion<=0)) throw new RuntimeException('成約額を入力してください。');
        $updated=$wpdb->update($table,['status'=>$next,'conversion_value'=>$next==='converted'?$conversion:$lead['conversion_value'],'updated_at'=>portal_now(),'version'=>$version+1],['id'=>$id,'version'=>$version]);
        if ($updated!==1) throw new RuntimeException('別の操作で更新されています。再読み込みしてください。');
        $fee=0;
        if (($next==='sent' && $lead['fee_type']==='lead') || ($next==='converted' && $lead['fee_type']!=='lead')) $fee=portal_fee($lead['fee_type'],(int)$lead['fee_rate'],$conversion);
        if ($next==='cancelled') $fee=-(int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(amount_yen),0) FROM '.portal_table('ledger').' WHERE lead_id=%d',$id));
        if ($fee!==0 && !$wpdb->insert(portal_table('ledger'),['lead_id'=>$id,'partner_id'=>$lead['partner_id'],'event_key'=>$id.':'.$next,'amount_yen'=>$fee,'created_at'=>portal_now()])) throw new RuntimeException('台帳を更新できませんでした。');
        if (!$wpdb->insert(portal_table('events'),['lead_id'=>$id,'actor_id'=>get_current_user_id(),'event_type'=>$next,'created_at'=>portal_now()])) throw new RuntimeException('履歴を保存できませんでした。');
        $wpdb->query('COMMIT'); return true;
    } catch (Throwable $e) { $wpdb->query('ROLLBACK'); return new WP_Error('status',$e->getMessage()); }
}
function portal_update_lead_status() {
    if (!portal_can_manage()) wp_die('権限がありません。',403);
    check_admin_referer('portal_lead_status');
    $result=portal_change_status(absint($_POST['lead_id']??0),absint($_POST['version']??0),sanitize_key($_POST['status']??''),(int)($_POST['conversion_value']??0));
    if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
    wp_safe_redirect(admin_url('admin.php?page=portal-dashboard&saved=1')); exit;
}
