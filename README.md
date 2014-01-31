wpscmin
=======

WPSCMin Reloaded is a fork of WPSCMin originally developed by Joel Hardi. Details of his original work can be found at [http://lyncd.com/wpscmin/](http://lyncd.com/wpscmin/) .

## Compatibility
Tested with the following...
- WordPress 3.8.1
- [WP Super Cache](http://wordpress.org/plugins/wp-super-cache/) 1.4
- [Minify](https://code.google.com/p/minify/) 2.1.7

## Installation

#### Step 1:

Some background: Due to the way WordPress upgrades plugins, the WPSC plugins you upload to `wp-super-cache/plugins/` will be deleted when you upgrade WP Super Cache. We can avoid this by loading the plugins from elsewhere by setting `$wp_cache_plugins_dir` to the new location in `wp-cache-config.php`. Now, WP Super Cache will look there instead.

- Create a directory named 'wpscplugins' (you may choose any name, though) inside `WP_CONTENT_DIR` (that is usually `/wp-content/`)
- Download [minify](https://code.google.com/p/minify/downloads/list), extract and copy (or move) `/min/` directory to `WP_CONTENT_DIR/wpscplugins/`.
- Download [WPSCMin Reloaded plugin](https://raw2.github.com/pothi/wpscmin-reloaded/master/WPSCMin.php) and keep it at `WP_CONTENT_DIR/wpscplugins/`.

After the above steps, your `WP_CONTENT_DIR` might look like...

-- index.php
-- plugins/
-- themes/
-- uploads/
-- upgrades/
-- wpscplugins/
   -- WPSCMin.php
   -- min/
      -- builder/
      -- lib/
      -- config.php
      -- utils.php
      -- ...

#### Step 2:

- Open the file `wp-cache-config.php` (that may reside in your `WP_CONTENT_DIR`)
- Edit the line that defines `$wp_cache_plugins_dir`
- Assign the value for `$wp_cache_plugins_dir` to `WP_CONTENT_DIR . '/wpscplugins/'`

#### Step 3: Optional
- Copy (or move) all the existing plugins for WP Super Cache from `WP_PLUGINS_DIR/wp-super-cache/plugins/` to `WP_CONTENT_DIR/wpscplugins/`

## Question?

Please open up an issue in Github. Pull requests are welcome too.
