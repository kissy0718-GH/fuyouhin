<?php get_header(); ?><main id="main" class="reading"><?php while ( have_posts() ) { the_post(); ?><h1><?php the_title(); ?></h1><?php the_content(); } ?></main><?php get_footer(); ?>
