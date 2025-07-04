<?php
/*
Plugin Name: MainWP Discourage Robots Checker Extension
Plugin URI:    https://www.georgenicolaou.me/plugins/mainwp-discourage-robots-checker
Description:   This extension checks the status of all your child sites to see if the Discourage robots is on or off. To help you make sure your "live" sites are not blocking indexing.
Version: 1.4
Author: George Nicolaou
Author URI: https://www.georgenicolaou.me/
Text Domain: mainwp-discourage-robots-checker
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with MainWP Discourage Robots checker. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

class MainWPDiscourageRobotsCheckerExtension {
  
    public function __construct() {
        add_filter('mainwp_getsubpages_sites', array(&$this, 'managesites_subpage'), 10, 1);

    }

    public function managesites_subpage($subPage) {    
        $subPage[] = array(
            'title'      => 'Discourage Robots Checker Extension',
            'slug'       => 'DiscourageRobotsCheckerExtension',
            'sitetab'    => true,
            'menu_hidden' => true,
            'callback'   => array('MainWPDiscourageRobotsCheckerExtension', 'renderPage'),
        );

        return $subPage;
    }

    public static function renderPage() {
        global $MainWPDiscourageRobotsCheckerExtensionActivator;

        if (isset($_POST['toggle_status'], $_POST['site_id'], $_POST['current_status']) && check_admin_referer('toggle_discourage_robots_' . intval($_POST['site_id']))) {
            $site_id = intval($_POST['site_id']);
            $current_status = sanitize_text_field($_POST['current_status']);
            self::toggle_discourage_robots_status($site_id, $current_status);
        }

        // Fetch all child-sites
        $websites = apply_filters('mainwp_getsites', $MainWPDiscourageRobotsCheckerExtensionActivator->getChildFile(), $MainWPDiscourageRobotsCheckerExtensionActivator->getChildKey(), null);
    
        if (is_array($websites)) {
            ?>      
            <div class="postbox">
                <div class="inside">
                    <p><?php _e('The MainWP Robots Checker Extension checks the status of all your child sites to see if the Discourage robots is on or off. To help you make sure your "live" sites are not blocking indexing.'); ?></p>
                </div>
            </div>
            <div class="postbox">
                <h3 class="mainwp_box_title"><?php _e('Get Child Sites'); ?></h3>
                <div class="inside">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Child Site'); ?></th>
                                <th><?php _e('Discourage Robots Status'); ?></th>
                                <th><?php _e('Toggle Status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Display a table row for each child site with their Discourage Robots status and toggle button
                            foreach ($websites as $site) {
                                // Fetch the Discourage Robots status for the child site
                                $site_id = $site['id'];
                                $discourage_robots = self::get_discourage_robots_status($site_id);
                                ?>
                                <tr>
                                    <td><?php echo $site['name']; ?></td>
                                    <td><?php echo $discourage_robots; ?></td>
                                    <td>
                                        <form method="post" action="">
                                            <?php wp_nonce_field('toggle_discourage_robots_' . $site_id); ?>
                                            <input type="hidden" name="site_id" value="<?php echo $site_id; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $discourage_robots; ?>">
                                            <button type="submit" name="toggle_status" class="button"><?php echo $discourage_robots === __('Off') ? __('Enable') : __('Disable'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
    
            // Enqueue your custom CSS file here
            wp_enqueue_style('mainwp-discourage-robots-checker', plugin_dir_url(__FILE__) . 'mainwp-discourage-robots-checker.css');
        }
    }
    
    
    // Helper function to get the Discourage Robots status for a specific child site
    private static function get_discourage_robots_status($site_id) {
        global $MainWPDiscourageRobotsCheckerExtensionActivator;

        $response = apply_filters(
            'mainwp_fetchurlauthed',
            array(
                'action' => 'get_discourage_robots_status'
            ),
            $MainWPDiscourageRobotsCheckerExtensionActivator->getChildFile(),
            $MainWPDiscourageRobotsCheckerExtensionActivator->getChildKey(),
            $site_id
        );

        $blog_public = isset($response['blog_public']) ? intval($response['blog_public']) : 1;

        return $blog_public ? __('Off') : __('On');
    }

    // Helper to toggle Discourage Robots status on a child site
    private static function toggle_discourage_robots_status($site_id, $current_status) {
        global $MainWPDiscourageRobotsCheckerExtensionActivator;

        // Switch to using text values so the child plugin
        // receives "on" or "off" rather than binary 0/1
        $new_value = ($current_status === __('Off')) ? 'on' : 'off';

        apply_filters(
            'mainwp_fetchurlauthed',
            array(
                'action' => 'set_discourage_robots_status',
                'value'  => $new_value,
            ),
            $MainWPDiscourageRobotsCheckerExtensionActivator->getChildFile(),
            $MainWPDiscourageRobotsCheckerExtensionActivator->getChildKey(),
            $site_id
        );
    }

    
      
}

class MainWPDiscourageRobotsCheckerExtensionActivator {
    protected $mainwpMainActivated = false;
    protected $childEnabled = false;
    protected $childKey = false;
    protected $childFile;
    protected $plugin_handle = 'mainwp-example-extension';

    public function __construct() {
        $this->childFile = __FILE__;
        add_filter('mainwp_getextensions', array(&$this, 'get_this_extension'));

        // This filter will return true if the main plugin is activated
        $this->mainwpMainActivated = apply_filters('mainwp_activated_check', false);

        if ($this->mainwpMainActivated !== false) {
            $this->activate_this_plugin();
        } else {
            //Because sometimes our main plugin is activated after the extension plugin is activated we also have a second step, 
            //listening to the 'mainwp-activated' action. This action is triggered by MainWP after initialization. 
            add_action('mainwp_activated', array(&$this, 'activate_this_plugin'));

        }        
        add_action('admin_notices', array(&$this, 'mainwp_error_notice'));
    }

    public function get_this_extension($pArray) {
        $pArray[] = array('plugin' => __FILE__, 'api' => $this->plugin_handle, 'mainwp' => false, 'callback' => array(&$this, 'settings'));
        return $pArray;
    }

    public function settings() {
        //The "mainwp-pageheader-extensions" action is used to render the tabs on the Extensions screen. 
        //It's used together with mainwp-pagefooter-extensions and mainwp-getextensions
        do_action('mainwp_pageheader_extensions', __FILE__);
        if ($this->childEnabled) {
            MainWPDiscourageRobotsCheckerExtension::renderPage();
        } else {
            ?><div class="mainwp_info-box-yellow"><?php _e("The Extension has to be enabled to change the settings."); ?></div><?php
        }
        do_action('mainwp_pagefooter_extensions', __FILE__);

    }

    public function activate_this_plugin() {
        //Checking if the MainWP plugin is enabled. This filter will return true if the main plugin is activated.
        $this->mainwpMainActivated = apply_filters('mainwp_activated_check', $this->mainwpMainActivated);


        // The 'mainwp-extension-enabled-check' hook. If the plugin is not enabled, this will return false, 
        // if the plugin is enabled, an array will be returned containing a key. 
        // This key is used for some data requests to our main
        $this->childEnabled = apply_filters('mainwp_extension_enabled_check', __FILE__);       

        $this->childKey = $this->childEnabled['key'];

        new MainWPDiscourageRobotsCheckerExtension();
    }

    public function mainwp_error_notice() {
        global $current_screen;
        if ($current_screen->parent_base == 'plugins' && $this->mainwpMainActivated == false) {
            echo '<div class="error"><p>MainWP Hello World! Extension ' . __('requires '). '<a href="http://mainwp.com/" target="_blank">MainWP</a>'. __(' Plugin to be activated in order to work. Please install and activate') . '<a href="http://mainwp.com/" target="_blank">MainWP</a> '.__('first.') . '</p></div>';
        }
    }

    public function getChildKey() {
        return $this->childKey;
    }

    public function getChildFile() {
        return $this->childFile;
    }
}

global $MainWPDiscourageRobotsCheckerExtensionActivator;
$MainWPDiscourageRobotsCheckerExtensionActivator = new MainWPDiscourageRobotsCheckerExtensionActivator();
