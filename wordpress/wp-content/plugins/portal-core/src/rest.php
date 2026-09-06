<?php
defined('ABSPATH') || exit;
function portal_register_routes() {
    register_rest_route('portal/v1','/session',['methods'=>'GET','callback'=>'portal_session','permission_callback'=>'__return_true']);
    register_rest_route('portal/v1','/matches',['methods'=>'POST','callback'=>'portal_find_matches','permission_callback'=>'portal_public_permission']);
    register_rest_route('portal/v1','/leads',['methods'=>'POST','callback'=>'portal_create_lead','permission_callback'=>'portal_public_permission']);
    register_rest_route('portal/v1','/dashboard',['methods'=>'GET','callback'=>fn()=>portal_dashboard_data(),'permission_callback'=>'portal_can_manage']);
}
function portal_session() {
    $session = bin2hex(random_bytes(24));
    $expires = time()+3600;
    setcookie('portal_session',$session,['expires'=>$expires,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Strict']);
    $token = $expires.'.'.hash_hmac('sha256',$session.'|'.$expires,wp_salt('nonce'));
    $response = new WP_REST_Response(['token'=>$token,'demo'=>portal_demo()]);
    $response->header('Cache-Control','no-store');
    return $response;
}
function portal_public_permission($request) {
    $origin = $request->get_header('origin');
    $expected = wp_parse_url(home_url());
    $actual = wp_parse_url($origin);
    if (!$actual || ($actual['host']??'')!==($expected['host']??'') || ($actual['scheme']??'')!==($expected['scheme']??'') || ($actual['port']??null)!==($expected['port']??null)) return new WP_Error('origin','ページを再読み込みしてください。',['status'=>403]);
    $token = explode('.', $request->get_header('x-portal-token'));
    $cookie = $_COOKIE['portal_session']??'';
    if (count($token)!==2 || !ctype_digit($token[0]) || (int)$token[0]<time() || (int)$token[0]>time()+3600 || !is_string($cookie) || !hash_equals(hash_hmac('sha256',$cookie.'|'.$token[0],wp_salt('nonce')),$token[1])) return new WP_Error('token','セッションが切れました。ページを再読み込みしてください。',['status'=>403]);
    if (strlen($request->get_body())>12000) return new WP_Error('size','入力が長すぎます。',['status'=>400]);
    $key = 'portal_rate_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',wp_salt('nonce'));
    $count = (int)get_transient($key);
    if ($count>=60) return new WP_Error('rate','しばらく待ってからお試しください。',['status'=>429]);
    set_transient($key,$count+1,60);
    return true;
}
function portal_find_matches($request) {
    $conditions=portal_validate_request($request->get_json_params());
    if (is_wp_error($conditions)) return $conditions;
    $rows=portal_partners($conditions);
    $public=array_map(fn($p)=>['id'=>(int)$p['id'],'name'=>$p['name'],'same_day'=>(bool)$p['same_day'],'night'=>(bool)$p['night'],'is_demo'=>(bool)$p['is_demo'],'verified_at'=>$p['verified_at'],'reason'=>'地域・すべての選択品目・希望条件が一致しています。'],$rows);
    return ['partners'=>$public,'demo'=>portal_demo(),'disclosure'=>'条件が一致した登録順の一覧です。紹介料や自社ブランドによる優先表示は行いません。'];
}
function portal_create_lead($request) {
    global $wpdb;
    $input=$request->get_json_params();
    $conditions=portal_validate_request($input);
    if (is_wp_error($conditions)) return $conditions;
    if (!portal_demo()) return new WP_Error('not_live','現在は試験運用中です。本番の問い合わせ受付は開始していません。',['status'=>503]);
    if (($input['consent']??false)!==true || !empty($input['website'])) return new WP_Error('consent','保存先と試験運用の説明を確認し、同意してください。',['status'=>400]);
    $email=is_string($input['email']??null)?sanitize_email($input['email']):'';
    $name=is_string($input['name']??null)?sanitize_text_field($input['name']):'';
    if (!$name || mb_strlen($name)>80 || !is_email($email) || strlen($email)>190) return new WP_Error('contact','名前とメールアドレスを確認してください。',['status'=>400]);
    if (!preg_match('/@example\.(com|org|net)$/i',$email)) return new WP_Error('demo_contact','試験では example.com / example.org / example.net のメールアドレスをお使いください。',['status'=>400]);
    $key=is_string($input['idempotency_key']??null)?$input['idempotency_key']:'';
    if (!preg_match('/^[a-f0-9-]{32,64}$/i',$key)) return new WP_Error('key','受付キーが不正です。再読み込みしてください。',['status'=>400]);
    $partner_id=absint($input['partner_id']??0);
    $source_id=absint($input['source_post_id']??0);
    if ($source_id && get_post_status($source_id)!=='publish') $source_id=0;
    $payload_hash=hash_hmac('sha256',wp_json_encode([$conditions,$partner_id,$source_id,$name,$email]),wp_salt('auth'));
    $leads=portal_table('leads'); $partners=portal_table('partners');
    $existing=$wpdb->get_row($wpdb->prepare("SELECT id,public_id,payload_hash FROM $leads WHERE idempotency_key=%s",$key),ARRAY_A);
    if ($existing) {
        if (!hash_equals($existing['payload_hash'],$payload_hash)) return new WP_Error('conflict','受付済みのキーと入力が一致しません。',['status'=>409]);
        return ['lead_id'=>$existing['public_id'],'duplicate'=>true,'demo'=>true];
    }
    $matches=portal_partners($conditions);
    $partner=null;
    foreach ($matches as $p) if ((int)$p['id']===$partner_id) $partner=$p;
    if (!$partner) return new WP_Error('unavailable','この業者は現在の条件では受付できません。もう一度検索してください。',['status'=>409]);
    try { $contact=portal_encrypt(['name'=>$name,'email'=>$email]); }
    catch (Throwable $e) { return new WP_Error('encryption',$e->getMessage(),['status'=>503]); }
    $wpdb->query('START TRANSACTION');
    try {
        $reserved=$wpdb->query($wpdb->prepare("UPDATE $partners SET current_leads=current_leads+1 WHERE id=%d AND status='active' AND current_leads<monthly_limit",$partner_id));
        if ($reserved!==1) throw new RuntimeException('受付枠が更新されました。再検索してください。');
        $saved=$wpdb->insert($leads,['idempotency_key'=>$key,'payload_hash'=>$payload_hash,'area'=>$conditions['area'],'items'=>wp_json_encode($conditions['items']),'night'=>(int)$conditions['night'],'same_day'=>(int)$conditions['same_day'],'partner_id'=>$partner_id,'source_post_id'=>$source_id,'source_page'=>$source_id?get_permalink($source_id):home_url('/company/'),'fee_type'=>$partner['fee_type'],'fee_rate'=>$partner['fee_rate'],'consent_version'=>'mvp-demo-v1','created_at'=>portal_now(),'updated_at'=>portal_now(),'is_demo'=>1]);
        if (!$saved) throw new RuntimeException('保存できませんでした。再試行してください。');
        $id=(int)$wpdb->insert_id;
        $public='LEAD-'.wp_date('Ymd').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
        if (!$wpdb->update($leads,['public_id'=>$public],['id'=>$id])) throw new RuntimeException('受付番号の保存に失敗しました。');
        if (!$wpdb->insert(portal_table('contacts'),['lead_id'=>$id,'encrypted_payload'=>$contact,'expires_at'=>gmdate('Y-m-d H:i:s',time()+90*DAY_IN_SECONDS)])) throw new RuntimeException('連絡先の保存に失敗しました。');
        if (!$wpdb->insert(portal_table('events'),['lead_id'=>$id,'actor_id'=>0,'event_type'=>'consent_received','created_at'=>portal_now()])) throw new RuntimeException('同意履歴の保存に失敗しました。');
        $wpdb->query('COMMIT');
        return new WP_REST_Response(['lead_id'=>$public,'demo'=>true,'message'=>'動作確認用の問い合わせを保存しました。外部には送信していません。'],201);
    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('save_failed',$e->getMessage(),['status'=>409]);
    }
}
function portal_dashboard_data() {
    global $wpdb;
    $leads=portal_table('leads'); $ledger=portal_table('ledger');
    $counts=$wpdb->get_results("SELECT status,COUNT(*) AS total FROM $leads GROUP BY status",OBJECT_K);
    $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $leads");
    $converted=isset($counts['converted'])?(int)$counts['converted']->total:0;
    return ['leads'=>$total,'converted'=>$converted,'revenue'=>(int)$wpdb->get_var("SELECT COALESCE(SUM(amount_yen),0) FROM $ledger"),'counts'=>$counts,'analytics_connected'=>false,'ai_connected'=>false];
}
