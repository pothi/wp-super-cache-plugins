<?php

/*  Copyright 2016 Pothi Kalimuthu

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

/*  class WPSCJSLoader
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

class WPSCJSLoader {
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

    // Set location of WP Super Cache config file wp-cache-config.php from global var
    if (isset($GLOBALS['wp_cache_config_file']) and file_exists($GLOBALS['wp_cache_config_file']))
      $this->wp_cache_config_file = $GLOBALS['wp_cache_config_file'];
  }

  // returns object instance of WPSCJSLoader
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
    self::getInstance()->minify($html);
    return $html;
  }

  // Minifies string referenced by $html, if $this->enabled is TRUE
  public function minify(& $html) {
    if (!$this->enabled or $this->skipping_known_user)
      return;

    $header_script_js = '<!-- javascript loader --><script type="text/javascript">(function(e,t){typeof module!="undefined"&&module.exports?module.exports=t():typeof define=="function"&&define.amd?define(t):this[e]=t()})("$script",function(){function p(e,t){for(var n=0,i=e.length;n<i;++n)if(!t(e[n]))return r;return 1}function d(e,t){p(e,function(e){return t(e),1})}function v(e,t,n){function g(e){return e.call?e():u[e]}function y(){if(!--h){u[o]=1,s&&s();for(var e in f)p(e.split("|"),g)&&!d(f[e],g)&&(f[e]=[])}}e=e[i]?e:[e];var r=t&&t.call,s=r?t:n,o=r?e.join(""):t,h=e.length;return setTimeout(function(){d(e,function t(e,n){if(e===null)return y();!n&&!/^https?:\/\//.test(e)&&c&&(e=e.indexOf(".js")===-1?c+e+".js":c+e);if(l[e])return o&&(a[o]=1),l[e]==2?y():setTimeout(function(){t(e,!0)},0);l[e]=1,o&&(a[o]=1),m(e,y)})},0),v}function m(n,r){var i=e.createElement("script"),u;i.onload=i.onerror=i[o]=function(){if(i[s]&&!/^c|loade/.test(i[s])||u)return;i.onload=i[o]=null,u=1,l[n]=2,r()},i.async=1,i.src=h?n+(n.indexOf("?")===-1?"?":"&")+h:n,t.insertBefore(i,t.lastChild)}var e=document,t=e.getElementsByTagName("head")[0],n="string",r=!1,i="push",s="readyState",o="onreadystatechange",u={},a={},f={},l={},c,h;return v.get=m,v.order=function(e,t,n){(function r(i){i=e.shift(),e.length?v(i,r):v(i,t,n)})()},v.path=function(e){c=e},v.urlArgs=function(e){h=e},v.ready=function(e,t,n){e=e[i]?e:[e];var r=[];return!d(e,function(e){u[e]||r[i](e)})&&p(e,function(e){return u[e]})?t():!function(e){f[e]=f[e]||[],f[e][i](t),n&&n(r)}(e.join("|")),v},v.done=function(e){v([null],e)},v})</script>';

    $pattern_full_js = '#<script .+</script>#i';

    // find all javascript patterns "<script src=some.js"></script>"
    if( preg_match_all( $pattern_full_js, $html, $matches ) ) :

        $is_jquery = false;
        $is_jquery_dependent = false;
        // TODO: there may be some script that is independent of jquery and that may be declared before jquery
        // remember navigation.js? - current logic simply ignores such scripts
        foreach( $matches[0] as $val ):
            // to skip all javascripts before finding jquery
            if( preg_match( "/jquery/", $val ) ) $is_jquery = true;

            if( $is_jquery ) :
                $pattern_js_single_quote = "/src='([^']+)'/";
                $pattern_js_double_quote = '/src="([^"]+)"/';

                // extract only the "src" part
                if( preg_match( $pattern_js_double_quote, $val, $js_src ) || preg_match( $pattern_js_single_quote, $val, $js_src ) ) :

                    // insert the "src" part in footer
                    if( $is_jquery_dependent == false ) :
                        $footer_script_js = '<script type="text/javascript"> ' . "\n";
                        $footer_script_js = $footer_script_js . '$script( "' . $js_src[1] . '" , function() { try{jQuery.noConflict();}catch(e){}; ' . "\n";
                        $is_jquery_dependent = true;
                    else:
                        $footer_script_js = $footer_script_js . '$script( "' . $js_src[1] . '" );' . "\n";
                    endif; // $i

                    // now let's remove the whole script tag
                    $html = str_replace( $val . "\n", "", $html, $count );
                    // the following throws a generic "parse error"
                    // if( $count == false || $count > 1 ) echo "Error occurred while removing full script line/s.\n";

                else:
                    echo "Something went wrong while trying to get js_src.\n";
                endif; // preg_match: pattern_full_js " & '
            endif; // is_jquery
        endforeach; // $matches
        
        $footer_script_js = $footer_script_js . "});</script>\n";

    else:
        echo "No matching javascript found\n";
    endif; // preg_match_all pattern_full_js

    $html = str_replace( "</head>", $header_script_js . "</head>", $html, $count );
    if( $count == false || $count > 1 ) echo "Error occurred while inserting header_script_js.\n";

    $html = str_replace( "</body>", $footer_script_js . "</body>", $html, $count );
    if( $count == false || $count > 1 ) echo "Error occurred while inserting footer_script_js.\n";

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
    <h4>Javascript Loader</h4>
    <form name="wp_manager" action="<?php echo $action.'#'.$id; ?>" method="post">
      <label><input type="radio" name="<?php echo self::$config_varname; ?>" value="1" <?php if( $this->enabled ) { echo 'checked="checked" '; } ?>/> Enabled</label>
      <label><input type="radio" name="<?php echo self::$config_varname; ?>" value="0" <?php if( !$this->enabled ) { echo 'checked="checked" '; } ?>/> Disabled</label>
      <p>Enables or disables javascript loader in the cached HTML output. Disable this if you encounter any problems or need to read your source code.</p>
    <?php
    if ($this->changed) {
      echo "<p><strong>Javascript Loader is now ";
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
    $placeholder = 'X_WPSCJSLoader_escaped_string_'.count($this->escapedStrings);
    $this->escapedStrings[$placeholder] = $matches[1];
    return $placeholder;
  }
}


/* function WPSCJSLoader_settings
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

function WPSCJSLoader_settings() {
  // Update option if it has been changed
  if (isset($_POST[WPSCJSLoader::$config_varname]))
    WPSCJSLoader::getInstance()->updateOption($_POST[WPSCJSLoader::$config_varname]);

  // Print HTML Minify configuration section
  WPSCJSLoader::getInstance()->printOptionsForm($_SERVER['REQUEST_URI']);
}

add_cacheaction('cache_admin_page', 'WPSCJSLoader_settings');


/* function WPSCJSLoader_minify
 *
 * Adds filter to minify the WP Super Cache buffer when wpsupercache_buffer
 * filters are executed in wp-cache-phase2.php.
 *
 * Must be defined as a function in global scope to be usable with the
 * add_cacheaction() plugin hook system of WP Super Cache.
 */

function WPSCJSLoader_minify() {
  add_filter('wpsupercache_buffer', array('WPSCJSLoader', 'minifyPage'));
}

add_cacheaction('add_cacheaction', 'WPSCJSLoader_minify');


/* function WPSCJSLoader_check_known_user
 *
 * Checks filtered $_COOKIE contents and global var $wp_cache_not_logged_in 
 * to skip minification of dynamic page contents for a detected known user. 
 * Action is called inside wp_cache_get_cookies_values().
 *
 * Must be defined as a function in global scope to be usable with the
 * add_cacheaction() plugin hook system of WP Super Cache.
 */

function WPSCJSLoader_check_known_user($string) {
  if ($GLOBALS['wp_cache_not_logged_in'] and $string != '') {
    // Detected known user per logic in wp-cache-phase2.php 
    // (see line 378 in WP Super Cache 0.9.9.8)
    WPSCJSLoader::skipKnownUser();
  }
  return $string;
}

add_cacheaction('wp_cache_get_cookies_values', 'WPSCJSLoader_check_known_user');

?>
