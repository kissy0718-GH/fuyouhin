<?php
/**
 * Plugin Name: Honest Portal Core
 * Description: 不用品回収ポータルのコンテンツ・問い合わせ・収益管理MVP。
 * Version: 0.1.0
 */
defined('ABSPATH') || exit;
define('PORTAL_VERSION', '0.1.0');
require_once __DIR__ . '/src/domain.php';
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/content.php';
require_once __DIR__ . '/src/rest.php';
require_once __DIR__ . '/src/admin.php';
register_activation_hook(__FILE__, 'portal_install');
add_action('init', 'portal_register_content');
add_action('rest_api_init', 'portal_register_routes');
add_action('admin_menu', 'portal_admin_menu');
add_action('admin_post_portal_partner', 'portal_save_partner');
add_action('admin_post_portal_lead_status', 'portal_update_lead_status');
add_action('add_meta_boxes', 'portal_meta_boxes');
add_action('save_post', 'portal_save_metadata');
add_filter('wp_insert_post_data', 'portal_publication_gate', 20, 2);

function portal_demo() { return defined('PORTAL_DEMO_MODE') && PORTAL_DEMO_MODE; }
function portal_table($name) { global $wpdb; return $wpdb->prefix . 'portal_' . $name; }
function portal_can_manage() { return current_user_can('manage_portal'); }
function portal_now() { return gmdate('Y-m-d H:i:s'); }
function portal_areas() { return ['tokyo/setagaya' => '東京都 世田谷区', 'kanagawa/kawasaki' => '神奈川県 川崎市']; }
function portal_items() { return ['sofa'=>'ソファ','bed'=>'ベッド','table'=>'テーブル','storage'=>'収納家具','chair'=>'椅子']; }
function portal_categories() { return ['anti-scam'=>'失敗しない業者選び','industry'=>'業界の裏側','staff'=>'スタッフの本音','disposal'=>'処分方法','price'=>'料金・相場','company'=>'業者を探す','area'=>'地域から探す','case'=>'回収事例','municipality'=>'自治体情報','news'=>'お知らせ']; }
