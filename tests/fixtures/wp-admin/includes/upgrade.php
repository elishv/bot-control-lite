<?php
/**
 * Stand-in for wp-admin/includes/upgrade.php used by the plugin's own tests.
 *
 * @package AllInCrawlerAIAgentManager
 */

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * Record schema statements. The tests do not need a real database.
	 *
	 * @param string $queries SQL.
	 * @return array
	 */
	function dbDelta( $queries ) {
		if ( ! isset( $GLOBALS['botctl_test_dbdelta'] ) || ! is_array( $GLOBALS['botctl_test_dbdelta'] ) ) {
			$GLOBALS['botctl_test_dbdelta'] = array();
		}
		$GLOBALS['botctl_test_dbdelta'][] = $queries;
		return array();
	}
}
