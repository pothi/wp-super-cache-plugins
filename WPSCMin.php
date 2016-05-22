<?php

/*  Copyright 2008-2015 Joel Hardi

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*  class WPSCMin
 *
 *  Singleton class add-on to WP Super Cache to interface with HTML Minify
 *  Author: Joel Hardi
 *  Author URI: http://lyncd.com/wpscmin/
 *  Version 0.7
 *
 *  WP Super Cache is a static caching plugin for WordPress
 *    For more information, see: http://ocaoimh.ie/wp-super-cache/
 *
 *  Minify is an HTML/CSS/JS whitespace compression library in PHP
 *    For more information, see: http://code.google.com/p/minify/
 *
 *  This plugin to WP Super Cache is a simple Singleton class that adds 
 *  minification of static HTML and gzipped HTML files that WP Super Cache 
 *  saves to the filesystem. It also adds a on/off configuration panel to 
 *  WP Super Cache's WordPress settings page in the WordPress backend.
 *
 *  It requires that you download and install Minify into the WP Super Cache 
 *  plugins directory. See http://lyncd.com/wpscmin/ for instructions.
 */

class WPSCMin {
  private $enabled = FALSE; // Whether Minify is enabled
  private $changed = FALSE; // Whether value of $enabled has been changed

  // Full path and filename of wp-cache-config.php
  // (currently set from global var $wp_cache_config_file)
  private $wp_cache_config_file;
  // Name of global var (optionally) setting minify_path in wp-cache-config.php
  // (if doesn't exist, constructor sets minify_path to Super Cache plugin dir)
  private $config_varname_minify_path = 'cache_minify_path';
  private $minify_path;

  // Set to TRUE if $wp_cache_not_logged_in is enabled and 
  // wp_cache_get_cookies_values() returns a non-empty string. 
  // See wp-cache-phase2.php for "Not caching for known user."
  private $skipping_known_user = FALSE;

  private $escapedStrings = array();

  // Name of global config var set in wp-cache-config.php
  public static $config_varname = 'cache_minify';
  private static $instance;

  // Will run once only, since private and called only by getInstance()
  private function __construct() {
    // vars from wp-cache-config.php are initialized in global scope, so just 
    // get initial value of $enabled from there
    if (isset($GLOBALS[self::$config_varname]) and $GLOBALS[self::$config_varname])
      $this->enabled = TRUE;

    if (isset($GLOBALS[$this->config_varname_minify_path]) and 
        is_dir($GLOBALS[$this->config_varname_minify_path])) {
      $this->minify_path = $GLOBALS[$this->config_varname_minify_path];
    } else {
      $this->minify_path = dirname(__FILE__);
    }

    // Set location of WP Super Cache config file wp-cache-config.php from global var
    if (isset($GLOBALS['wp_cache_config_file']) and file_exists($GLOBALS['wp_cache_config_file']))
      $this->wp_cache_config_file = $GLOBALS['wp_cache_config_file'];
  }

  // returns object instance of WPSCMin
  public static function getInstance() {
    if (empty(self::$instance))
      self::$instance = new self();
    return self::$instance;
  }

  public static function skipKnownUser() {
    self::getInstance()->skipping_known_user = TRUE;
  }

  // Given string $html, returns minified version.
  // Preserves HTML comments appended by WP Super Cache
  public static function minifyPage($html) {
/*
    For versions of WP Super Cache 0.9.9.5 and earlier, uncomment the code
    section below, and comment out (or delete) the alternate code section
    for versions 0.9.9.6+.
*/
  //  $parts = preg_split('/\s*(<\!-- Dynamic page generated in [^->]+-->)\s*/', $html, 2, PREG_SPLIT_DELIM_CAPTURE);
  //  self::getInstance()->minify($parts[0]);
  //  return implode("\n", $parts);
/* 
    This is the simpler, regex hack-free version for WP Super Cache 0.9.9.6+.
*/
    self::getInstance()->minify($html);
    return $html;
/*
    End alternate versions
*/
  }

  // Minifies string referenced by $html, if $this->enabled is TRUE
  public function minify(& $html) {
    if (!$this->enabled or $this->skipping_known_user)
      return;

    // Include Minify components unless they have already been required
    // (i.e. by another plugin or user mod, or if WordPress were to use it)
    if (!class_exists('Minify_HTML')) {
      require_once("$this->minify_path/min/lib/Minify/HTML.php");
      // Add min/lib to include_path for CSS.php to be able to find components
      ini_set('include_path', ini_get('include_path').":$this->minify_path/min/lib");
      require_once("$this->minify_path/min/lib/Minify/CSS.php");
      require_once("$this->minify_path/min/lib/Minify/CSS/Compressor.php");
      require_once("$this->minify_path/min/lib/Minify/CommentPreserver.php");
      require_once("$this->minify_path/min/lib/JSMinPlus.php");
    }

    // Protect from minify any fragments escaped by
    // <!--[minify_skip]-->   protected text  <!--[/minify_skip]-->
    $this->escapedStrings = array();
    $html = preg_replace_callback(
      '#<\!--\s*\[minify_skip\]\s*-->((?:[^<]|<(?!<\!--\s*\[/minify_skip\]))+?)<\!--\s*\[/minify_skip\]\s*-->#i',
      array($this, 'strCapture'), $html);

    $html = Minify_HTML::minify($html,
             array('cssMinifier' => array('Minify_CSS', 'minify'),
                   'jsMinifier' => array('JSMinPlus', 'minify')));

    // Restore any escaped fragments
    $html = str_replace(array_keys($this->escapedStrings),
                        $this->escapedStrings, $html);
  }

  public function updateOption($value) {
    $enabled = (bool) $value;
    if ($enabled != $this->enabled) {
      $this->enabled = $enabled;
      $this->changed = TRUE;
      wp_cache_replace_line('^ *\$'.self::$config_varname, "\$".self::$config_varname." = " . var_export($enabled, TRUE) . ";", $this->wp_cache_config_file);
    }
  }

  public function printOptionsForm($action) {
    $id = 'htmlminify-section';
    ?>
    <fieldset id="<?php echo $id; ?>" class="options"> 
    <h4>HTML Minify</h4>
    <form name="wp_manager" action="<?php echo $action.'#'.$id; ?>" method="post">
      <label><input type="radio" name="<?php echo self::$config_varname; ?>" value="1" <?php if( $this->enabled ) { echo 'checked="checked" '; } ?>/> Enabled</label>
      <label><input type="radio" name="<?php echo self::$config_varname; ?>" value="0" <?php if( !$this->enabled ) { echo 'checked="checked" '; } ?>/> Disabled</label>
      <p>Enables or disables <a target="_blank" href="http://code.google.com/p/minify/">Minify</a> (stripping of unnecessary comments and whitespace) of cached HTML output. Disable this if you encounter any problems or need to read your source code.</p>
    <?php
    if ($this->changed) {
      echo "<p><strong>HTML Minify is now ";
      if ($this->enabled)
        echo "enabled";
      else
        echo "disabled";
      echo ".</strong></p>";
    }
    echo '<div class="submit"><input ' . SUBMITDISABLED . 'class="button-primary" type="submit" value="Update" /></div>';
    wp_nonce_field('wp-cache');
    ?>

    </form>
    </fieldset>
    <?php
  }

  private function strCapture($matches) {
    $placeholder = 'X_WPSCMin_escaped_string_'.count($this->escapedStrings);
    $this->escapedStrings[$placeholder] = $matches[1];
    return $placeholder;
  }
}


/* function WPSCMin_settings
 *
 * Inserts an "on/off switch" for HTML Minify into the WP Super Cache
 * configuration screen in WordPress' settings section.
 *
 * Must be defined as a function in global scope to be usable with the
 * add_cacheaction() plugin hook system of WP Super Cache that is documented
 * here:
 *
 * http://ocaoimh.ie/wp-super-cache-developers/
 */

function WPSCMin_settings() {
  // Update option if it has been changed
  if (isset($_POST[WPSCMin::$config_varname]))
    WPSCMin::getInstance()->updateOption($_POST[WPSCMin::$config_varname]);

  // Print HTML Minify configuration section
  WPSCMin::getInstance()->printOptionsForm($_SERVER['REQUEST_URI']);
}

add_cacheaction('cache_admin_page', 'WPSCMin_settings');


/* function WPSCMin_minify
 *
 * Adds filter to minify the WP Super Cache buffer when wpsupercache_buffer
 * filters are executed in wp-cache-phase2.php.
 *
 * Must be defined as a function in global scope to be usable with the
 * add_cacheaction() plugin hook system of WP Super Cache.
 */

function WPSCMin_minify() {
  add_filter('wpsupercache_buffer', array('WPSCMin', 'minifyPage'));
}

add_cacheaction('add_cacheaction', 'WPSCMin_minify');


/* function WPSCMin_check_known_user
 *
 * Checks filtered $_COOKIE contents and global var $wp_cache_not_logged_in 
 * to skip minification of dynamic page contents for a detected known user. 
 * Action is called inside wp_cache_get_cookies_values().
 *
 * Must be defined as a function in global scope to be usable with the
 * add_cacheaction() plugin hook system of WP Super Cache.
 */

function WPSCMin_check_known_user($string) {
  if ($GLOBALS['wp_cache_not_logged_in'] and $string != '') {
    // Detected known user per logic in wp-cache-phase2.php 
    // (see line 378 in WP Super Cache 0.9.9.8)
    WPSCMin::skipKnownUser();
  }
  return $string;
}

add_cacheaction('wp_cache_get_cookies_values', 'WPSCMin_check_known_user');

?>
