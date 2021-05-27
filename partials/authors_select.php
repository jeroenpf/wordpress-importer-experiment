<?php
_e( 'Import author:', 'wordpress-importer' );
echo ' <strong>' . esc_html( $author['author_display_name'] );
	if ( '1.0' != $wxr_version ) {
	echo ' (' . esc_html( $author['author_login'] ) . ')';
	}
	echo '</strong><br />';

if ( '1.0' != $wxr_version ) {
echo '<div style="margin-left:18px">';
	}

	if ( $can_create_users ) {
	echo '<label for="user_new_' . $n . '">';
		if ( '1.0' != $wxr_version ) {
		_e( 'or create new user with login name:', 'wordpress-importer' );
		$value = '';
		} else {
		_e( 'as a new user:', 'wordpress-importer' );
		$value = esc_attr( sanitize_user( $author['author_login'], true ) );
		}
		echo '</label>';

	echo ' <input type="text" id="user_new_' . $n . '" name="user_new[' . $n . ']" value="' . $value . '" /><br />';
	}

	echo '<label for="imported_authors_' . $n . '">';
		if ( ! $can_create_users && '1.0' == $wxr_version ) {
		_e( 'assign posts to an existing user:', 'wordpress-importer' );
		} else {
		_e( 'or assign posts to an existing user:', 'wordpress-importer' );
		}
		echo '</label>';

	echo ' ' . wp_dropdown_users(
	array(
	'name'            => "user_map[$n]",
	'id'              => 'imported_authors_' . $n,
	'multi'           => true,
	'show_option_all' => __( '- Select -', 'wordpress-importer' ),
	'show'            => 'display_name_with_login',
	'echo'            => 0,
	)
	);

	echo '<input type="hidden" name="imported_authors[' . $n . ']" value="' . esc_attr( $author['author_login'] ) . '" />';

	if ( '1.0' != $wxr_version ) {
	echo '</div>';
}
