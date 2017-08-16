<?php
/**
 * Single Food Meta
 *
 * This template can be overridden by copying it to yourtheme/restaurantpress/single-food/meta.php.
 *
 * HOWEVER, on occasion RestaurantPress will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.wpeverest.com/docs/restaurantpress/template-structure/
 * @author  WPEverest
 * @package RestaurantPress/Templates
 * @version 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="food_meta">

	<?php do_action( 'restaurantpress_product_meta_start' ); ?>

	<?php do_action( 'restaurantpress_product_meta_end' ); ?>

</div>
