<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<form class="edit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync>
    <input type="hidden" name="action" value="traveler_update_segment">
    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
    <input type="hidden" name="segment_index" value="<?php echo esc_attr( (string) $index ); ?>">
    <?php wp_nonce_field( 'traveler_update_segment_' . $trip_data['id'] . '_' . $index ); ?>
    <label class="field-wide">
        <?php esc_html_e( 'Title', 'traveler' ); ?>
        <input name="segment_title" value="<?php echo esc_attr( (string) ( $segment['title'] ?? '' ) ); ?>">
    </label>
    <label class="field-wide">
        <?php esc_html_e( 'Type', 'traveler' ); ?>
        <select name="segment_type">
            <?php
            $segment_type_labels = [
                'flight'   => __( 'Flight', 'traveler' ),
                'lodging'  => __( 'Lodging', 'traveler' ),
                'train'    => __( 'Train', 'traveler' ),
                'car'      => __( 'Rental car', 'traveler' ),
                'activity' => __( 'Activity', 'traveler' ),
                'other'    => __( 'Other', 'traveler' ),
            ];
            ?>
            <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $segment['type'] ?? 'other', $type ); ?>><?php echo esc_html( $segment_type_labels[ $type ] ?? ucfirst( $type ) ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field-wide">
        <?php esc_html_e( 'URL', 'traveler' ); ?>
        <input type="url" name="segment_url" value="<?php echo esc_attr( (string) ( $segment['url'] ?? '' ) ); ?>">
    </label>
    <?php
    $url_preview = isset( $segment['url_preview'] ) && is_array( $segment['url_preview'] ) ? $segment['url_preview'] : [];
    $url_preview_debug = isset( $segment['url_preview_debug'] ) && is_array( $segment['url_preview_debug'] ) ? $segment['url_preview_debug'] : [];
    $preview_status = ! empty( $url_preview_debug['status'] )
        ? (string) $url_preview_debug['status']
        : ( ! empty( $url_preview ) ? __( 'saved', 'traveler' ) : __( 'not fetched yet', 'traveler' ) );
    $preview_message = ! empty( $url_preview_debug['message'] )
        ? (string) $url_preview_debug['message']
        : __( 'Save this item to fetch preview metadata, or enter preview fields manually.', 'traveler' );
    ?>
    <details class="field-wide preview-edit">
        <summary>
            <?php esc_html_e( 'URL Preview', 'traveler' ); ?>
            <span><?php echo esc_html( $preview_status ); ?></span>
        </summary>
        <p class="preview-status"><?php echo esc_html( $preview_message ); ?></p>
        <label>
            <?php esc_html_e( 'Preview Title', 'traveler' ); ?>
            <input name="segment_url_preview_title" value="<?php echo esc_attr( (string) ( $url_preview['title'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'Preview Image URL', 'traveler' ); ?>
            <input type="url" name="segment_url_preview_image" value="<?php echo esc_attr( (string) ( $url_preview['image'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'Preview Description', 'traveler' ); ?>
            <textarea name="segment_url_preview_description"><?php echo esc_textarea( (string) ( $url_preview['description'] ?? '' ) ); ?></textarea>
        </label>
    </details>
    <label>
        <?php esc_html_e( 'Location', 'traveler' ); ?>
        <input name="segment_location" value="<?php echo esc_attr( (string) ( $segment['location'] ?? '' ) ); ?>">
    </label>
    <label>
        <?php esc_html_e( 'End Location', 'traveler' ); ?>
        <input name="segment_end_location" value="<?php echo esc_attr( (string) ( $segment['end_location'] ?? '' ) ); ?>">
    </label>
    <div class="date-time-group">
        <label>
            <?php esc_html_e( 'Start Date', 'traveler' ); ?>
            <input type="date" name="segment_date" value="<?php echo esc_attr( (string) ( $segment['date'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'Start Time', 'traveler' ); ?>
            <input type="time" name="segment_time" value="<?php echo esc_attr( (string) ( $segment['time'] ?? '' ) ); ?>">
        </label>
    </div>
    <div class="date-time-group">
        <label>
            <?php esc_html_e( 'End Date', 'traveler' ); ?>
            <input type="date" name="segment_end_date" value="<?php echo esc_attr( (string) ( $segment['end_date'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'End Time', 'traveler' ); ?>
            <input type="time" name="segment_end_time" value="<?php echo esc_attr( (string) ( $segment['end_time'] ?? '' ) ); ?>">
        </label>
    </div>
    <label class="field-wide">
        <?php esc_html_e( 'Details', 'traveler' ); ?>
        <textarea name="segment_details"><?php echo esc_textarea( (string) ( $segment['details'] ?? '' ) ); ?></textarea>
    </label>
    <div class="form-actions">
        <span class="form-secondary-actions">
            <button class="ghost-button" type="button" data-inline-edit-cancel><?php esc_html_e( 'Cancel', 'traveler' ); ?></button>
            <button class="delete-item-link" type="submit" form="<?php echo esc_attr( 'delete-segment-form-' . (string) $index ); ?>"><?php esc_html_e( 'Delete Item', 'traveler' ); ?></button>
        </span>
        <button type="submit"><?php esc_html_e( 'Save Item', 'traveler' ); ?></button>
    </div>
</form>
<form id="<?php echo esc_attr( 'delete-segment-form-' . (string) $index ); ?>" class="delete-segment-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this itinerary item?', 'traveler' ) ); ?>');">
    <input type="hidden" name="action" value="traveler_delete_segment">
    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
    <input type="hidden" name="segment_index" value="<?php echo esc_attr( (string) $index ); ?>">
    <?php wp_nonce_field( 'traveler_delete_segment_' . $trip_data['id'] . '_' . $index ); ?>
</form>
