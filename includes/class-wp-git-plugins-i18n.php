<?php
class WP_Git_Plugins_i18n {
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wp-git-plugins',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
