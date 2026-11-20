<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
?>
<div class="shared-route-map" id="shared-route-map-panel" hidden>
    <?php echo $shared_map_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped component HTML. ?>
</div>
