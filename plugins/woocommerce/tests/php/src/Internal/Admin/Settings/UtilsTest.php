<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\Tests\Internal\Admin\Settings;

use Automattic\WooCommerce\Internal\Admin\Settings\Utils;
use WC_Unit_Test_Case;

/**
 * Payments settings utilities test.
 *
 * @class Utils
 */
class UtilsTest extends WC_Unit_Test_Case {
	/**
	 * Test applying new order mappings to a base order map.
	 *
	 * @dataProvider data_provider_order_map_apply_mappings
	 *
	 * @param array $base_order_map The base order map.
	 * @param array $new_order_map  The new order map.
	 * @param array $expected_order The expected order of the order map.
	 */
	public function test_order_map_apply_mappings( array $base_order_map, array $new_order_map, array $expected_order ) {
		// Act.
		$update_order_map = Utils::order_map_apply_mappings( $base_order_map, $new_order_map );

		// Assert.
		// The order map should be normalized with orders starting from 0.
		$expected_order_map = array_flip( $expected_order );
		$this->assertSame( $expected_order_map, $update_order_map );
	}

	/**
	 * Data provider for the test_order_map_apply_mappings test.
	 *
	 * @return array
	 */
	public function data_provider_order_map_apply_mappings(): array {
		return array(
			'both old and new maps are empty' => array(
				array(),
				array(),
				array(),
			),
			'old map is empty'                => array(
				array(),
				array(
					'new1' => 1,
					'new2' => 2,
					'new3' => 3,
				),
				array(
					'new1',
					'new2',
					'new3',
				),
			),
			'new map is empty'                => array(
				array(
					'old1' => 1,
					'old2' => 2,
					'old3' => 3,
				),
				array(),
				array(
					'old1',
					'old2',
					'old3',
				),
			),
			'both maps have the same ids'     => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 2, // The sorting should not matter.
					'provider2' => 3,
					'provider3' => 1,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'both maps have the same ids - non-consecutive order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 20, // The sorting should not matter.
					'provider2' => 30,
					'provider3' => 10,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'both maps have the same ids - non-consecutive order values #2' => array(
				array(
					'provider1' => 1,
					'provider2' => 10,
					'provider3' => 20,
				),
				array(
					'provider1' => 20, // The sorting should not matter.
					'provider2' => 30,
					'provider3' => 10,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'both maps have the same ids - non-consecutive order values #3' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider1' => 3, // The sorting should not matter.
					'provider2' => 5,
					'provider3' => 1,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'new map has only one provider - present in the old map #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider3' => 0,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'new map has only one provider - present in the old map #2' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider3' => 2,
				),
				array(
					'provider1',
					'provider3',
					'provider2',
				),
			),
			'new map has only one provider - present in the old map #3' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 2,
				),
				array(
					'provider2',
					'provider1',
					'provider3',
				),
			),
			'new map has only one provider - present in the old map #4' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 3,
				),
				array(
					'provider2',
					'provider3',
					'provider1',
				),
			),
			'new map has only one provider - not present in the old map #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 0,
				),
				array(
					'provider4',
					'provider1',
					'provider2',
					'provider3',
				),
			),
			'new map has only one provider - not present in the old map #2' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 999,
				),
				array(
					'provider1',
					'provider2',
					'provider3',
					'provider4',
				),
			),
			'new map has only one provider - not present in the old map #3' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 2,
				),
				array(
					'provider1',
					'provider4',
					'provider2',
					'provider3',
				),
			),
			'new map has only one provider - not present in the old map #4' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 1,
				),
				array(
					'provider4',
					'provider1',
					'provider2',
					'provider3',
				),
			),
			'new map has only one provider - not present in the old map; existing order value' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 2,
				),
				array(
					'provider1',
					'provider4', // The provider takes the place of the existing one.
					'provider2',
					'provider3',
				),
			),
			'new map is a subset of the old map - existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider2' => 1,
					'provider3' => 2,
				),
				array(
					'provider2',
					'provider3',
					'provider1',
				),
			),
			'new map is a subset of the old map - existing order values #2' => array(
				array(
					'provider2' => 2, // The sorting should not matter.
					'provider1' => 1,
					'provider3' => 3,
				),
				array(
					'provider3' => 2, // The sorting should matter.
					'provider2' => 1,
				),
				array(
					'provider2',
					'provider1',
					'provider3',
				),
			),
			'new map is a subset of the old map - non-existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 10,
					'provider2' => 20,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'new map is a subset of the old map - non-existing order values #2' => array(
				array(
					'provider1' => 1, // The sorting should not matter.
					'provider3' => 3,
					'provider2' => 2,
				),
				array(
					'provider2' => 20, // The sorting should not matter.
					'provider1' => 10,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'new map is a subset of the old map - both existing and non-existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 3,
					'provider2' => 20,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'new map is a subset of the old map - both existing and non-existing order values #2' => array(
				array(
					'provider2' => 2, // The sorting should not matter.
					'provider1' => 1,
					'provider3' => 3,
				),
				array(
					'provider2' => 20, // The sorting should not matter.
					'provider1' => 3,
				),
				array(
					'provider3',
					'provider1',
					'provider2',
				),
			),
			'new map not present in the old map - existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 1,
					'provider5' => 2,
				),
				array(
					'provider4',
					'provider5',
					'provider1',
					'provider2',
					'provider3',
				),
			),
			'new map not present in the old map - existing order values #2' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 2,
					'provider5' => 3,
				),
				array(
					'provider1',
					'provider4',
					'provider5',
					'provider2',
					'provider3',
				),
			),
			'new map not present in the old map - existing order values #3' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 1,
					'provider5' => 3,
				),
				array(
					'provider4',
					'provider1',
					'provider5',
					'provider2',
					'provider3',
				),
			),
			'new map not present in the old map - non-existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider5' => 30, // The sorting should not matter.
					'provider4' => 20,
				),
				array(
					'provider1',
					'provider2',
					'provider3',
					'provider4',
					'provider5',
				),
			),
			'new map not present in the old map - non-existing order values #2' => array(
				array(
					'provider1' => 10,
					'provider2' => 20,
					'provider3' => 30,
				),
				array(
					'provider4' => 1,
					'provider5' => 15,
				),
				array(
					'provider4',
					'provider1',
					'provider5',
					'provider2',
					'provider3',
				),
			),
			'new map not present in the old map - non-existing order values #3' => array(
				array(
					'provider1' => 10,
					'provider2' => 20,
					'provider3' => 30,
				),
				array(
					'provider4' => 15,
					'provider5' => 25,
				),
				array(
					'provider1',
					'provider4',
					'provider2',
					'provider5',
					'provider3',
				),
			),
			'new map not present in the old map - non-existing order values #4' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider4' => 2,
					'provider5' => 4,
				),
				array(
					'provider1',
					'provider4',
					'provider2',
					'provider5',
					'provider3',
				),
			),
			'new map not present in the old map - both existing and non-existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider4' => 2,
					'provider5' => 4,
				),
				array(
					'provider1',
					'provider4',
					'provider2',
					'provider5',
					'provider3',
				),
			),
			'new map not present in the old map - both existing and non-existing order values #2' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider4' => 2,
					'provider5' => 4,
				),
				array(
					'provider1',
					'provider4',
					'provider2',
					'provider5',
					'provider3',
				),
			),
			'new map not present in the old map - both existing and non-existing order values #3' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider4' => 0,
					'provider5' => 2,
				),
				array(
					'provider4',
					'provider1',
					'provider5',
					'provider2',
					'provider3',
				),
			),
			'new map not present in the old map - both existing and non-existing order values #4' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider4' => -1,
					'provider5' => 1,
				),
				array(
					'provider4',
					'provider5',
					'provider1',
					'provider2',
					'provider3',
				),
			),
			'new map partially present in the old map - existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider4' => 1,
					'provider1' => 4,
				),
				array(
					'provider4',
					'provider2',
					'provider1',
					'provider3',
				),
			),
			'new map partially present in the old map - existing order values #2' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => 1,
					'provider4' => 3,
				),
				array(
					'provider1',
					'provider4',
					'provider2',
					'provider3',
				),
			),
			'new map partially present in the old map - existing order values #3' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider1' => 3,
					'provider4' => 5,
				),
				array(
					'provider0',
					'provider2',
					'provider1',
					'provider4',
					'provider3',
				),
			),
			'new map partially present in the old map - non-existing order values #1' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => -1,
					'provider4' => 5,
				),
				array(
					'provider1',
					'provider2',
					'provider3',
					'provider4',
				),
			),
			'new map partially present in the old map - non-existing order values #2' => array(
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => -1,
					'provider4' => 2,
				),
				array(
					'provider1',
					'provider4',
					'provider2',
					'provider3',
				),
			),
			'new map partially present in the old map - non-existing order values #3' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider1' => 4,
					'provider4' => 6,
				),
				array(
					'provider0',
					'provider2',
					'provider1',
					'provider3',
					'provider4',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #1' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => 4,
					'provider4' => 5,
				),
				array(
					'provider0',
					'provider2',
					'provider3',
					'provider1',
					'provider4',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #2' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => 4,
					'provider4' => 6,
				),
				array(
					'provider0',
					'provider2',
					'provider3',
					'provider1',
					'provider4',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #3' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => 1,
					'provider4' => 2,
				),
				array(
					'provider0',
					'provider1',
					'provider4',
					'provider2',
					'provider3',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #4' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => -1,
					'provider4' => 0,
				),
				array(
					'provider1',
					'provider4',
					'provider0',
					'provider2',
					'provider3',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #5' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 4,
				),
				array(
					'provider1' => -1,
					'provider2' => 1,
					'provider4' => 2,
				),
				array(
					'provider1',
					'provider0',
					'provider2',
					'provider4',
					'provider3',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #6' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider1' => -1,
					'provider2' => 1,
					'provider4' => 4,
				),
				array(
					'provider1',
					'provider0',
					'provider2',
					'provider4',
					'provider3',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #7' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider0' => -1,
					'provider2' => 1,
					'provider4' => 2,
				),
				array(
					'provider0',
					'provider2',
					'provider4',
					'provider1',
					'provider3',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #8' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider0' => -1,
					'provider2' => 1,
					'provider4' => 5,
				),
				array(
					'provider0',
					'provider2',
					'provider1',
					'provider4',
					'provider3',
				),
			),
			'new map partially present in the old map - both existing and non-existing order values #9' => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 5,
				),
				array(
					'provider0' => -1,
					'provider2' => 1,
					'provider4' => 6,
				),
				array(
					'provider0',
					'provider2',
					'provider1',
					'provider3',
					'provider4',
				),
			),
			'move one item lower #1'          => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 2,
				),
				array(
					'provider0',
					'provider2',
					'provider1',
					'provider3',
				),
			),
			'move one item lower #2'          => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 3,
				),
				array(
					'provider0',
					'provider2',
					'provider3',
					'provider1',
				),
			),
			'move one item higher #1'         => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 0,
				),
				array(
					'provider1',
					'provider0',
					'provider2',
					'provider3',
				),
			),
			'move one item higher #2'         => array(
				array(
					'provider0' => 0,
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider2' => 0,
				),
				array(
					'provider2',
					'provider0',
					'provider1',
					'provider3',
				),
			),
		);
	}

	/**
	 * Test moving an id at a specific order in an order map.
	 *
	 * @dataProvider data_provider_order_map_move_at_order
	 *
	 * @param array  $order_map          The order map.
	 * @param string $id                 The id to move.
	 * @param int    $order              The order to move the id at.
	 * @param array  $expected_order_map The expected order map.
	 */
	public function test_order_map_move_at_order( array $order_map, string $id, int $order, array $expected_order_map ) {
		// Act.
		$updated_order_map = Utils::order_map_move_at_order( $order_map, $id, $order );

		// Assert.
		$this->assertSame( $expected_order_map, $updated_order_map );
	}

	/**
	 * Data provider for the test_order_map_move_at_order test.
	 *
	 * @return array
	 */
	public function data_provider_order_map_move_at_order(): array {
		return array(
			'id does not exist'                           => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
				),
				'provider3',
				1,
				array(
					'provider1' => 1, // Remains the same.
					'provider2' => 2,
				),
			),
			'id is already at the desired order'          => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
				),
				'provider1',
				1,
				array(
					'provider1' => 1, // Remains the same.
					'provider2' => 2,
				),
			),
			'id is not at the desired order - does not bump after it #1' => array(
				array(
					'provider1' => 0,
					'provider2' => 2,
				),
				'provider1',
				1,
				array(
					'provider1' => 1,
					'provider2' => 2,
				),
			),
			'id is not at the desired order - does not bump after it #2' => array(
				array(
					'provider1' => 0,
					'provider2' => 10,
					'provider3' => 20,
				),
				'provider2',
				11,
				array(
					'provider1' => 0,
					'provider2' => 11,
					'provider3' => 20,
				),
			),
			'id is not at the desired order - does not bump after it regardless of sorting' => array(
				array(
					'provider2' => 10,
					'provider1' => 0,
					'provider3' => 20,
				),
				'provider2',
				11,
				array(
					'provider2' => 11,
					'provider1' => 0,
					'provider3' => 20,
				),
			),
			'id is not at the desired order - move down #1' => array(
				array(
					'provider1' => 0,
					'provider2' => 10,
					'provider3' => 20,
				),
				'provider1',
				10,
				array(
					'provider1' => 10,
					'provider2' => 9,
					'provider3' => 20,
				),
			),
			'id is not at the desired order - move down #2' => array(
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
				'provider2',
				2,
				array(
					'provider1' => 0,
					'provider2' => 2,
					'provider3' => 1,
				),
			),
			'id is not at the desired order - move down #3' => array(
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
					'provider4' => 3,
				),
				'provider2',
				3,
				array(
					'provider1' => 0,
					'provider2' => 3,
					'provider3' => 1,
					'provider4' => 2,
				),
			),
			'id is not at the desired order - move down regardless of sorting' => array(
				array(
					'provider3' => 2,
					'provider2' => 1,
					'provider1' => 0,
				),
				'provider2',
				2,
				array(
					'provider3' => 1,
					'provider2' => 2,
					'provider1' => 0,
				),
			),
			'id is not at the desired order - move up #1' => array(
				array(
					'provider1' => 0,
					'provider2' => 10,
					'provider3' => 20,
				),
				'provider3',
				10,
				array(
					'provider1' => 0,
					'provider2' => 11,
					'provider3' => 10,
				),
			),
			'id is not at the desired order - move up #2' => array(
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
				'provider2',
				0,
				array(
					'provider1' => 1,
					'provider2' => 0,
					'provider3' => 2,
				),
			),
			'id is not at the desired order - move up #3' => array(
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
					'provider4' => 3,
				),
				'provider3',
				0,
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 0,
					'provider4' => 3,
				),
			),
		);
	}

	/**
	 * Test inserting an id at a specific order in an order map.
	 *
	 * @dataProvider data_provider_order_map_place_at_order
	 *
	 * @param array  $order_map          The order map.
	 * @param string $id                 The id to insert.
	 * @param int    $order              The order to insert the id at.
	 * @param array  $expected_order_map The expected order map.
	 */
	public function test_order_map_place_at_order( array $order_map, string $id, int $order, array $expected_order_map ) {
		// Act.
		$updated_order_map = Utils::order_map_place_at_order( $order_map, $id, $order );

		// Assert.
		$this->assertSame( $expected_order_map, $updated_order_map );
	}

	/**
	 * Data provider for the test_order_map_place_at_order test.
	 *
	 * @return array
	 */
	public function data_provider_order_map_place_at_order(): array {
		return array(
			'empty order map'                              => array(
				array(),
				'provider1',
				1,
				array(
					'provider1' => 1,
				),
			),
			'id exists and is already at the desired order' => array(
				array(
					'provider1' => 1,
				),
				'provider1',
				1,
				array(
					'provider1' => 1,
				),
			),
			'id exists and is not at the desired order'    => array(
				array(
					'provider1' => 2,
					'provider2' => 3,
					'provider3' => 5,
				),
				'provider1',
				1,
				array(
					'provider1' => 1,
					'provider2' => 3, // These are not bumped because there was none at the desired order.
					'provider3' => 5,
				),
			),
			'id exists and is not at the desired order #2' => array(
				array(
					'provider1' => 2,
					'provider2' => 3,
					'provider3' => 5,
				),
				'provider1',
				3,
				array(
					'provider1' => 3,
					'provider2' => 4, // These are bumped.
					'provider3' => 6,
				),
			),
			'id exists and is not at the desired order #3' => array(
				array(
					'provider1' => 2,
					'provider2' => 3,
					'provider3' => 5,
				),
				'provider2',
				4,
				array(
					'provider1' => 2,
					'provider2' => 4,
					'provider3' => 5, // These are not bumped because there was none at the desired order.
				),
			),
			'id exists and is not at the desired order #4' => array(
				array(
					'provider1' => 2,
					'provider2' => 3,
					'provider3' => 4,
					'provider4' => 7,
				),
				'provider2',
				4,
				array(
					'provider1' => 2,
					'provider2' => 4,
					'provider3' => 5, // These are bumped.
					'provider4' => 8,
				),
			),
			'id exists and is not at the desired order #5' => array(
				array(
					'provider1' => 2,
					'provider2' => 3,
					'provider3' => 5,
					'provider4' => 7,
				),
				'provider2',
				7,
				array(
					'provider1' => 2,
					'provider2' => 7,
					'provider3' => 5,
					'provider4' => 8, // This is bumped.
				),
			),
			'id exists and is not at the desired order #6' => array(
				array(
					'provider1' => 2,
					'provider2' => 3,
					'provider3' => 5,
					'provider4' => 7,
				),
				'provider3',
				2,
				array(
					'provider1' => 3, // All are bumped.
					'provider2' => 4,
					'provider3' => 2,
					'provider4' => 8,
				),
			),
			'id does not exist and order does not exist #1' => array(
				array(
					'provider1' => 1,
				),
				'provider2',
				2,
				array(
					'provider1' => 1,
					'provider2' => 2,
				),
			),
			'id does not exist and order does not exist #2' => array(
				array(
					'provider1' => 2,
				),
				'provider2',
				1,
				array(
					'provider1' => 2,
					'provider2' => 1,
				),
			),
			'id does not exist and order does not exist #3' => array(
				array(
					'provider1' => 2,
					'provider2' => 4,
				),
				'provider3',
				3,
				array(
					'provider1' => 2,
					'provider2' => 4,
					'provider3' => 3,
				),
			),
			'id does not exist and order does not exist #4' => array(
				array(
					'provider1' => 2,
					'provider2' => 4,
				),
				'provider3',
				0,
				array(
					'provider1' => 2,
					'provider2' => 4,
					'provider3' => 0,
				),
			),
			'id does not exist and order exists #1'        => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
				),
				'provider3',
				1,
				array(
					'provider1' => 2,
					'provider2' => 3,
					'provider3' => 1,
				),
			),
			'id does not exist and order exists #2'        => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
				),
				'provider3',
				2,
				array(
					'provider1' => 1,
					'provider2' => 3,
					'provider3' => 2,
				),
			),
			'id does not exist and order exists #3 - non-consecutive' => array(
				array(
					'provider1' => 0,
					'provider2' => 10,
					'provider3' => 20,
				),
				'provider4',
				0,
				array(
					'provider1' => 1,
					'provider2' => 11,
					'provider3' => 21,
					'provider4' => 0,
				),
			),
			'id does not exist and order exists #4 - non-consecutive' => array(
				array(
					'provider1' => 0,
					'provider2' => 10,
					'provider3' => 20,
				),
				'provider4',
				10,
				array(
					'provider1' => 0,
					'provider2' => 11,
					'provider3' => 21,
					'provider4' => 10,
				),
			),
		);
	}

	/**
	 * Test normalizing an order map.
	 *
	 * @dataProvider data_provider_order_map_normalize
	 *
	 * @param array $order_map          The order map.
	 * @param array $expected_order_map The expected order map.
	 */
	public function test_order_map_normalize( array $order_map, array $expected_order_map ) {
		// Act.
		$updated_order_map = Utils::order_map_normalize( $order_map );

		// Assert.
		$this->assertSame( $expected_order_map, $updated_order_map );
	}

	/**
	 * Data provider for the test_order_map_normalize test.
	 *
	 * @return array
	 */
	public function data_provider_order_map_normalize(): array {
		return array(
			'empty order map'                         => array(
				array(),
				array(),
			),
			'one item'                                => array(
				array(
					'provider1' => 1,
				),
				array(
					'provider1' => 0,
				),
			),
			'one item - already normalized'           => array(
				array(
					'provider1' => 0,
				),
				array(
					'provider1' => 0,
				),
			),
			'multiple items'                          => array(
				array(
					'provider1' => 1,
					'provider2' => 2,
					'provider3' => 3,
				),
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
			),
			'multiple items - already normalized'     => array(
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
			),
			'multiple items - non-consecutive orders' => array(
				array(
					'provider1' => 1,
					'provider2' => 20,
					'provider3' => 30,
				),
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
			),
			'multiple items - not sorted #1'          => array(
				array(
					'provider2' => 20,
					'provider1' => 1,
					'provider3' => 30,
				),
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
			),
			'multiple items - not sorted #2'          => array(
				array(
					'provider3' => 30,
					'provider2' => 20,
					'provider1' => 1,
				),
				array(
					'provider1' => 0,
					'provider2' => 1,
					'provider3' => 2,
				),
			),
		);
	}
}