<?php
/**
 * Category tree built while offers are emitted (path of names → sequential id).
 *
 * The state is plain arrays so a chunked build can persist it between requests.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Sequential-id category tree.
 */
class Marketplace_Category_Tree {

	/**
	 * Next id to hand out.
	 *
	 * @var int
	 */
	private $seq = 1;

	/**
	 * Category list in creation order: id => array( 'name' => string, 'parent' => int|null, 'path' => string ).
	 *
	 * @var array<int, array{name:string,parent:?int,path:string}>
	 */
	private $nodes = array();

	/**
	 * Path key → id. Path key is the segments joined with a unit separator.
	 *
	 * @var array<string, int>
	 */
	private $by_path = array();

	/**
	 * Register category paths ([['A','B'], ['C']]); returns the deepest id of the last path.
	 *
	 * @param array<int, array<int, string>> $paths Paths.
	 * @return int|null
	 */
	public function add_paths( array $paths ) {
		$last_id = null;

		foreach ( $paths as $path ) {
			$current   = array();
			$parent_id = null;

			foreach ( $path as $segment ) {
				$current[] = $segment;
				$key       = implode( "\x1f", $current );

				if ( ! isset( $this->by_path[ $key ] ) ) {
					$id                    = $this->seq++;
					$this->by_path[ $key ] = $id;
					$this->nodes[ $id ]    = array(
						'name'   => (string) $segment,
						'parent' => $parent_id,
						'path'   => $key,
					);
				}

				$parent_id = $this->by_path[ $key ];
				$last_id   = $parent_id;
			}
		}

		return $last_id;
	}

	/**
	 * Number of categories.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->nodes );
	}

	/**
	 * Categories in creation order.
	 *
	 * @return array<int, array{name:string,parent:?int,path:string}>
	 */
	public function all(): array {
		return $this->nodes;
	}

	/**
	 * Ids that point to a parent missing from the tree (should be none).
	 *
	 * @return int
	 */
	public function broken_parents(): int {
		$broken = 0;

		foreach ( $this->nodes as $node ) {
			if ( null !== $node['parent'] && ! isset( $this->nodes[ $node['parent'] ] ) ) {
				++$broken;
			}
		}

		return $broken;
	}

	/**
	 * Serializable state.
	 *
	 * @return array{seq:int,nodes:array<int,array>}
	 */
	public function to_array(): array {
		return array(
			'seq'   => $this->seq,
			'nodes' => $this->nodes,
		);
	}

	/**
	 * Restore a state produced by to_array().
	 *
	 * @param array $state State.
	 * @return self
	 */
	public static function from_array( array $state ): self {
		$tree      = new self();
		$tree->seq = isset( $state['seq'] ) ? max( 1, (int) $state['seq'] ) : 1;

		foreach ( (array) ( $state['nodes'] ?? array() ) as $id => $node ) {
			$id                 = (int) $id;
			$tree->nodes[ $id ] = array(
				'name'   => (string) ( $node['name'] ?? '' ),
				'parent' => isset( $node['parent'] ) && null !== $node['parent'] ? (int) $node['parent'] : null,
				'path'   => (string) ( $node['path'] ?? '' ),
			);
			$tree->by_path[ (string) ( $node['path'] ?? '' ) ] = $id;
		}

		return $tree;
	}

	/**
	 * Render the <category> elements.
	 *
	 * @param string $indent Indentation prefix.
	 * @return string
	 */
	public function to_xml( string $indent = '' ): string {
		$out = '';

		foreach ( $this->nodes as $id => $node ) {
			$attrs = ' id="' . (int) $id . '"';

			if ( null !== $node['parent'] ) {
				$attrs .= ' parentId="' . (int) $node['parent'] . '"';
			}

			$out .= $indent . '<category' . $attrs . '>' . Marketplace_Text::xml_text( $node['name'] ) . "</category>\n";
		}

		return $out;
	}
}
