<div class="login-message-container">
	<?php if ( $attributes['show_title'] ) : ?>
		<h2><?php _e( 'AAI@EduHr Message', 'aaieduhr' ); ?></h2>
	<?php endif; ?>

    <p class="">
        <?php echo $attributes['auth_message']; ?>
    </p>

    <!-- Show errors if there are any -->
	<?php if ( count( $attributes['errors'] ) > 0 ) : ?>
        <p>
        <?php _e('Errors:', 'aaieduhr'); ?>
            <ul>
            <?php foreach ( $attributes['errors'] as $error ) : ?>
                <li class="login-error">
                    <?php echo $error; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </p>
	<?php endif; ?>

    <p>
        <a href="<?php echo home_url() ?>"><?php _e('Go to homepage', 'aaieduhr');?></a>
    </p>
</div>