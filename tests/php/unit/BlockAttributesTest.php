<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use WP_Block_Type_Registry;

/**
 * Tests for the BlockAttributes class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\BlockAttributes
 */
class BlockAttributesTest extends \WP_UnitTestCase {

	/**
	 * Core query has order by popularity attribute.
	 */
	public function test_core_query_has_order_by_popularity_attribute(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' );
		$this->assertNotNull( $type );
		$this->assertArrayHasKey( 'orderByPopularity', $type->attributes );
		$this->assertSame( 'boolean', $type->attributes['orderByPopularity']['type'] );
		$this->assertFalse( $type->attributes['orderByPopularity']['default'] );
	}

	/**
	 * Core query provides popularity context.
	 */
	public function test_core_query_provides_popularity_context(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' );
		$this->assertNotNull( $type );
		$this->assertArrayHasKey( 'outstand/orderByPopularity', $type->provides_context );
		$this->assertSame( 'orderByPopularity', $type->provides_context['outstand/orderByPopularity'] );
	}

	/**
	 * Post template uses popularity context.
	 */
	public function test_post_template_uses_popularity_context(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/post-template' );
		$this->assertNotNull( $type );
		$this->assertContains( 'outstand/orderByPopularity', (array) $type->uses_context );
	}
}
