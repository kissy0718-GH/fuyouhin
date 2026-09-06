<?php
defined('ABSPATH') || exit;

function portal_match_partner($partner, $request) {
    if ($partner['status'] !== 'active' || empty($partner['verified_at'])) return false;
    if (!in_array($request['area'], json_decode($partner['areas'], true) ?: [], true)) return false;
    if (array_diff($request['items'], json_decode($partner['items'], true) ?: [])) return false;
    if (!empty($request['night']) && !$partner['night']) return false;
    if (!empty($request['same_day']) && !$partner['same_day']) return false;
    return (int)$partner['current_leads'] < (int)$partner['monthly_limit'];
}

function portal_fee($type, $rate, $conversion) {
    if ($type === 'conversion_fixed' || $type === 'lead') return (int)$rate;
    if ($type === 'conversion_percent') return (int)floor($conversion * $rate / 10000);
    return 0;
}

function portal_valid_transition($from, $to) {
    $map = ['received'=>['sent','cancelled'], 'sent'=>['quoted','converted','lost','cancelled'], 'quoted'=>['converted','lost','cancelled'], 'converted'=>['cancelled'], 'lost'=>[], 'cancelled'=>[]];
    return in_array($to, $map[$from] ?? [], true);
}

function portal_statuses() { return ['received'=>'受付済','sent'=>'送客済','quoted'=>'見積済','converted'=>'成約','lost'=>'未成約','cancelled'=>'キャンセル']; }

function portal_validate_request($input) {
    if (!is_array($input)) return new WP_Error('invalid', '入力内容を確認してください。', ['status'=>400]);
    $area = isset($input['area']) && is_string($input['area']) ? $input['area'] : '';
    $items = $input['items'] ?? [];
    if (!isset(portal_areas()[$area])) return new WP_Error('area', '地域を選択してください。', ['status'=>400]);
    if (!is_array($items) || !$items || count($items)>5 || array_filter($items, fn($v)=>!is_string($v)) || array_diff($items, array_keys(portal_items()))) return new WP_Error('items', '対象品目を選択してください。', ['status'=>400]);
    return ['area'=>$area,'items'=>array_values(array_unique($items)), 'night'=>!empty($input['night']), 'same_day'=>!empty($input['same_day'])];
}
