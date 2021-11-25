<?php
/**
 * Filter label template
 */

if ( ! ( ( isset( $show_label ) && $show_label ) ) ) {
	return;
}

$filter_label = get_post_meta( $filter_id, '_filter_label', true );

if ( empty( $filter_label ) ) {
	return;
}

?>
<div class="jet-filter-label"><?php echo $filter_label; ?></div>
