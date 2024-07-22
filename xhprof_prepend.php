<?php
/**
 * Coding Pioneers extended XHProf Toolset
 *
 * Customized by Coding Pioneers GmbH.
 *
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 or later
 *
 * Based on xhprof_prepend.php by XHProf and the ddev Project. Licensed under the Apache License, Version 2.0.
 *
 * Original Authors: ddev contributors, Facebook, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * For further documentation, see README.md
 */

namespace CodingPioneers\XHProfToolset;

/**
 * Customize these values as needed, if possible you can use environment variables.
 *
 * @see README.md for details.
 */
define( 'WPDB_MIN_QUERY_TIME',  getenv( 'CP_XHPROF_WPDB_MIN_QUERY_TIME' ) ?: 0.1 );
define( 'LOG_LOCATION',         getenv( 'CP_XHPROF_LOG_LOCATION' )        ?: '/tmp/xhprof/' );
define( 'EMPTY_LOGS',           getenv( 'CP_XHPROF_EMPTY_LOGS' )          ?: 'skip' );
define( 'XHPROF_LIB_PATH',      getenv( 'CP_XHPROF_LIB_PATH' )            ?: '/var/xhprof/xhprof_lib/utils/xhprof_lib.php' );
define( 'XHPROF_RUNS_PATH',     getenv( 'CP_XHPROF_RUNS_PATH' )           ?: '/var/xhprof/xhprof_lib/utils/xhprof_runs.php' );
define( 'HTTP_LOG_SUFFIX',      getenv( 'CP_XHPROF_HTTP_LOG_SUFFIX' )     ?: '_http.log' );
define( 'DB_LOG_SUFFIX',        getenv( 'CP_XHPROF_DB_LOG_SUFFIX' )       ?: '_db.log' );
define( 'CLI_BASE_LINK',        getenv( 'CP_XHPROF_CLI_BASE_LINK' )       ?: '' );
define( 'XHPROF_RELATIVE_PATH', getenv( 'CP_XHPROF_RELATIVE_PATH' )       ?: '/xhprof/' );

/**
 * That's all there is to configure. The rest of the file should not need to be modified.
 */

/**
 * Class XHProf_Toolset
 *
 * This class is used to log additional information about the requests.
 */
class XHProfToolset {
	/**
	 * @var float $start_time The start time of the request.
	 */
	private float $start_time;

	/**
	 * @var float $end_time The end time of the request.
	 */
	private float $end_time;

	/**
	 * @var string $run_id The run id of the xhprof run.
	 */
	private string $run_id;

	/**
	 * @var string $run_id The run id of the xhprof run.
	 */
	private const APP_NAMESPACE = 'ddev';

	/**
	 * CP_XHProf constructor.
	 */
	public function __construct() {
		$this->start_time = microtime( true );

		xhprof_enable( XHPROF_FLAGS_MEMORY );
		register_shutdown_function( [ $this, 'completion' ] );
	}

	/**
	 * Log the request to a file.
	 *
	 * @return void
	 */
	private function log_request() {
		if ( php_sapi_name() === 'cli' ) {
			$request_type = 'CLI';
			$uri = implode( ' ', $_SERVER['argv'] );
			$uri_label = 'Command:';
			$referrer = 'none';
		} else {
			$uri = $_SERVER['REQUEST_URI'] ?? 'none';
			$uri_label = 'URI:';
			$referrer = $_SERVER['HTTP_REFERER'] ?? 'none';
			$local_domain =  ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . '/';
			$referrer = str_replace( $local_domain, '/', $referrer );
			$request_type = $_SERVER['REQUEST_METHOD'] ?? 'none';
			if ( $request_type === 'POST' ) {
				$num_post_vars = count( $_POST );
				// Add the number of bytes in the POST body to the log entry.
				$post_body_size = strlen( file_get_contents(  'php://input' ) );
				$request_type .= sprintf( '(%d vars, %.1f kb)', $num_post_vars, $post_body_size / 1024 );
			}
		}

		$runtime = $this->end_time - $this->start_time;

		// Construct the log entry.
		$log_entry = sprintf(
			"[%s] ∆ %.2f s | %s | %s %s | %s | Referrer: %s\n",
			date( 'Y-m-d H:i:s', (int) $this->start_time ) . ' - ' . date( 'H:i:s', (int) $this->end_time ),
			$runtime,
			$this->get_profiler_url(),
			$uri_label,
			$uri,
			$request_type,
			$referrer,
		);


		// Write the log entry to the file
		file_put_contents( LOG_LOCATION . 'xhprof.log', $log_entry, FILE_APPEND );
	}

	/**
	 * Log database queries to a file.
	 *
	 * @return void
	 */
	private function log_wpdb_queries(): void {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}

		if ( ! is_countable( $wpdb->queries ) ) {
			return;
		}
		$total_query_time = 0;
		$queries_skipped = 0;
		$log_entry = '';
		foreach ( $wpdb->queries as $query ) {
			$total_query_time += $query[1];
			if ( $query[1] < WPDB_MIN_QUERY_TIME ) {
				$queries_skipped ++;
				continue;
			}
			$log_entry .= sprintf(
				"[%.4f s] %s\n--------------------------------------------------------------------\n",
				$query[1],
				$query[0],
			);
		}
		if ( empty( $log_entry ) ) {
			$log_entry = 'No queries took longer than ' . WPDB_MIN_QUERY_TIME . " seconds.\n";
		}

		$log_intro = sprintf(
			"[%s] xhprof id: %s, %s\nTotal query time: %.4f s\nTotal queries: %d\nQueries skipped: %d (> %s s)\nShown queries %d\n--------------------------------------------------------------------\n",
			date('Y-m-d H:i:s'),
			$this->run_id,
			$this->get_profiler_url(),
			$total_query_time,
			count( $wpdb->queries ),
			$queries_skipped,
			WPDB_MIN_QUERY_TIME,
			count( $wpdb->queries ) - $queries_skipped,
		);

		$this->write_log( DB_LOG_SUFFIX, $log_entry, $log_intro );
	}

	/**
	 * Log HTTP requests to a file.
	 *
	 * @return void
	 */
	private function log_wp_http_requests(): void {
		if ( ! class_exists(\QM_Collectors::class ) ) {
			return;
		}
		$collector = \QM_Collectors::get( 'http' );

		if ( $collector === null ) {
			return;
		}
		$data = $collector->get_data();

		$http_requests = isset( $data->http ) && is_countable( $data->http ) ? count( $data->http ) : 0;

		$log_intro = sprintf(
			"[%s] xhprof id: %s, %s\nTotal http requests time: %.4f s\nTotal http requests: %d\n--------------------------------------------------------------------\n",
			date('Y-m-d H:i:s'),
			$this->get_profiler_url(),
			$this->run_id,
			$data['ltime'],
			$http_requests,
		);

		$log_content = '';
		if ( is_iterable( $data->http ) ) {
			foreach ( $data->http as $http_request ) {
				$log_content .= sprintf(
					"[%.4f s] %s %s %s (%.1f kb ↑, %.1f kb ↓)\nHeaders: %s\nBody: %s\n--------------------------------------------------------------------\n",
					$http_request['ltime'],
					$http_request['args']['method'],
					$http_request['info']['http_code'],
					$http_request['url'],
					$http_request['info']['size_upload'] / 1024,
					$http_request['info']['size_download'] / 1024,
					! empty( $http_request['args']['headers'] ) ? print_r( $http_request['args']['headers'], true ) : 'none',
					! empty( $http_request['args']['body'] ) ? print_r( $http_request['args']['body'], true ) : 'none',
				);
			}
		}

		$this->write_log( HTTP_LOG_SUFFIX, $log_content, $log_intro );
	}

	/**
	 * Write a log entry to a file.
	 *
	 * @param string $file_suffix Suffix for the logfile.
	 * @param string $content     Content to write to the log file.
	 * @param string $log_intro   Intro for the log file.
	 *
	 * @return void
	 */
	private function write_log( string $file_suffix, string $content, string $log_intro = '' ): void {
		if ( empty( $content ) && EMPTY_LOGS === 'skip' ) {
			// Only write a log entry if there is content to write.
			return;
		}

		file_put_contents( LOG_LOCATION . $this->run_id . $file_suffix, $log_intro . $content, FILE_APPEND );
	}

	/**
	 * Get the profiler URL.
	 *
	 * @return string
	 */
	private function get_profiler_url(): string {
		if ( php_sapi_name() === 'cli' ) {
			$base_link = CLI_BASE_LINK;
		} else {
			$base_link = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://" . $_SERVER['HTTP_HOST'];
		}

		return sprintf('%s?run=%s&source=%s', $base_link . XHPROF_RELATIVE_PATH, $this->run_id, self::APP_NAMESPACE );
	}

	/**
	 * Complete the xhprof profiling and write the data to the xhprof_html output and latest.
	 *
	 * @return void
	 */
	public function completion(): void {
		$xhprof_data = xhprof_disable();
		$this->end_time = microtime( true );

		require_once XHPROF_LIB_PATH;
		require_once XHPROF_RUNS_PATH;

		$xhprof_runs = new \XHProfRuns_Default();
		$this->run_id = $xhprof_runs->save_run( $xhprof_data, self::APP_NAMESPACE );

		if ( function_exists( 'add_action') ) {
			// We are within WordPress, so we hook into the shutdown action, to log everything that happens on shutdown().
			add_action( 'shutdown', [ $this, 'wp_shutdown'], 1000 );
		} else {
			// We are not within WordPress, so we just fire log request now.
			$this->log_request();
		}
	}

	/**
	 * Log the request, database queries and http requests.
	 *
	 * Called on the WordPress action 'shutdown'.
	 *
	 * @return void
	 */
	public function wp_shutdown() {
		$this->log_request();
		$this->log_wpdb_queries();
		$this->log_wp_http_requests();
	}
}

$uri = "none";
if ( ! empty( $_SERVER ) && array_key_exists( 'REQUEST_URI', $_SERVER ) ) {
	$uri = $_SERVER['REQUEST_URI'];
}
// Enable xhprof profiling if we're not on a xhprof page
if ( extension_loaded( 'xhprof' ) && strpos( $uri, '/xhprof' ) !== 0 ) {
	new XHProfToolset();
}
