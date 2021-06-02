<form action="<?php echo add_query_arg(
	array(
		'action'    => 'start-import',
		'import_id' => $import->get_id(),
	)
); ?>" method="post">
	<?php wp_nonce_field( 'start-import' ); ?>

	<?php if ( ! empty( $authors ) ) : ?>
		<h3><?php _e( 'Assign Authors', 'wordpress-importer' ); ?></h3>
		<p><?php _e( 'To make it simpler for you to edit and save the imported content, you may want to reassign the author of the imported item to an existing user of this site, such as your primary administrator account.', 'wordpress-importer' ); ?></p>
		<?php if ( $can_create_users ) : ?>
			<p><?php printf( __( 'If a new user is created by WordPress, a new password will be randomly generated and the new user&#8217;s role will be set as %s. Manually changing the new user&#8217;s details will be necessary.', 'wordpress-importer' ), esc_html( get_option( 'default_role' ) ) ); ?></p>
		<?php endif; ?>
		<ol id="authors">
			<?php foreach ( $authors as $n => $author ) : ?>
				<li><?php include __DIR__ . '/authors_select.php'; ?></li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<?php if ( $can_fetch_attachments ) : ?>
		<h3><?php _e( 'Import Attachments', 'wordpress-importer' ); ?></h3>
		<p>
			<input type="checkbox" value="1" name="fetch_attachments" id="import-attachments" />
			<label for="import-attachments"><?php _e( 'Download and import file attachments', 'wordpress-importer' ); ?></label>
		</p>
	<?php endif; ?>

	<p class="submit"><input type="submit" class="button" value="<?php esc_attr_e( 'Submit', 'wordpress-importer' ); ?>" /></p>
</form>
