<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Render-local template state.
$relative_labels = [
    'tomorrow' => __( 'Starts tomorrow', 'travel-app' ),
    /* translators: %d: number of days until the trip starts. */
    'startsMany' => __( 'Starts in %d days', 'travel-app' ),
    'yesterday' => __( 'Ended yesterday', 'travel-app' ),
    /* translators: %d: number of days since the trip ended. */
    'endedMany' => __( 'Ended %d days ago', 'travel-app' ),
    'active' => __( 'Active now', 'travel-app' ),
];
?>
<span data-trip-relative data-labels="<?php echo esc_attr( wp_json_encode( $relative_labels ) ); ?>" hidden></span>
