<?php
$demo_control_id = isset( $demo_control_id ) ? (string) $demo_control_id : 'travel-app-demo';
$demo_control_value = isset( $demo_control_value ) ? (string) $demo_control_value : gmdate( 'Y-m-d\TH:i' );
$demo_control_date = substr( $demo_control_value, 0, 10 );
$demo_control_time = substr( $demo_control_value, 11, 5 ) ?: '12:00';
?>
<div class="demo-controls" data-demo-controls="<?php echo esc_attr( $demo_control_id ); ?>">
    <label>
        <?php esc_html_e( 'Demo date', 'travel-app' ); ?>
        <input type="date" data-demo-date value="<?php echo esc_attr( $demo_control_date ); ?>">
    </label>
    <label>
        <?php esc_html_e( 'Demo time', 'travel-app' ); ?>
        <input type="text" inputmode="numeric" pattern="[0-9]{2}:[0-9]{2}" placeholder="14:00" data-demo-time value="<?php echo esc_attr( $demo_control_time ); ?>">
    </label>
    <input type="hidden" data-demo-input value="<?php echo esc_attr( $demo_control_value ); ?>">
    <button type="button" class="ghost-button" data-demo-shift="-60"><?php esc_html_e( '-1 Hour', 'travel-app' ); ?></button>
    <button type="button" class="ghost-button" data-demo-shift="60"><?php esc_html_e( '+1 Hour', 'travel-app' ); ?></button>
    <button type="button" class="ghost-button" data-demo-shift="-1440"><?php esc_html_e( 'Previous Day', 'travel-app' ); ?></button>
    <button type="button" class="ghost-button" data-demo-shift="1440"><?php esc_html_e( 'Next Day', 'travel-app' ); ?></button>
    <button type="button" class="ghost-button" data-demo-now><?php esc_html_e( 'Today', 'travel-app' ); ?></button>
</div>
