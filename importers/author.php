<?php
namespace ImporterExperiment;

class Author_Importer extends Partial_XML_Importer {
	public function parse( $state, $data ) {
		$xml = $this->simplexml( $data );
		if ( \is_wp_error( $xml ) ) {
			var_dump($xml);exit;
		}

		$create_users = apply_filters( 'import_allow_create_users', true );

		if ( ! isset( $state['authors'] ) ) {
			$state['authors'] = array();
		}
		foreach ( $xml->xpath('/rss/channel/wp:author') as $author_arr ) {
			$a = $author_arr->children( $this->namespaces['wp'] );
			$login = (string) $a->author_login;
			$state['authors'][$login] = array(
				'author_id' => (int) $a->author_id,
				'author_login' => $login,
				'author_email' => (string) $a->author_email,
				'author_display_name' => (string) $a->author_display_name,
				'author_first_name' => (string) $a->author_first_name,
				'author_last_name' => (string) $a->author_last_name
			);
		}
 		return $state;
	}
}
$author_importer = new Author_Importer;

add_action( 'wordpress_importer_job_author', array( $author_importer, 'process_job' ), 10, 2 );
