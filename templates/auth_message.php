<div class="login-message-container">
	<?php if ( isset($attributes['show_title']) ) : ?>
		<h2><?php esc_html_e( 'AAI@EduHr Message', 'wp-aaieduhr-auth' ); ?></h2>
	<?php endif; ?>

	<p class="">
		<?php esc_html($attributes['auth_message']); ?>
	</p>

	<!-- Show errors if there are any -->
	<?php if ( count( $attributes['errors'] ) > 0 ) : ?>
		<p>
			<ul>
			<?php foreach ( $attributes['errors'] as $error ) : ?>
				<li class="login-error">
					<?php esc_html($error); ?>
				</li>
			<?php endforeach; ?>
			</ul>
		</p>
	<?php endif; ?>

	<p>
		<a href="<?php esc_url(home_url()) ?>">
            <?php esc_html_e('Go to homepage', 'wp-aaieduhr-auth');?>
        </a>
	</p>
</div>