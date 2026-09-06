<?php
defined('ABSPATH') || exit;

function portal_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $tables = [
        'partners' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
name varchar(190) NOT NULL,
partner_type varchar(20) NOT NULL DEFAULT 'external',
areas text NOT NULL,
items text NOT NULL,
night tinyint NOT NULL DEFAULT 0,
same_day tinyint NOT NULL DEFAULT 0,
monthly_limit int NOT NULL DEFAULT 10,
current_leads int NOT NULL DEFAULT 0,
usage_month varchar(7) NOT NULL,
fee_type varchar(32) NOT NULL DEFAULT 'conversion_fixed',
fee_rate int NOT NULL DEFAULT 0,
status varchar(20) NOT NULL DEFAULT 'inactive',
verified_at datetime DEFAULT NULL,
is_demo tinyint NOT NULL DEFAULT 0,
PRIMARY KEY  (id)",
        'leads' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
public_id varchar(48) DEFAULT NULL,
idempotency_key varchar(64) NOT NULL,
payload_hash varchar(64) NOT NULL,
area varchar(100) NOT NULL,
items text NOT NULL,
night tinyint NOT NULL DEFAULT 0,
same_day tinyint NOT NULL DEFAULT 0,
partner_id bigint(20) unsigned NOT NULL,
source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
source_page varchar(500) NOT NULL DEFAULT '',
status varchar(32) NOT NULL DEFAULT 'received',
fee_type varchar(32) NOT NULL,
fee_rate int NOT NULL DEFAULT 0,
conversion_value int NOT NULL DEFAULT 0,
consent_version varchar(40) NOT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
version int NOT NULL DEFAULT 1,
is_demo tinyint NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
UNIQUE KEY public_id (public_id),
UNIQUE KEY idempotency_key (idempotency_key),
KEY source_post_id (source_post_id),
KEY status_created (status,created_at)",
        'contacts' => "lead_id bigint(20) unsigned NOT NULL,
encrypted_payload longtext NOT NULL,
expires_at datetime NOT NULL,
PRIMARY KEY  (lead_id)",
        'ledger' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
lead_id bigint(20) unsigned NOT NULL,
partner_id bigint(20) unsigned NOT NULL,
event_key varchar(100) NOT NULL,
amount_yen bigint NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY event_key (event_key),
KEY lead_id (lead_id)",
        'events' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
lead_id bigint(20) unsigned NOT NULL,
actor_id bigint(20) unsigned NOT NULL,
event_type varchar(40) NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY lead_id (lead_id)"
    ];
    foreach ($tables as $name=>$definition) dbDelta('CREATE TABLE '.portal_table($name)." ($definition) $charset;");
    $role = get_role('administrator');
    if ($role) $role->add_cap('manage_portal');
    update_option('portal_schema_version', PORTAL_VERSION);
    portal_register_content();
    flush_rewrite_rules();
}

function portal_reset_month() {
    global $wpdb;
    $month = wp_date('Y-m');
    $wpdb->query($wpdb->prepare('UPDATE '.portal_table('partners').' SET current_leads=0, usage_month=%s WHERE usage_month<>%s', $month, $month));
}

function portal_partners($request) {
    global $wpdb;
    portal_reset_month();
    $rows = $wpdb->get_results('SELECT * FROM '.portal_table('partners').' ORDER BY id ASC', ARRAY_A);
    return array_values(array_filter($rows, fn($row)=> ((bool)$row['is_demo'] === portal_demo()) && portal_match_partner($row, $request)));
}

function portal_encrypt($value) {
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('連絡先暗号化機能が利用できません。');
    $secret = getenv('PORTAL_CONTACT_KEY');
    if (!$secret && !portal_demo()) throw new RuntimeException('連絡先保護キーを設定してください。');
    $key = hash('sha256', $secret ?: wp_salt('auth'), true);
    $iv = random_bytes(12);
    $cipher = openssl_encrypt(wp_json_encode($value), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('連絡先の保存に失敗しました。');
    return base64_encode($iv.$tag.$cipher);
}

function portal_decrypt($value) {
    $data = base64_decode($value, true);
    if (!$data || strlen($data)<28) return [];
    $key = hash('sha256', getenv('PORTAL_CONTACT_KEY') ?: wp_salt('auth'), true);
    $plain = openssl_decrypt(substr($data,28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($data,0,12), substr($data,12,16));
    return $plain ? json_decode($plain,true) : [];
}
