<?php defined( 'ABSPATH' ) || exit; ?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?><a class="skip-link" href="#main">本文へ移動</a>
<header class="masthead"><div class="wrap masthead-inner"><a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><small>不用品回収・遺品整理</small><strong>悪徳業者撲滅プロジェクト</strong></a><p class="header-note">処分する前に、知っておきたいこと。<br>情報を確かめ、納得して選ぶために。</p></div>
<nav class="navigation" aria-label="カテゴリー"><div class="wrap"><ul><?php foreach ( portal_categories() as $slug => $label ) { ?><li><a href="<?php echo esc_url( home_url( '/' . $slug . '/' ) ); ?>"><?php echo esc_html( $label ); ?></a></li><?php } ?></ul></div></nav></header>
