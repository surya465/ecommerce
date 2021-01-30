<?php
/**
 * @version 3.2.8
 */
?>
<span class="wc-stripe-card-icons-container">
	<?php foreach ( $cards as $id ): ?>
        <img class="wc-stripe-card-icon"
             src="<?php echo $assets_url . 'img/cards/' . $id . '.svg' ?>"/>
	<?php endforeach; ?>
</span>