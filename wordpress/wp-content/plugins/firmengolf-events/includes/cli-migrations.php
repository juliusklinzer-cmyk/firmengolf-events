<?php

if ( ! defined( 'ABSPATH' ) || ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

class FGE_CLI_Migrations {

	/**
	 * Migrate legacy event format keys to the new unified taxonomy.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print what would change, without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp firmengolf migrate-formats --dry-run
	 *     wp firmengolf migrate-formats
	 */
	public function migrate_formats( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );
		$map     = fge_get_event_format_legacy_map();

		WP_CLI::line( $dry_run ? '[DRY RUN] No data will be written.' : 'Running migration…' );

		$totals = [
			'firmengolf_event'   => $this->migrate_single_meta( 'firmengolf_event', '_fge_event_type', $map, $dry_run ),
			'firmengolf_request' => $this->migrate_single_meta( 'firmengolf_request', '_fge_event_goal', $map, $dry_run ),
			'firmengolf_partner' => $this->migrate_array_meta( 'firmengolf_partner', '_fge_event_formats', $map, $dry_run ),
		];

		WP_CLI::success( sprintf(
			'Done. Events: %d, Requests: %d, Partners: %d %s',
			$totals['firmengolf_event'],
			$totals['firmengolf_request'],
			$totals['firmengolf_partner'],
			$dry_run ? '(dry-run, not written)' : 'updated'
		) );
	}

	private function migrate_single_meta( string $post_type, string $meta_key, array $map, bool $dry_run ): int {
		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$changed = 0;
		foreach ( $posts as $post_id ) {
			$value = (string) get_post_meta( $post_id, $meta_key, true );
			if ( $value === '' || ! isset( $map[ $value ] ) ) {
				continue;
			}
			$new = $map[ $value ];
			WP_CLI::line( sprintf( '  %s #%d: %s → %s', $post_type, $post_id, $value, $new ) );
			if ( ! $dry_run ) {
				update_post_meta( $post_id, $meta_key, $new );
			}
			$changed++;
		}
		return $changed;
	}

	private function migrate_array_meta( string $post_type, string $meta_key, array $map, bool $dry_run ): int {
		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$changed = 0;
		foreach ( $posts as $post_id ) {
			$raw     = (array) get_post_meta( $post_id, $meta_key, true );
			$current = array_values( array_filter( array_map( 'strval', $raw ), static fn( $v ) => $v !== '' ) );
			if ( empty( $current ) ) {
				continue;
			}
			$mapped = array_values( array_unique( array_map( static fn( $v ) => $map[ $v ] ?? $v, $current ) ) );
			$a = $current;
			$b = $mapped;
			sort( $a );
			sort( $b );
			if ( $a === $b ) {
				continue;
			}
			WP_CLI::line( sprintf( '  %s #%d: [%s] → [%s]', $post_type, $post_id, implode( ',', $current ), implode( ',', $mapped ) ) );
			if ( ! $dry_run ) {
				update_post_meta( $post_id, $meta_key, $mapped );
			}
			$changed++;
		}
		return $changed;
	}
}

WP_CLI::add_command( 'firmengolf migrate-formats', [ new FGE_CLI_Migrations(), 'migrate_formats' ] );
