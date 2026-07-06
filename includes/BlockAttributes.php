<?php

namespace Outstand\WP\QueryLoop\Analytics;

/**
 * Wires the orderByPopularity attribute on core/query through block context.
 *
 * The attribute itself is registered on the JS side; here we only ensure the
 * value is exposed to child blocks (post-template) via provides_context.
 */
class BlockAttributes extends BaseModule {

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'register_block_type_args', [ $this, 'register_context' ], 10, 2 );
	}

	/**
	 * Add context to core/query and core/post-template.
	 *
	 * @param array<string, mixed> $args       Block type arguments.
	 * @param string               $block_type Block type name.
	 * @return array<string, mixed>
	 */
	public function register_context( array $args, string $block_type ): array {
		if ( $block_type === 'core/query' ) {
			$args['attributes']                      ??= [];
			$args['attributes']['orderByPopularity'] ??= [
				'type'    => 'boolean',
				'default' => false,
			];

			$args['provides_context'] = array_merge(
				$args['provides_context'] ?? [],
				[ 'outstand/orderByPopularity' => 'orderByPopularity' ]
			);
		}

		if ( $block_type === 'core/post-template' ) {
			$existing = $args['uses_context'] ?? [];
			if ( ! in_array( 'outstand/orderByPopularity', $existing, true ) ) {
				$existing[] = 'outstand/orderByPopularity';
			}
			$args['uses_context'] = $existing;
		}

		return $args;
	}
}
