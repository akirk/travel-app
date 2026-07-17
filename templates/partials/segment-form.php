<form class="edit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="travel_app_update_segment">
    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
    <input type="hidden" name="segment_index" value="<?php echo esc_attr( (string) $index ); ?>">
    <?php wp_nonce_field( 'travel_app_update_segment_' . $trip_data['id'] . '_' . $index ); ?>
    <label class="field-wide">
        <?php esc_html_e( 'Title', 'travel-app' ); ?>
        <input name="segment_title" value="<?php echo esc_attr( (string) ( $segment['title'] ?? '' ) ); ?>">
    </label>
    <label>
        <?php esc_html_e( 'Type', 'travel-app' ); ?>
        <select name="segment_type">
            <?php foreach ( [ 'flight', 'hotel', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $segment['type'] ?? 'other', $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <?php esc_html_e( 'Date', 'travel-app' ); ?>
        <input type="date" name="segment_date" value="<?php echo esc_attr( (string) ( $segment['date'] ?? '' ) ); ?>">
    </label>
    <label>
        <?php esc_html_e( 'Time', 'travel-app' ); ?>
        <input type="time" name="segment_time" value="<?php echo esc_attr( (string) ( $segment['time'] ?? '' ) ); ?>">
    </label>
    <label>
        <?php esc_html_e( 'Location', 'travel-app' ); ?>
        <input name="segment_location" value="<?php echo esc_attr( (string) ( $segment['location'] ?? '' ) ); ?>">
    </label>
    <label class="field-wide">
        <?php esc_html_e( 'Details', 'travel-app' ); ?>
        <textarea name="segment_details"><?php echo esc_textarea( (string) ( $segment['details'] ?? '' ) ); ?></textarea>
    </label>
    <div class="form-actions">
        <button type="submit"><?php esc_html_e( 'Save Item', 'travel-app' ); ?></button>
    </div>
</form>
