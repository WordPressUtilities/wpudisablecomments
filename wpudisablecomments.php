<?php
defined('ABSPATH') || die;
/*
Plugin Name: WPU disable comments
Plugin URI: https://github.com/WordPressUtilities/wpudisablecomments
Update URI: https://github.com/WordPressUtilities/wpudisablecomments
Description: Disable all comments
Version: 2.3.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpudisablecomments
Requires at least: 6.2
Requires PHP: 8.0
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

// Thx to : https://wordpress.stackexchange.com/a/17936

/* ----------------------------------------------------------
  Remove from main widget
---------------------------------------------------------- */

add_action('admin_head', 'wpu_disable_comments_css');
function wpu_disable_comments_css() {
    echo "<style>#dashboard_right_now .comment-count, #dashboard_right_now .table_discussion, #latest-comments{ display:none; } {display:none !important;}</style>";
}

/* ----------------------------------------------------------
  Remove dashboard widget
---------------------------------------------------------- */

add_action('wp_dashboard_setup', 'wpu_disable_comments_remove_dashboard_widgets');
function wpu_disable_comments_remove_dashboard_widgets() {
    global $wp_meta_boxes;
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments'])) {
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);
    }
}

/* ----------------------------------------------------------
  Hide from admin menu & content
---------------------------------------------------------- */

add_action('admin_menu', 'wpu_disable_comments_admin_menus');
function wpu_disable_comments_admin_menus() {
    remove_menu_page('edit-comments.php');
    remove_submenu_page('options-general.php', 'options-discussion.php');
    $post_types = get_post_types(array('public' => true), 'names');
    foreach ($post_types as $post_type) {
        remove_meta_box('commentsdiv', $post_type, 'normal');
        remove_meta_box('commentstatusdiv', $post_type, 'normal');
    }
}

/* ----------------------------------------------------------
  Removes from all post types
---------------------------------------------------------- */

add_action('init', 'wpu_disable_comments_support', 100);
function wpu_disable_comments_support() {
    $post_types = get_post_types(array(
        'public' => true
    ), 'names');
    foreach ($post_types as $post_type) {
        remove_post_type_support($post_type, 'comments');
    }
}

/* ----------------------------------------------------------
  Removes from admin bar
---------------------------------------------------------- */

add_action('wp_before_admin_bar_render', 'wpu_disable_comments_admin_bar_render');
function wpu_disable_comments_admin_bar_render() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');

    /* Disable links to comments */
    $all_nodes = $wp_admin_bar->get_nodes();
    foreach ($all_nodes as $key => $val) {
        $current_node = $all_nodes[$key];
        if (!$current_node->href) {
            continue;
        }
        if (strpos($current_node->href, 'edit-comments') === false) {
            continue;
        }
        $wp_admin_bar->remove_node($key);
    }
}

/* ----------------------------------------------------------
  Send every comment to spam
---------------------------------------------------------- */

add_filter('pre_comment_approved', 'wpu_disable_comments_send_spam', '99', 2);
function wpu_disable_comments_send_spam($approved, $commentdata) {
    return 'spam';
}

/* ----------------------------------------------------------
  Force options
---------------------------------------------------------- */

/* Disable new comments */

add_filter('pre_option_default_comment_status', 'wpu_disable_comments_option_default_comment_status');
function wpu_disable_comments_option_default_comment_status($value) {
    return 'closed';
}

/* Disable new pings */

add_filter('pre_option_default_ping_status', 'wpu_disable_comments_option_default_ping_status');
function wpu_disable_comments_option_default_ping_status($value) {
    return 'closed';
}

/* ----------------------------------------------------------
  Disable pings
---------------------------------------------------------- */

add_action('pre_ping', 'wpu_disable_comments_disable_ping', 99);
function wpu_disable_comments_disable_ping(&$links) {
    $links = array();
}

/* ----------------------------------------------------------
  Disable comments RSS feed
---------------------------------------------------------- */

add_filter('feed_links_show_comments_feed', '__return_false', 99);

/* ----------------------------------------------------------
  Disable count
---------------------------------------------------------- */

add_filter('wp_count_comments', 'wpu_disable_comments_wp_count_comments', 10, 1);
function wpu_disable_comments_wp_count_comments($content) {
    $comment_count = array(
        'approved' => 0,
        'moderated' => 0,
        'awaiting_moderation' => 0,
        'spam' => 0,
        'trash' => 0,
        'post-trashed' => 0,
        'total_comments' => 0,
        'all' => 0
    );
    return (object) $comment_count;
}

/* ----------------------------------------------------------
  Simplify comment queries called from some functions
---------------------------------------------------------- */

add_filter('comments_clauses', 'wpu_disable_comments_comments_clauses', 999, 1);
function wpu_disable_comments_comments_clauses($clauses) {
    return array(
        'fields' => 'comment_ID',
        'join' => '',
        'where' => '',
        'orderby' => '',
        'limits' => 'LIMIT 0,0',
        'groupby' => ''
    );
}

/* ----------------------------------------------------------
  Remove from WP API
---------------------------------------------------------- */

add_filter('rest_endpoints', 'wpu_disable_comments_rest_endpoints');
function wpu_disable_comments_rest_endpoints($endpoints) {
    $endpoints_keys = array(
        '/wp/v2/comments',
        '/wp/v2/comments/(?P<id>[\\d]+)'
    );

    foreach ($endpoints_keys as $key) {
        if (isset($endpoints[$key])) {
            unset($endpoints[$key]);
        }
    }

    return $endpoints;
}

/* ----------------------------------------------------------
  Native condition to close comments
---------------------------------------------------------- */

add_filter('comments_open', '__return_false', 999, 1);

/* ----------------------------------------------------------
  Prevent access to the WordPress comments files
---------------------------------------------------------- */

add_filter('mod_rewrite_rules', 'wpu_disable_comments_rewrite_rules', 10, 1);
function wpu_disable_comments_rewrite_rules($rules) {
    $new_rules = "<IfModule mod_rewrite.c>
<FilesMatch (wp-comments-post\.php|wp-trackback\.php)>
Deny from all
</FilesMatch>
</IfModule>\n";
    return $new_rules . $rules;
}

/* ----------------------------------------------------------
  Prevent access to admin pages
---------------------------------------------------------- */

add_action('current_screen', function () {

    /* Only once */
    if (defined('WPU_DISABLE_COMMENTS_CHECK_PAGE')) {
        return;
    }
    define('WPU_DISABLE_COMMENTS_CHECK_PAGE', 1);

    /* Only logged-in non admin users */
    if (!is_admin() || !is_user_logged_in()) {
        return;
    }

    $screen = get_current_screen();
    if (!isset($screen->base)) {
        return;
    }

    if ($screen->base == 'edit-comments') {
        wp_redirect(admin_url(''));
        die;
    }
});
