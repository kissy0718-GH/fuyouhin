<?php
require_once '/wordpress/wp-load.php';
if (!portal_demo()) throw new RuntimeException('デモ専用の初期化です。');
define('PORTAL_SEEDING', true);
update_option('blogname','かたづけの手帖');
update_option('blogdescription','不用品回収の裏側まで、正直に。');
update_option('timezone_string','Asia/Tokyo');
update_option('blog_public',0);
update_option('permalink_structure','/%postname%/');
update_option('default_comment_status','closed');
function seed_post($type,$slug,$title,$body='',$excerpt='',$meta=[],$parent=0) {
    $existing=get_posts(['post_type'=>$type,'name'=>$slug,'post_status'=>'any','numberposts'=>1,'post_parent'=>$parent]);
    if($existing) return $existing[0]->ID;
    $id=wp_insert_post(['post_type'=>$type,'post_name'=>$slug,'post_title'=>$title,'post_content'=>$body,'post_excerpt'=>$excerpt,'post_status'=>'publish','post_parent'=>$parent]);
    foreach($meta as $key=>$value)update_post_meta($id,'_portal_'.$key,$value);
    update_post_meta($id,'_portal_demo',1);
    return $id;
}
$home=seed_post('page','home','かたづけの手帖');
update_option('show_on_front','page');update_option('page_on_front',$home);
$descriptions=['company'=>'地域・品目・ご希望の条件から、一致する登録業者を確認できます。','area'=>'自治体ごとに異なる出し方を、公式情報から確かめましょう。','municipality'=>'自治体の公式案内への入り口です。','anti-scam'=>'納得して依頼するために、料金表示や見積もりの確認ポイントを。','industry'=>'回収の仕組みを、根拠のある情報からひもときます。','staff'=>'現場の言葉は、実際の取材と本人の確認を経て。','case'=>'写真・料金・作業内容を確認した実際の事例を掲載します。','disposal'=>'手放したいものから、確認しておくことを整理。','price'=>'金額だけでなく、料金に含まれる作業と条件を確認しましょう。','news'=>'サイトからのお知らせ。'];
foreach(portal_categories() as $slug=>$label)seed_post('page',$slug,$label,'',$descriptions[$slug]??'');
seed_post('page','about','この手帖が、大切にしていること。','<h2>不用品回収の裏側まで、正直に。</h2><p>自治体の収集、リユース、業者への依頼。方法を比べて、納得して手放すための情報を整理するポータルです。</p><h2>情報を届ける3つの約束</h2><ul><li>自治体の情報には公式の出典と確認日を付けます。</li><li>事例・料金・スタッフの発言を創作しません。</li><li>紹介料や自社ブランドを理由に、おすすめ順位を上げません。</li></ul><h2>掲載と紹介料について</h2><p>将来、登録業者への紹介によって紹介料を受け取る予定です。自社ブランドと外部の提携業者を同じ条件で管理し、広告・紹介関係を表示します。</p><h2>現在は試験運用です</h2><p>このMVPでは世田谷区・川崎市の公式案内と編集用の記事プレビューを掲載しています。記事は本番公開前に編集者の審査が必要です。業者は動作確認用の架空データです。実際の依頼や外部送客は受け付けていません。</p>');
seed_post('page','privacy','個人情報の取り扱い','<h2>試験受付で保存する情報</h2><p>入力した試験用の名前・メールアドレス、地域、品目、条件、候補業者、起点ページ、同意日時を保存します。実際の個人情報は入力しないでください。メールアドレスはexample.com、example.org、example.netに限定しています。</p><h2>利用目的と保存先</h2><p>問い合わせと成約・紹介料管理の動作確認に使用し、このサイトの管理者が確認できます。外部業者への送信、メール送信、外部解析サービスへの情報送信は行いません。</p><h2>保管期限</h2><p>連絡先は暗号化して保存し、90日を過ぎると管理画面に表示しません。期限切れデータは管理用の削除処理で消去します。本番運用には運営者名・連絡窓口・保存期間を確定した正式な説明が必要です。</p>');
$setagaya='https://www.city.setagaya.lg.jp/02241/online_tetsuzuki/380.html';
$kawasaki='https://www.city.kawasaki.jp/templates/faq/300/0000013341.html';
$meta=['source_url'=>$setagaya,'verified_at'=>'2026-09-05','quality_score'=>0];
$stories=[
 ['estimate-checklist','その「無料」、どこまで無料？ 見積もりの確認リスト','anti-scam','<p>「無料」という表示を見たときは、何が無料なのかを分けて確認しましょう。相談、出張、見積もり、運び出し、回収では、指している作業が異なります。</p><h2>最初に、対象の作業をそろえる</h2><ul><li>どの品目を、いくつ依頼するのか。</li><li>室内からの運び出しを含むのか。</li><li>階段、分解、駐車場所などで料金が変わるのか。</li></ul><h2>合計額と追加条件を確認する</h2><p>見積もりは合計額だけでなく、含まれる作業と追加料金の条件を合わせて確認します。キャンセル条件も依頼前に聞き、回答を記録しておきましょう。</p><h2>自治体の方法も比較する</h2><p>例えば世田谷区の公式案内では、品目の名称・大きさ・数量を確認して申し込む手順が示されています。業者への相談前に、自分で利用できる自治体の方法も確認できます。</p>'],
 ['before-disposal','粗大ごみを申し込む前に、測っておきたいこと','disposal','<p>処分したい家具が決まったら、品目・大きさ・数量をメモしておきましょう。問い合わせや申込みで、同じ説明を繰り返さずに済みます。</p><h2>品目とサイズを記録する</h2><p>世田谷区の公式案内は、名称・大きさ・数量の確認を申込みの最初の手順にしています。電話申込み前には最長辺とその次に長い辺を測るよう案内されています。</p><h2>運び出す場所を確認する</h2><p>集合住宅では排出場所が決められている場合があります。管理者の案内を確認しておきましょう。</p><h2>申込み先で条件を確認する</h2><p>受付方法・対象品目・手数料は自治体の案内で確認してください。別の自治体の条件をそのまま当てはめないようにしましょう。</p>'],
 ['compare-estimates','見積もりを比べるときは「総額」と「作業範囲」をセットに','price','<p>見積もりを比較するための編集部チェックリストです。このページでは、未確認の相場や具体的な料金を掲載しません。</p><h2>同じ依頼内容で確認する</h2><ul><li>品目、数量、大きさ</li><li>運び出しの有無と階段・エレベーターの条件</li><li>希望する日時</li><li>追加作業が必要になった場合の確認方法</li></ul><h2>内訳と適用条件を残す</h2><p>合計額、税込・税別、含まれる作業、追加料金、キャンセル条件を記録します。条件が違う場合は金額だけで判断せず、違いを確認しましょう。</p><h2>自治体の案内も確認する</h2><p>自治体に依頼できる品目か、排出場所まで自分で運べるかも、方法選びの判断材料になります。</p>']
];
foreach($stories as [$slug,$title,$category,$body]){$id=seed_post('article',$slug,$title,$body,'依頼前に確認したいことを整理する、編集用プレビューです。',$meta);$term=term_exists($category,'article_type');if(!$term)$term=wp_insert_term(portal_categories()[$category], 'article_type',['slug'=>$category]);if(!is_wp_error($term))wp_set_object_terms($id,(int)$term['term_id'],'article_type');}
foreach(portal_items() as $slug=>$label)seed_post('item',$slug,$label.'の手放し方','<p>'.$label.'を手放す前に、状態・大きさ・数量を確認しましょう。</p><h2>まだ使えるかを確認する</h2><p>譲渡やリユースを検討する場合は、状態と受入条件を相手に確認します。引取りや買取を保証するものではありません。</p><h2>自治体の案内を調べる</h2><p>品目の呼び方、サイズ、申込み方法、排出場所は、お住まいの自治体の公式案内を確認してください。</p><h2>運び出しが難しい場合</h2><p>業者に相談する際は、大きさと設置場所、運び出しの条件を伝え、作業範囲と総額の見積もりを確認しましょう。</p>','サイズ・状態・運び出しの条件から、選択肢を整理します。',$meta);
foreach([['tokyo','東京都','setagaya','世田谷区',$setagaya],['kanagawa','神奈川県','kawasaki','川崎市',$kawasaki]] as [$pref,$prefname,$city,$cityname,$url]){
    $parent=seed_post('area',$pref,$prefname.'の不用品・粗大ごみ案内','<p>掲載中の自治体から、お住まいの地域を選んでください。</p><p><a href="/area/'.$pref.'/'.$city.'/">'.$cityname.'の案内を見る →</a></p>');
    $body='<p>'.$cityname.'で粗大ごみを出すときは、自治体の公式案内で品目と申込方法を確認しましょう。</p><h2>申し込む前に準備すること</h2><ul><li>品目の名称・大きさ・数量を記録する。</li><li>収集対象になるかを公式案内で確認する。</li><li>申込み時に、収集日や手数料の案内を確認する。</li></ul><h2>最新の条件は公式情報で</h2><p>受付方法や手数料は更新されることがあります。下記の公式案内から、現在の条件をご確認ください。</p><p><a href="'.esc_url($url).'" target="_blank" rel="noopener">'.$cityname.'の粗大ごみ公式案内 ↗</a></p><h2>業者に相談したいとき</h2><p>運び出しの対応や希望する日時を伝えて、作業の範囲と見積もりを確認しましょう。自治体の申込みと、民間業者への依頼は別の手続きです。</p>';
    $m=['source_url'=>$url,'verified_at'=>'2026-09-05','area'=>$pref.'/'.$city];
    seed_post('area',$city,$cityname.'の粗大ごみ・不用品回収案内',$body,'公式の申込み案内と、手放す前に確認したいこと。',$m,$parent);
    seed_post('municipality',$city,$cityname.'の公式案内',$body,'自治体情報の確認用ページ。',$m);
}
global $wpdb;
if(!(int)$wpdb->get_var('SELECT COUNT(*) FROM '.portal_table('partners'))){
    foreach([
      ['動作確認用パートナー A',['tokyo/setagaya','kanagawa/kawasaki'],array_keys(portal_items()),1,1,3000],
      ['動作確認用パートナー B',['tokyo/setagaya'],['sofa','table','chair'],0,0,5000]
    ] as [$name,$areas,$items,$night,$same,$fee])$wpdb->insert(portal_table('partners'),['name'=>$name,'areas'=>wp_json_encode($areas),'items'=>wp_json_encode($items),'night'=>$night,'same_day'=>$same,'monthly_limit'=>100,'usage_month'=>wp_date('Y-m'),'fee_type'=>'conversion_fixed','fee_rate'=>$fee,'status'=>'active','verified_at'=>portal_now(),'is_demo'=>1]);
    foreach(['不用品回収エコピット','GO!GO!!クリーン'] as $name)$wpdb->insert(portal_table('partners'),['name'=>$name,'partner_type'=>'internal','areas'=>'[]','items'=>'[]','monthly_limit'=>0,'usage_month'=>wp_date('Y-m'),'status'=>'inactive','is_demo'=>0]);
}
flush_rewrite_rules();
echo 'MVP seed ready';
