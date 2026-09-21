<?php
function ai_plan( $decision = 'CREATE', $target = 0 ) {
    return array( 'keyword' => '検証用キーワード', 'intent' => '検証用の検索意図', 'decision' => $decision, 'reason' => '資料と既存記事の重複を確認するテスト', 'unique_value' => '検証用の独自価値', 'outline' => '背景・確認事項・選び方', 'article_type' => 'industry', 'target_id' => $target, 'merge_ids' => array(), 'sources' => array( array( 'url' => 'https://example.org/test-source', 'title' => '検証専用資料', 'excerpt' => '実情報ではないテスト用根拠' ) ) );
}
function ai_output() {
    return array( 'title' => 'AI検証用原稿', 'content' => '<h2>確認事項</h2><p>テストのための原稿。</p>', 'excerpt' => '検証専用の抜粋', 'provider' => 'manual-test', 'model' => 'test-only', 'claims' => array( array( 'claim' => '検証用の主張', 'source_index' => 0, 'type' => 'other', 'status' => 'verified' ) ) );
}
function ai_prepare_for_review( $id ) {
    $data = cleanup_data( $id ); $data['last_verified_at'] = current_time( 'Y-m-d' ); update_post_meta( $id, '_cleanup_data', $data );
    return cleanup_ai_review_claims( $id, cleanup_ai_review_digest( $id ), array( array( 'status' => 'verified', 'note' => '原資料と本文全体を照合するテスト' ) ) );
}
wp_set_current_user( 1 );
$bad = ai_plan(); $bad['sources'] = array();
check( is_wp_error( cleanup_ai_create_job( $bad ) ), 'AI job requires research sources' );
$no_action = ai_plan( 'NO ACTION' ); $no_action['sources'] = array(); $no_action['outline'] = ''; $no_action['unique_value'] = '';
$closed_job = cleanup_ai_create_job( $no_action );
check( ! is_wp_error( $closed_job ) && cleanup_ai_job( $closed_job )['state'] === 'closed', 'NO ACTION saves reason without draft' );
check( is_wp_error( cleanup_ai_submit( $closed_job, ai_output() ) ), 'NO ACTION cannot generate a draft' );
$job_id = cleanup_ai_create_job( ai_plan() );
check( is_int( $job_id ), 'human creates researched AI job' );
$bot = wp_insert_user( array( 'user_login' => 'test-ai', 'user_pass' => wp_generate_password(), 'role' => 'cleanup_ai' ) );
wp_set_current_user( $bot );
check( is_wp_error( cleanup_ai_create_job( ai_plan() ) ), 'AI account cannot create or alter editorial plan' );
check( ! current_user_can( 'publish_cleanup_contents' ) && ! current_user_can( 'cleanup_review' ) && ! current_user_can( 'edit_cleanup_contents' ), 'AI role has no publish, review or native edit permission' );
$brief = rest_do_request( new WP_REST_Request( 'GET', '/cleanup/v1/ai/jobs/' . $job_id ) );
check( 200 === $brief->get_status() && isset( $brief->get_data()['sources'] ), 'authorized AI reads prepared brief' );
$bad = ai_output(); $bad['status'] = 'publish';
check( is_wp_error( cleanup_ai_submit( $job_id, $bad ) ), 'AI cannot specify publication state' );
$bad = ai_output(); $bad['claims'][0]['source_index'] = 999;
check( is_wp_error( cleanup_ai_submit( $job_id, $bad ) ), 'AI claim must refer to supplied source' );
$request = new WP_REST_Request( 'POST', '/cleanup/v1/drafts' );
$request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( array( 'job_id' => $job_id, 'output' => ai_output() ) ) );
$response = rest_do_request( $request ); $draft = $response->get_data()['draft_id'] ?? 0;
check( 200 === $response->get_status() && 'draft' === get_post_status( $draft ), 'AI REST output always saves a draft' );
check( 'needs_review' === get_post_meta( $draft, '_cleanup_ai_claims', true )[0]['status'], 'model verified assertion becomes needs_review' );
check( $draft === cleanup_ai_submit( $job_id, ai_output() ), 'identical retry returns existing draft' );
$changed = ai_output(); $changed['title'] = '変更した原稿';
check( is_wp_error( cleanup_ai_submit( $job_id, $changed ) ), 'different retry cannot overwrite draft' );
check( is_wp_error( cleanup_ai_review_claims( $draft, cleanup_ai_review_digest( $draft ), array() ) ), 'AI cannot verify claims' );
$request = new WP_REST_Request( 'POST', '/wp/v2/article/' . $article ); $request->set_param( 'content', 'AI must not overwrite' );
check( 403 === rest_do_request( $request )->get_status(), 'AI cannot overwrite existing article via core REST' );
wp_set_current_user( 0 );
check( 401 === rest_do_request( new WP_REST_Request( 'GET', '/cleanup/v1/ai/jobs/' . $job_id ) )->get_status(), 'anonymous AI brief access denied' );
wp_set_current_user( 1 );
check( is_wp_error( cleanup_approve( $draft, cleanup_fingerprint( $draft ) ) ), 'AI draft cannot publish before human fact check' );
check( ! is_wp_error( ai_prepare_for_review( $draft ) ), 'reviewer verifies claims with notes' );
check( ! is_wp_error( cleanup_approve( $draft, cleanup_fingerprint( $draft ) ) ) && 'publish' === get_post_status( $draft ), 'reviewed AI draft can be manually published' );
wp_update_post( array( 'ID' => $draft, 'post_content' => '<p>確認後に変更した本文</p>' ) );
check( is_wp_error( cleanup_approve( $draft, cleanup_fingerprint( $draft ) ) ), 'content change invalidates AI fact review' );
$original = fixture( 'article', 'ai-original' ); wp_set_object_terms( $original, 'industry', 'article_type' ); cleanup_approve( $original, cleanup_fingerprint( $original ) );
$original_hash = cleanup_fingerprint( $original ); $original_url = get_permalink( $original );
$rewrite_job = cleanup_ai_create_job( ai_plan( 'UPDATE', $original ) );
check( ! empty( cleanup_ai_brief( $rewrite_job )['published_originals'] ), 'rewrite brief includes current public original' );
$proposal = cleanup_ai_submit( $rewrite_job, ai_output() );
check( $original_hash === cleanup_fingerprint( $original ) && 'publish' === get_post_status( $original ), 'rewrite proposal does not change live article' );
ai_prepare_for_review( $proposal );
check( is_wp_error( cleanup_approve( $proposal, cleanup_fingerprint( $proposal ) ) ), 'rewrite proposal cannot publish as duplicate' );
check( $original === cleanup_ai_apply( $rewrite_job, cleanup_ai_review_digest( $proposal ) ), 'reviewer explicitly adopts rewrite' );
check( 'pending' === get_post_status( $original ) && $original_url === get_permalink( $original ), 'adoption preserves URL and requires final review' );
check( ! empty( get_post_meta( $rewrite_job, '_cleanup_before_apply', true ) ), 'pre-adoption content and metadata retained' );
check( is_wp_error( cleanup_approve( $original, cleanup_fingerprint( $original ) ) ), 'adopted record requires final fact recheck' );
ai_prepare_for_review( $original ); cleanup_approve( $original, cleanup_fingerprint( $original ) );
$conflict_job = cleanup_ai_create_job( ai_plan( 'UPDATE', $original ) ); $conflict_draft = cleanup_ai_submit( $conflict_job, ai_output() ); ai_prepare_for_review( $conflict_draft );
wp_update_post( array( 'ID' => $original, 'post_title' => '元記事を別途更新' ) );
check( is_wp_error( cleanup_ai_apply( $conflict_job, cleanup_ai_review_digest( $conflict_draft ) ) ), 'rewrite conflict prevents overwriting newer original' );
// These extra public fixtures are removed from publication before SEO HTTP checks.
wp_update_post( array( 'ID' => $original, 'post_status' => 'draft' ) );
$merge_target = fixture( 'article', 'merge-target' ); wp_set_object_terms( $merge_target, 'industry', 'article_type' ); cleanup_approve( $merge_target, cleanup_fingerprint( $merge_target ) );
$merge_source = fixture( 'article', 'merge-source' ); wp_set_object_terms( $merge_source, 'industry', 'article_type' ); cleanup_approve( $merge_source, cleanup_fingerprint( $merge_source ) );
$source_hash = cleanup_fingerprint( $merge_source );
$merge_plan = ai_plan( 'MERGE', $merge_target ); $merge_plan['merge_ids'] = array( $merge_target );
check( is_wp_error( cleanup_ai_create_job( $merge_plan ) ), 'MERGE cannot reference itself' );
$merge_plan['merge_ids'] = array( $merge_source ); $merge_job = cleanup_ai_create_job( $merge_plan ); $merge_draft = cleanup_ai_submit( $merge_job, ai_output() ); ai_prepare_for_review( $merge_draft );
check( $merge_target === cleanup_ai_apply( $merge_job, cleanup_ai_review_digest( $merge_draft ) ), 'MERGE uses reviewed proposal workflow' );
check( 'publish' === get_post_status( $merge_source ) && $source_hash === cleanup_fingerprint( $merge_source ), 'MERGE does not delete or modify source article' );
