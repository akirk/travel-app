<form class="edit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="travel_app_update_segment">
    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
    <input type="hidden" name="segment_index" value="<?php echo esc_attr( (string) $index ); ?>">
    <?php wp_nonce_field( 'travel_app_update_segment_' . $trip_data['id'] . '_' . $index ); ?>
    <label class="field-wide">
        <?php esc_html_e( 'Title', 'travel-app' ); ?>
        <input name="segment_title" value="<?php echo esc_attr( (string) ( $segment['title'] ?? '' ) ); ?>">
    </label>
    <label class="field-wide">
        <?php esc_html_e( 'Type', 'travel-app' ); ?>
        <select name="segment_type">
            <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $segment['type'] ?? 'other', $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field-wide">
        <?php esc_html_e( 'URL', 'travel-app' ); ?>
        <input type="url" name="segment_url" value="<?php echo esc_attr( (string) ( $segment['url'] ?? '' ) ); ?>">
    </label>
    <?php
    $url_preview = isset( $segment['url_preview'] ) && is_array( $segment['url_preview'] ) ? $segment['url_preview'] : [];
    $url_preview_debug = isset( $segment['url_preview_debug'] ) && is_array( $segment['url_preview_debug'] ) ? $segment['url_preview_debug'] : [];
    $preview_status = ! empty( $url_preview_debug['status'] )
        ? (string) $url_preview_debug['status']
        : ( ! empty( $url_preview ) ? __( 'saved', 'travel-app' ) : __( 'not fetched yet', 'travel-app' ) );
    $preview_message = ! empty( $url_preview_debug['message'] )
        ? (string) $url_preview_debug['message']
        : __( 'Save this item to fetch preview metadata, or enter preview fields manually.', 'travel-app' );
    ?>
    <details class="field-wide preview-edit">
        <summary>
            <?php esc_html_e( 'URL Preview', 'travel-app' ); ?>
            <span><?php echo esc_html( $preview_status ); ?></span>
        </summary>
        <p class="preview-status"><?php echo esc_html( $preview_message ); ?></p>
        <label>
            <?php esc_html_e( 'Preview Title', 'travel-app' ); ?>
            <input name="segment_url_preview_title" value="<?php echo esc_attr( (string) ( $url_preview['title'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'Preview Image URL', 'travel-app' ); ?>
            <input type="url" name="segment_url_preview_image" value="<?php echo esc_attr( (string) ( $url_preview['image'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'Preview Description', 'travel-app' ); ?>
            <textarea name="segment_url_preview_description"><?php echo esc_textarea( (string) ( $url_preview['description'] ?? '' ) ); ?></textarea>
        </label>
    </details>
    <label>
        <?php esc_html_e( 'Location', 'travel-app' ); ?>
        <input name="segment_location" value="<?php echo esc_attr( (string) ( $segment['location'] ?? '' ) ); ?>">
    </label>
    <label>
        <?php esc_html_e( 'End Location', 'travel-app' ); ?>
        <input name="segment_end_location" value="<?php echo esc_attr( (string) ( $segment['end_location'] ?? '' ) ); ?>">
    </label>
    <div class="date-time-group">
        <label>
            <?php esc_html_e( 'Start Date', 'travel-app' ); ?>
            <input type="date" name="segment_date" value="<?php echo esc_attr( (string) ( $segment['date'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'Start Time', 'travel-app' ); ?>
            <input type="time" name="segment_time" value="<?php echo esc_attr( (string) ( $segment['time'] ?? '' ) ); ?>">
        </label>
    </div>
    <div class="date-time-group">
        <label>
            <?php esc_html_e( 'End Date', 'travel-app' ); ?>
            <input type="date" name="segment_end_date" value="<?php echo esc_attr( (string) ( $segment['end_date'] ?? '' ) ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'End Time', 'travel-app' ); ?>
            <input type="time" name="segment_end_time" value="<?php echo esc_attr( (string) ( $segment['end_time'] ?? '' ) ); ?>">
        </label>
    </div>
    <label class="field-wide">
        <?php esc_html_e( 'Details', 'travel-app' ); ?>
        <textarea name="segment_details"><?php echo esc_textarea( (string) ( $segment['details'] ?? '' ) ); ?></textarea>
    </label>
    <div class="form-actions">
        <button type="submit"><?php esc_html_e( 'Save Item', 'travel-app' ); ?></button>
    </div>
</form>
