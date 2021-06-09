<?php

namespace ImporterExperiment\Loggers;

use ImporterExperiment\Import;
use ImporterExperiment\Abstracts\Logger;

class CommentLogger extends Logger {

	const LOG_COMMENT_TYPE = 'import_log_entry';

	public function log( $message, $level, array $context = array() ) {
		$context_meta['level'] = $level;

		wp_insert_comment(
			array(
				'comment_post_ID' => $this->import->get_id(),
				'comment_content' => $message,
				'comment_meta'    => $context_meta,
				'comment_type'    => self::LOG_COMMENT_TYPE,
				'comment_agent'   => 'wordpress-importer',
			)
		);
	}
}
