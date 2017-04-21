<?php
/**
 * Plugin Name: Woo Vendors Bookings Management
 * Description: Allows vendors to manage their bookings in the frontend
 * Version: 3.0.0
 * Author: Liam Bailey
 * Author URI: http://webbyscots.com/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
global $WVBD;
$WVBD = new WVBD;

class WVBD {

    private $textdomain = "woocommerce-vendors-bookings-management";
    private $required_plugins = array('woocommerce', 'woocommerce-bookings', 'woocommerce-product-vendors');

    function have_required_plugins() {
        $active_plugins = (array) get_option('active_plugins', array());
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        foreach ($this->required_plugins as $key => $required) {
            $required = (!is_numeric($key)) ? "{$key}/{$required}.php" : "{$required}/{$required}.php";
            if (!in_array($required, $active_plugins) && !array_key_exists($required, $active_plugins)) {
                return false;
            }
        }
        return true;
    }

    function __construct() {
        if (!$this->have_required_plugins()) {
            return;
        }
        register_activation_hook(__FILE__, array($this, 'rewrite_flush'));
        add_filter('rewrite_rules_array',array($this,'rewrite_rules'),-1);
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('parse_query', array($this, 'parse_request'), 10);
        add_filter('template_include', array($this, 'get_template'));
        add_action('wp', array($this, 'process_confirm'));
        add_action('woocommerce_before_my_account', array($this, 'add_vendor_dashboard_link'));
    }

    function parse_request($query) {
        if (!isset($query->query_vars['vendors-dashboard']))
            return $query;
        $this->vendor = get_term_by('slug', get_query_var('vendors-dashboard'), 'wcpv_product_vendors');
        $posts_per_page = 10;
        $paged = max(1, $query->query_vars['paged']);
        $statuses = apply_filters('woo_vendors_bookings_dashboard_statuses',array('unconfirmed','unpaid','pending','paid','confirmed','cancelled','complete'));
            $vendor_product_ids = WC_Product_Vendors_Utils::get_vendor_product_ids($this->vendor->term_id);
            $args = array(
                'vendors-dashboard' => $query->query_vars['vendors-dasboard'],
                'post_type' => 'wc_booking',
                'posts_per_page' => $posts_per_page,
                'post_status' => $statuses,
                'paged' => $paged
            );
            if ( version_compare( WC()->version, '3.0.0', '>=' ) && class_exists('WC_Booking_Data_Store') ) {
                $vendor_bookings = WC_Booking_Data_Store::get_booking_ids_by( array(
        			'object_id'   => $vendor_product_ids,
        			'object_type' => 'product',
        			'status'      => $statuses,
        		) );
                $args['post__in'] = $vendor_bookings;

            } else {
                $args = array_merge($args, array(
                    'meta_key' => '_booking_product_id',
                    'meta_value' => $vendor_product_ids,
                    'meta_compare' => 'IN'
                ));
            }
            $query->query_vars = apply_filters( 'woo_vendors_bookings_dashboard_query_vars', $args );
    }

    function add_vendor_dashboard_link() {
        $user = get_current_user_id();
        $vendor_id = get_user_meta($user, 'product_vendor', true);
        if ($vendor_id === "") {
            return;
        }
        $vendor_term = get_term($vendor_id, 'wcpv_product_vendors');
        $vendor_link = site_url() . "/vendors-dashboard/" . $vendor_term->slug;
        ?><h2>Vendors Dashboard</h2>
        <p>Please <a href='<?php echo $vendor_link; ?>'>click here</a> to manage your bookings</p><?php
    }

    function process_confirm() {
        if ($_GET['wvbd_action'] == "confirm") {
            if (get_post_type($_GET['booking_id']) != "wc_booking" || !wp_verify_nonce($_REQUEST['security'], 'vendor-booking-confirm-noncerator')) {
                wc_add_notice("Invalid request - booking not confirmed", "error");
                $url = site_url() . "/vendors-dashboard/" . get_query_var('vendors-dashboard') . "/";
                header("Location: " . $url);
            }
            $booking = new WC_Booking($_GET['booking_id']);
            $booking->update_status("confirmed");
            wc_add_notice("Booking " . $_GET['booking_id'] . " confirmed", "success");
        }
    }

    function get_template($template) {
        global $wp;
        return (isset($wp->query_vars['vendors-dashboard'])) ? trailingslashit(plugin_dir_path(__FILE__)) . "vendors-dashboard.php" : $template;
    }

    function add_query_vars($vars) {
        $vars[] = 'vendors-dashboard';
        return $vars;
    }

    function rewrite_rules($rules) {
        $new_rules = array('^vendors-dashboard/([^/]+)\/?$' => 'index.php?vendors-dashboard=$matches[1]&paged=$matches[2]',
        '^vendors-dashboard/([^/]+)/page/([0-9]+)\/?$' => 'index.php?vendors-dashboard=$matches[1]&paged=$matches[2]');
        return $new_rules + $rules;
    }

    function rewrite_flush() {
        flush_rewrite_rules();
    }

    function show_dashboard() {
        $vendor_data = get_term_meta($this->vendor->term_id,'vendor_data', true);
        if ( ! empty( $vendor_data['admins'] ) ) {
            if ( version_compare( WC_VERSION, '2.7.0', '>=' ) && is_array( $vendor_data['admins'] ) ) {
                $admin_ids = array_map( 'absint', $vendor_data['admins'] );
            } else {
                if ( is_array( $vendor_data['admins'] ) ) {
                    $admin_ids = array_filter( array_map( 'absint', $vendor_data['admins'] ) );
                } else {
                    $admin_ids = array_filter( array_map( 'absint', explode( ',', $vendor_data['admins'] ) ) );
                }
            }
        }
        if (!$this->vendor) {
            echo "<p class='error'>Vendor not found!</p>";
            return;
        }
        $user = get_current_user_id();
        if (!in_array($user->ID,$admin_ids) && !current_user_can('administrator') && $this->vendor->slug !== WC_Product_Vendors_Utils::get_logged_in_vendor('slug'))  {
            echo "<p class='error'>You do not have permission to view this page</p>";
            return;
        }
        $cols = array('Booking ID', 'Parent Order', 'Product', 'Date', 'Start Time', 'End Time', '# of Guests', 'Price', 'User', 'Date Applied', 'Status', 'Actions');
        $tabs = array('bookings');
        global $wp_query;
        $bookings = $wp_query->posts;
        $data = array();
        foreach ($bookings as $key => $booking) {
            $_booking = new WC_Booking($booking->ID);
            $user = get_post_meta($booking->ID, '_booking_customer_id', true);
            $actions = array();
            if ($booking->post_status != "cancelled") {
                array_push($actions, "cancel");
            }
            if ($booking->post_status == "pending-confirmation") {
                array_push($actions, "confirm");
            }
            $action_strings = array();
            foreach ($actions as $action) {
                $action_url = add_query_arg(array('wvbd_action' => $action, 'booking_id' => $booking->ID, 'security' => wp_create_nonce('vendor-booking-confirm-noncerator')), site_url() . "/vendors-dashboard/" . get_query_var('vendors-dashboard') . "/");
                $action_strings[] = ($action == "cancel") ? "<a href='" . $_booking->get_cancel_url("/vendors-dashboard/{$this->vendor->slug}") . "'>Cancel</a>" : "<a href='" . $action_url . "'>" . ucfirst($action) . "</a>";
            }
            $booking_product = $_booking->get_product();
            $data[$booking->ID] = array(
                'Booking ID' => $booking->ID,
                'Parent Order' => $booking->post_parent,
                'Product' => '<a href="'.get_edit_post_link($booking_product->id).'">' . $booking_product->post->post_title . '</a>',
                'Date' => date("Y-m-d", strtotime(get_post_meta($booking->ID, '_booking_start', true))),
                'Start Time' => date("h:ia", strtotime(get_post_meta($booking->ID, '_booking_start', true))),
                'End Time' => date("h:ia", strtotime(get_post_meta($booking->ID, '_booking_end', true))),
                '# of Guests' => count(get_post_meta($booking->ID, '_booking_persons', true)),
                'Price' => get_post_meta($booking->ID, '_booking_cost', true),
                'User' => (get_user_meta($user, 'billing_first_name', true) !== "") ? get_user_meta($user, 'billing_first_name', true) . " " . get_user_meta($user, 'billing_last_name', true) : $user->user_login,
                'Date Applied' => $booking->post_date,
                'Status' => $booking->post_status,
                'Actions' => implode(" | ", $action_strings)
            );
        }
        wc_print_notices();
        ?><div class="woocommerce-tabs"><?php
        if (count($tabs) > 1) {
            ?><ul class="tabs"><?php
            foreach ($tabs as $key => $tab) {
                $class = ($key == 0) ? "active" : "";
                ?><li class="dashboard_tab <?php echo $class; ?>">
                        <a href="#tab-<?php echo $tab; ?>"><?php echo ucwords($tab); ?></a>
                </li><?php
            } ?>
            </ul><?php
        }
        foreach ($tabs as $key => $tab) {
                    ?><div class="panel entry-content" id="tab-<?php echo $tab; ?>" style="display: block;">
                    <table class="<?php echo $tab; ?>-table">
                        <thead>
                            <tr>
            <?php foreach ($cols as $key => $col) { ?>
                                    <th><?php echo $col; ?></th><?php } ?>
                            </tr>
                        </thead>
                        <tbody>
            <?php foreach ($data as $booking_id => $booking) { ?>
                                <tr><?php foreach ($cols as $key => $col) { ?>
                                        <td><?php echo $booking[$col]; ?></td>
                <?php } ?>
                                </tr><?php } ?>

                        </tbody>
                    </table>
                </div><?php
        }
        ?><div class="pagination"><?php
        $big = 999999999; // need an unlikely integer
        echo paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => max(1, get_query_var('paged')),
            'total' => $wp_query->max_num_pages
        ));
        ?></div><!--Ends pagination //-->


    </div><!-- ends .woocommerce-tabs //--><?php
    }

    //HELPERS
    public function filter_booking_products( $item ) {
		$product_ids = WC_Product_Vendors_Utils::get_vendor_product_ids($this->active_vendor);

		$booking_item = get_wc_booking( $item->ID );

		if ( is_object( $booking_item ) && !strstr($booking_item->status, "in-cart") && is_object( $booking_item->get_product() ) && $booking_item->get_product()->id && in_array( $booking_item->get_product()->id, $product_ids ) ) {
			return $item;
		}
	}

}
