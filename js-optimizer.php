<?php
/**
 * Version: 2.0
 * Date: 2017-12-16 - complete rewrite
 * Description: Move header scripts (non-async) to footer!
 */

function tinywp_js_optimizer($html) {

	$doc = new DOMDocument();
	$doc->loadHTML( $html, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR ) or die( 'html could not be loaded!' . PHP_EOL );

    // find header scripts that are not async
    // store them in a variable
    // and remove them physically from the DOM
	$xpath = new DOMXPath($doc);
	$list = $xpath->query("//head//script");
	$headerscripts = [];
	foreach( $list as $node ) {
		if ($node->hasAttribute('src') && !$node->hasAttribute('async')) {
			$headerscripts[] = $node->getAttribute('src');
			$node->parentNode->removeChild($node);
		}
	}

	// find the first script in body
	$bodyscripts = $xpath->query("//body/script");
	foreach( $bodyscripts as $script ) {
		if( $script->hasAttribute('src') && !$script->hasAttribute('async') ) {
			$first_body_script = $script;
			break;
		}
	}

    // insert collected header scripts just before the first script in body tag
	foreach( $headerscripts as $script ) {
		$node = $doc->createElement("script");
		$node->setAttribute('type', 'text/javascript');
		$node->setAttribute('src', $script);
		$first_body_script->parentNode->insertBefore($node, $first_body_script);
	}

	// save html
	$html = $doc->saveHTML();

    return $html;

}

function tinywp_trigger_js_optimizer() {
	global $enable_js_optimizer;
	if( $enable_js_optimizer == '1' ) {
		add_filter( 'wpsupercache_buffer', 'tinywp_js_optimizer' );
	}
}

function tinywp_js_optimizer_admin() {
	global $enable_js_optimizer, $wp_cache_config_file, $valid_nonce;

	$enable_js_optimizer = $enable_js_optimizer == '' ? '0' : $enable_js_optimizer;

	if(isset($_POST['enable_js_optimizer']) && $valid_nonce) {
		$enable_js_optimizer = (int)$_POST['enable_js_optimizer'];
		wp_cache_replace_line('^ *\$enable_js_optimizer', "\$enable_js_optimizer = '$enable_js_optimizer';", $wp_cache_config_file);
		$changed = true;
	} else {
		$changed = false;
	}
	$id = 'js-optimizer-section';
	?>
		<fieldset id="<?php echo $id; ?>" class="options">
		<h4><?php _e( 'Javascript Optimizer', 'wp-super-cache' ); ?></h4>
		<form name="wp_manager" action="" method="post">
		<label><input type="radio" name="enable_js_optimizer" value="1" <?php if( $enable_js_optimizer ) { echo 'checked="checked" '; } ?>/> <?php _e( 'Enabled', 'wp-super-cache' ); ?></label>
		<label><input type="radio" name="enable_js_optimizer" value="0" <?php if( !$enable_js_optimizer ) { echo 'checked="checked" '; } ?>/> <?php _e( 'Disabled', 'wp-super-cache' ); ?></label>
		<p><?php _e( 'Enables or disables the plugin to optimize Javascript.', 'wp-super-cache' ); ?></p>
		<?php
		if ($changed) {
			if ( $enable_js_optimizer )
				$status = __( "enabled", 'wp-super-cache' );
			else
				$status = __( "disabled", 'wp-super-cache' );
			echo "<p><strong>" . sprintf( __( "Javascript Optimizer is now %s", 'wp-super-cache' ), $status ) . "</strong></p>";
		}
	echo '<div class="submit"><input class="button-primary" ' . SUBMITDISABLED . 'type="submit" value="' . __( 'Update', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	?>
	</form>
	</fieldset>
	<?php

}

add_cacheaction( 'add_cacheaction', 'tinywp_trigger_js_optimizer' );
add_cacheaction( 'cache_admin_page', 'tinywp_js_optimizer_admin' );
