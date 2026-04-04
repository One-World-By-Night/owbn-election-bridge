<?php
defined( 'ABSPATH' ) || exit;

// Available in scope: $query (WP_Query with candidate posts)
?>
<ul class="oeb-candidate-list">
	<?php while ( $query->have_posts() ) : $query->the_post(); ?>
		<li class="oeb-candidate-list__item">
			<h3 class="oeb-candidate-list__name">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h3>
			<?php if ( has_excerpt() ) : ?>
				<p class="oeb-candidate-list__excerpt"><?php the_excerpt(); ?></p>
			<?php endif; ?>
		</li>
	<?php endwhile; ?>
</ul>
