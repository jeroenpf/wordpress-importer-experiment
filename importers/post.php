<?php
namespace ImporterExperiment;

class Post_Importer extends Partial_XML_Importer {
	public function parse( $state, $data ) {
		var_dump($data);
		$xml = $this->simplexml( $data );
		if ( \is_wp_error( $xml ) ) {
			var_dump($xml);exit;
		}

		if ( ! isset( $state['posts'] ) ) {
			$state['posts'] = array();
		}

		foreach ( $xml->channel->item as $item ) {
			$post = array(
				'post_title' => (string) $item->title,
				'guid' => (string) $item->guid,
			);
			$dc = $item->children( 'http://purl.org/dc/elements/1.1/' );
			$post['post_author'] = (string) $dc->creator;

			$content = $item->children( 'http://purl.org/rss/1.0/modules/content/' );
			$excerpt = $item->children( $this->namespaces['excerpt'] );
			$post['post_content'] = (string) $content->encoded;
			$post['post_excerpt'] = (string) $excerpt->encoded;

			$wp = $item->children( $this->namespaces['wp'] );
			$post['post_id'] = (int) $wp->post_id;
			$post['post_date'] = (string) $wp->post_date;
			$post['post_date_gmt'] = (string) $wp->post_date_gmt;
			$post['comment_status'] = (string) $wp->comment_status;
			$post['ping_status'] = (string) $wp->ping_status;
			$post['post_name'] = (string) $wp->post_name;
			$post['status'] = (string) $wp->status;
			$post['post_parent'] = (int) $wp->post_parent;
			$post['menu_order'] = (int) $wp->menu_order;
			$post['post_type'] = (string) $wp->post_type;
			$post['post_password'] = (string) $wp->post_password;
			$post['is_sticky'] = (int) $wp->is_sticky;

			if ( isset($wp->attachment_url) )
				$post['attachment_url'] = (string) $wp->attachment_url;

			foreach ( $item->category as $c ) {
				$att = $c->attributes();
				if ( isset( $att['nicename'] ) )
					$post['terms'][] = array(
						'name' => (string) $c,
						'slug' => (string) $att['nicename'],
						'domain' => (string) $att['domain']
					);
			}

			foreach ( $wp->postmeta as $meta ) {
				$post['postmeta'][] = array(
					'key' => (string) $meta->meta_key,
					'value' => (string) $meta->meta_value
				);
			}

			foreach ( $wp->comment as $comment ) {
				$meta = array();
				if ( isset( $comment->commentmeta ) ) {
					foreach ( $comment->commentmeta as $m ) {
						$meta[] = array(
							'key' => (string) $m->meta_key,
							'value' => (string) $m->meta_value
						);
					}
				}

				$post['comments'][] = array(
					'comment_id' => (int) $comment->comment_id,
					'comment_author' => (string) $comment->comment_author,
					'comment_author_email' => (string) $comment->comment_author_email,
					'comment_author_IP' => (string) $comment->comment_author_IP,
					'comment_author_url' => (string) $comment->comment_author_url,
					'comment_date' => (string) $comment->comment_date,
					'comment_date_gmt' => (string) $comment->comment_date_gmt,
					'comment_content' => (string) $comment->comment_content,
					'comment_approved' => (string) $comment->comment_approved,
					'comment_type' => (string) $comment->comment_type,
					'comment_parent' => (string) $comment->comment_parent,
					'comment_user_id' => (int) $comment->comment_user_id,
					'commentmeta' => $meta,
				);
			}

			$state['posts'][] = $post;

		}
 		return $state;
	}
}
$post_importer = new Post_Importer;

add_action( 'wordpress_importer_job_post', array( $post_importer, 'process_job' ), 10, 2 );
