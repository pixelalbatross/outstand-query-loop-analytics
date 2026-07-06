<?php

namespace Outstand\WP\QueryLoop\Analytics;

/**
 * Registers Popular Posts block patterns for the core/query variation.
 *
 * Each pattern's inner core/query carries the `outstand/popular-posts` namespace
 * and `orderByPopularity`, so it shows (namespace-filtered) in the Query Loop
 * setup screen for the variation and selecting one preserves the popularity
 * behaviour.
 */
class Patterns extends BaseModule {

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_patterns' ] );
	}

	/**
	 * Register the pattern category and the Popular Posts patterns.
	 */
	public function register_patterns(): void {
		register_block_pattern_category(
			'outstand-popular-posts',
			[ 'label' => __( 'Popular Posts', 'outstand-query-loop-analytics' ) ]
		);

		$patterns = [
			'outstand/popular-posts-titles' => [
				'title' => __( 'Popular Posts: titles & dates', 'outstand-query-loop-analytics' ),
				'file'  => 'popular-posts-titles.html',
			],
			'outstand/popular-posts-grid'   => [
				'title' => __( 'Popular Posts: grid with excerpt', 'outstand-query-loop-analytics' ),
				'file'  => 'popular-posts-grid.html',
			],
		];

		foreach ( $patterns as $name => $pattern ) {
			$content = $this->load_pattern( $pattern['file'] );
			if ( $content === '' ) {
				continue;
			}

			register_block_pattern(
				$name,
				[
					'title'      => $pattern['title'],
					'blockTypes' => [ 'core/query' ],
					'categories' => [ 'outstand-popular-posts' ],
					'content'    => $content,
				]
			);
		}
	}

	/**
	 * Load a pattern's serialized block markup from the patterns directory.
	 *
	 * @param string $file Pattern file name.
	 * @return string Block markup, or empty string if the file is missing/unreadable.
	 */
	private function load_pattern( string $file ): string {
		$path = OUTSTAND_QUERY_LOOP_ANALYTICS_PATH . 'patterns/' . $file;

		if ( ! is_readable( $path ) ) {
			Logger::error( 'load_pattern: missing pattern file ' . $file );
			return '';
		}

		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
