<?php
/**
 * Plugin Name: Bimonthly Report Manager
 * Description: Manage bimonthly updates with cascading workplan output selection and date-filtered post selection
 * Version: 2.1.0
 * Author: KC Web Programmers
 * Text Domain: bimonthly-report-manager
 */
if (!defined('ABSPATH')) exit;

define('BRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BRM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BRM_VERSION', '2.1.0');

/**
 * On activation, grant 'edit_bimonthly_updates' to administrator.
 * Other roles get the capability via the "Apply Capabilities" button in settings.
 */
register_activation_hook(__FILE__, 'brm_activate');
function brm_activate() {
    $admin = get_role('administrator');
    if ($admin) $admin->add_cap('edit_bimonthly_updates');
}

/**
 * On deactivation, remove 'edit_bimonthly_updates' from all roles that have it.
 */
register_deactivation_hook(__FILE__, 'brm_deactivate');
function brm_deactivate() {
    $all_roles = wp_roles()->roles;
    foreach (array_keys($all_roles) as $role_slug) {
        $role = get_role($role_slug);
        if ($role && $role->has_cap('edit_bimonthly_updates')) {
            $role->remove_cap('edit_bimonthly_updates');
        }
    }
}

class BimonthlyReportManager {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        $ajax_actions = array(
            'brm_get_bimonthly_list', 'brm_get_report_data', 'brm_get_filtered_posts',
            'brm_get_workplans', 'brm_get_goals', 'brm_get_objectives', 'brm_get_outputs',
            'brm_save_report_items', 'brm_delete_report_item',
            'brm_create_bimonthly', 'brm_delete_bimonthly', 'brm_create_highlight',
            'brm_export_pdf', 'brm_save_settings', 'brm_save_summary',
            'brm_get_all_updates_for_meta', 'brm_export_meta_pdf', 'brm_get_meta_preview',
            'brm_save_group_config', 'brm_apply_capabilities',
        );
        foreach ($ajax_actions as $action) {
            $method = 'ajax_' . str_replace('brm_', '', $action);
            add_action('wp_ajax_' . $action, array($this, $method));
        }

        add_shortcode('bimonthly_report', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
    }

    public function add_admin_menu() {
        add_menu_page('Bimonthly Updates', 'Bimonthly Updates', 'edit_bimonthly_updates', 'bimonthly-report-manager', array($this, 'admin_page'), 'dashicons-calendar-alt', 31);
        add_submenu_page('bimonthly-report-manager', 'Settings', 'Settings', 'manage_options', 'brm-pdf-settings', array($this, 'settings_page'));
        add_submenu_page('bimonthly-report-manager', 'Meta Report', 'Meta Report', 'edit_others_workplans', 'brm-meta-report', array($this, 'meta_report_page'));
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook === 'bimonthly-updates_page_brm-pdf-settings') { wp_enqueue_media(); return; }
        if ($hook !== 'toplevel_page_bimonthly-report-manager') return;

        wp_enqueue_script('brm-admin-js', BRM_PLUGIN_URL . 'js/bimonthly-report-manager.js', array('jquery'), BRM_VERSION, true);
        wp_enqueue_style('brm-admin-css', BRM_PLUGIN_URL . 'css/bimonthly-report-manager.css', array(), BRM_VERSION);
        wp_localize_script('brm-admin-js', 'brm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('brm_nonce'),
            'is_admin' => current_user_can('edit_others_workplans'),
        ));
    }

    public function enqueue_frontend_styles() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'bimonthly_report'))
            wp_enqueue_style('brm-frontend-css', BRM_PLUGIN_URL . 'css/bimonthly-frontend.css', array(), BRM_VERSION);
    }

    public function admin_page() { include BRM_PLUGIN_PATH . 'includes/admin-page.php'; }
    public function settings_page() { include BRM_PLUGIN_PATH . 'includes/settings-page.php'; }
    public function meta_report_page() { include BRM_PLUGIN_PATH . 'includes/meta-report-page.php'; }

    // =========================================================================
    // Post types config
    // =========================================================================

    private $allowed_post_types = array('news', 'event', 'products_and_resourc', 'bimonthly-highlight');

    /**
     * Get the date for a post based on its type
     */
    private function get_post_date($p) {
        switch ($p->post_type) {
            case 'news': return get_the_date('m/d/Y', $p->ID);
            case 'event':
                $raw = get_field('event_date_start', $p->ID);
                return $raw ? date('m/d/Y', strtotime($raw)) : '';
            case 'products_and_resourc':
                $raw = get_field('publication_date', $p->ID);
                return $raw ? date('m/d/Y', strtotime($raw)) : '';
            case 'bimonthly-highlight':
                $raw = get_field('highlight_date', $p->ID);
                return $raw ? date('m/d/Y', strtotime($raw)) : '';
            default: return get_the_date('m/d/Y', $p->ID);
        }
    }

    /**
     * Get the summary/description text for a post
     */
    private function get_summary_text($post_id) {
        $post_type = get_post_type($post_id);

        // For highlights, use highlight_text directly
        if ($post_type === 'bimonthly-highlight') {
            if (function_exists('get_field')) {
                return get_field('highlight_text', $post_id) ?: '';
            }
            return get_post_meta($post_id, 'highlight_text', true) ?: '';
        }

        // For other types, use internal_reporting_summary
        return $this->get_summary($post_id);
    }

    // =========================================================================
    // Summary generation
    // =========================================================================

    private function get_post_content_for_summary($post_id) {
        $post_type = get_post_type($post_id);
        switch ($post_type) {
            case 'news': case 'page': return get_post_field('post_content', $post_id);
            case 'products_and_resourc': return get_post_meta($post_id, 'product_description', true);
            case 'event': return get_post_meta($post_id, 'description', true);
            default: return get_post_field('post_content', $post_id);
        }
    }

    private function ensure_summary($post_id) {
        $post_type = get_post_type($post_id);

        // Highlights don't need AI summary — they use highlight_text
        if ($post_type === 'bimonthly-highlight') return $this->get_summary_text($post_id);

        $existing = $this->get_summary($post_id);
        if (!empty(trim($existing))) return $existing;

        $api_key = get_option('chatgpt_api_key', '');
        if (empty($api_key)) return '';

        $content = $this->get_post_content_for_summary($post_id);
        if (empty($content)) return '';

        $content = wp_strip_all_tags(strip_shortcodes($content));
        $words = explode(' ', $content);
        if (count($words) > 3000) $content = implode(' ', array_slice($words, 0, 3000));

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array('role' => 'system', 'content' => 'Summarize the provided content in under 100 words.'),
                    array('role' => 'user', 'content' => $content),
                ),
                'max_tokens' => 200,
            )),
        ));

        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['choices'][0]['message']['content'])) return '';

        $summary = trim($body['choices'][0]['message']['content']);
        if (function_exists('update_field')) update_field('internal_reporting_summary', $summary, $post_id);
        else update_post_meta($post_id, 'internal_reporting_summary', $summary);

        return $summary;
    }

    private function get_summary($post_id) {
        if (function_exists('get_field')) $val = get_field('internal_reporting_summary', $post_id);
        else $val = get_post_meta($post_id, 'internal_reporting_summary', true);
        return !empty($val) ? $val : '';
    }

    // =========================================================================
    // Permissions
    // =========================================================================

    private function get_user_group_ids($user_id = null) {
        if (!$user_id) $user_id = get_current_user_id();
        if (user_can($user_id, 'edit_others_workplans')) return array();

        $group_config = get_option('brm_group_config', array());
        $user = get_userdata($user_id);
        $ids = array();

        if ($user && !empty($group_config)) {
            // Build reverse lookup: role_slug => array of term_ids
            $role_to_terms = array();
            foreach ($group_config as $term_id => $cfg) {
                if (!empty($cfg['role'])) {
                    $role_to_terms[$cfg['role']][] = intval($term_id);
                }
            }

            foreach ($user->roles as $role) {
                if (isset($role_to_terms[$role])) {
                    $ids = array_merge($ids, $role_to_terms[$role]);
                }
            }
        }

        return array_unique($ids);
    }

    private function user_can_access_bimonthly($post_id, $user_id = null) {
        $allowed = $this->get_user_group_ids($user_id);
        if (empty($allowed)) return true;
        $terms = wp_get_post_terms($post_id, 'group', array('fields' => 'ids'));
        return !empty(array_intersect($terms, $allowed));
    }

    // =========================================================================
    // Region mapping (config-driven)
    // =========================================================================

    private function get_region_order() {
        $group_config = get_option('brm_group_config', array());
        $map = array();
        foreach ($group_config as $term_id => $cfg) {
            if (!empty($cfg['region'])) {
                $term = get_term($term_id, 'group');
                if ($term && !is_wp_error($term)) {
                    $map[$term->slug] = intval($cfg['region']);
                }
            }
        }
        return $map;
    }

    private function get_region_for_post($post_id) {
        $map = $this->get_region_order();
        $terms = wp_get_post_terms($post_id, 'group', array('fields' => 'slugs'));
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $slug) { if (isset($map[$slug])) return $map[$slug]; }
        }
        return 99;
    }

    // =========================================================================
    // AJAX: List / Create / Delete bimonthly posts
    // =========================================================================

    public function ajax_get_bimonthly_list() {
        check_ajax_referer('brm_nonce', 'nonce');
        $args = array('post_type' => 'bimonthly', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC');
        $gids = $this->get_user_group_ids();
        if (!empty($gids)) $args['tax_query'] = array(array('taxonomy' => 'group', 'field' => 'term_id', 'terms' => $gids));
        $posts = get_posts($args);
        $list = array();
        foreach ($posts as $p) {
            $groups = wp_get_post_terms($p->ID, 'group', array('fields' => 'names'));
            $list[] = array('id' => $p->ID, 'title' => $p->post_title, 'date' => get_the_date('m/d/Y', $p->ID), 'group' => !empty($groups) ? implode(', ', $groups) : '—');
        }
        wp_send_json_success($list);
    }

    public function ajax_create_bimonthly() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('edit_bimonthly_updates')) wp_send_json_error('Insufficient permissions');
        $title = sanitize_text_field($_POST['title']);
        $group = intval($_POST['group']);
        if (empty($title)) wp_send_json_error('Title is required');

        // Validate group assignment — center editors can only use their own group
        if ($group > 0) {
            $allowed = $this->get_user_group_ids();
            if (!empty($allowed) && !in_array($group, $allowed)) {
                wp_send_json_error('You can only create updates for your assigned group');
            }
        }

        $pid = wp_insert_post(array('post_title' => $title, 'post_type' => 'bimonthly', 'post_status' => 'publish', 'post_author' => get_current_user_id()));
        if (is_wp_error($pid)) wp_send_json_error('Failed to create update');
        if ($group > 0) wp_set_object_terms($pid, array($group), 'group', false);
        wp_send_json_success(array('id' => $pid));
    }

    public function ajax_delete_bimonthly() {
        check_ajax_referer('brm_nonce', 'nonce');
        $pid = intval($_POST['post_id']);
        if (!$this->user_can_access_bimonthly($pid)) wp_send_json_error('Insufficient permissions');
        $post = get_post($pid);
        if (!$post || $post->post_type !== 'bimonthly') wp_send_json_error('Update not found');
        delete_post_meta($pid, '_brm_prior_items');
        delete_post_meta($pid, '_brm_ahead_items');
        wp_delete_post($pid, true);
        wp_send_json_success();
    }

    // =========================================================================
    // AJAX: Create a Bi-Monthly Highlight post inline from the item modal
    // =========================================================================

    public function ajax_create_highlight() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('edit_bimonthly_updates')) wp_send_json_error('Insufficient permissions');

        $bimonthly_id = intval($_POST['bimonthly_id']);
        if (!$this->user_can_access_bimonthly($bimonthly_id)) wp_send_json_error('Insufficient permissions');

        $title = sanitize_text_field($_POST['title']);
        $date = sanitize_text_field($_POST['highlight_date']);
        $text = sanitize_textarea_field($_POST['highlight_text']);
        if (empty($title)) wp_send_json_error('Title is required');
        if (empty($date)) wp_send_json_error('Highlight date is required');

        $pid = wp_insert_post(array(
            'post_title' => $title, 'post_type' => 'bimonthly-highlight',
            'post_status' => 'publish', 'post_author' => get_current_user_id(),
        ));
        if (is_wp_error($pid)) wp_send_json_error('Failed to create highlight');

        if (function_exists('update_field')) {
            update_field('highlight_date', $date, $pid);
            update_field('highlight_text', $text, $pid);
        } else {
            update_post_meta($pid, 'highlight_date', $date);
            update_post_meta($pid, 'highlight_text', $text);
        }

        // Assign the same group(s) as the parent update so it's findable in the date-filtered post select
        $groups = wp_get_post_terms($bimonthly_id, 'group', array('fields' => 'ids'));
        if (!empty($groups)) wp_set_object_terms($pid, $groups, 'group', false);

        $p = get_post($pid);
        wp_send_json_success(array('id' => $pid, 'title' => $p->post_title, 'date' => $this->get_post_date($p)));
    }

    // =========================================================================
    // AJAX: Get report data + hydrate
    // =========================================================================

    public function ajax_get_report_data() {
        check_ajax_referer('brm_nonce', 'nonce');
        $pid = intval($_POST['post_id']);
        if (!$this->user_can_access_bimonthly($pid)) wp_send_json_error('Insufficient permissions');
        $post = get_post($pid);
        if (!$post) wp_send_json_error('Update not found');

        $prior = get_post_meta($pid, '_brm_prior_items', true);
        $ahead = get_post_meta($pid, '_brm_ahead_items', true);
        $prior = !empty($prior) ? json_decode($prior, true) : array();
        $ahead = !empty($ahead) ? json_decode($ahead, true) : array();

        $groups = wp_get_post_terms($pid, 'group', array('fields' => 'all'));
        $gdata = array();
        foreach ($groups as $g) $gdata[] = array('id' => $g->term_id, 'name' => $g->name);

        wp_send_json_success(array(
            'id' => $post->ID, 'title' => $post->post_title, 'groups' => $gdata,
            'prior_items' => $this->hydrate_items($prior),
            'ahead_items' => $this->hydrate_items($ahead),
        ));
    }

    /**
     * Hydrate items. Each item now has an 'outputs' array (array of {workplan_id, goal_id, objective_id, output_index}).
     * For backwards compatibility, items with the old flat structure are migrated.
     */
    private function hydrate_items($items) {
        $hydrated = array();
        foreach ($items as $item) {
            $p = get_post($item['post_id']);
            if (!$p) continue;

            $pt_obj = get_post_type_object($p->post_type);
            $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $p->post_type;

            // Migrate old flat structure to new outputs array
            $outputs_raw = array();
            if (isset($item['outputs']) && is_array($item['outputs'])) {
                $outputs_raw = $item['outputs'];
            } elseif (!empty($item['objective_id'])) {
                // Old format — single output
                $outputs_raw = array(array(
                    'workplan_id' => isset($item['workplan_id']) ? $item['workplan_id'] : 0,
                    'goal_id' => isset($item['goal_id']) ? $item['goal_id'] : 0,
                    'objective_id' => $item['objective_id'],
                    'output_index' => isset($item['output_index']) ? $item['output_index'] : 0,
                ));
            }

            // Hydrate each output
            $outputs_hydrated = array();
            foreach ($outputs_raw as $out) {
                $o = array(
                    'workplan_id' => intval($out['workplan_id']),
                    'goal_id' => intval($out['goal_id']),
                    'objective_id' => intval($out['objective_id']),
                    'output_index' => intval($out['output_index']),
                    'workplan_title' => '', 'goal_title' => '', 'goal_letter' => '',
                    'objective_title' => '', 'objective_number' => '',
                    'output_letter' => '', 'output_desc' => '',
                );

                if ($o['workplan_id']) { $wp = get_post($o['workplan_id']); $o['workplan_title'] = $wp ? $wp->post_title : ''; }
                if ($o['goal_id']) {
                    $gp = get_post($o['goal_id']);
                    $o['goal_title'] = $gp ? $gp->post_title : '';
                    $o['goal_letter'] = get_field('goal_letter', $o['goal_id']) ?: '';
                }
                if ($o['objective_id']) {
                    $op = get_post($o['objective_id']);
                    $o['objective_title'] = $op ? $op->post_title : '';
                    $o['objective_number'] = get_field('objective_number', $o['objective_id']) ?: '';
                    $outs = get_field('objective_outputs', $o['objective_id']);
                    if (!empty($outs) && isset($outs[$o['output_index']])) {
                        $o['output_letter'] = isset($outs[$o['output_index']]['output_letter']) ? $outs[$o['output_index']]['output_letter'] : '';
                        $o['output_desc'] = isset($outs[$o['output_index']]['output_description']) ? $outs[$o['output_index']]['output_description'] : '';
                    }
                }
                $outputs_hydrated[] = $o;
            }

            $hydrated[] = array(
                'post_id' => $p->ID, 'post_title' => $p->post_title,
                'post_type' => $p->post_type, 'post_type_label' => $pt_label,
                'permalink' => get_permalink($p->ID),
                'author' => get_the_author_meta('nickname', $p->post_author),
                'date' => $this->get_post_date($p),
                'summary' => $this->get_summary_text($p->ID),
                'outputs' => $outputs_hydrated,
            );
        }
        return $hydrated;
    }

    // =========================================================================
    // AJAX: Get date-filtered posts
    // =========================================================================

    public function ajax_get_filtered_posts() {
        check_ajax_referer('brm_nonce', 'nonce');
        $direction = sanitize_text_field($_POST['direction']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $bimonthly_id = intval($_POST['bimonthly_id']);

        if (!in_array($post_type, $this->allowed_post_types)) wp_send_json_error('Invalid post type');

        $now = current_time('Y-m-d');
        $settings = get_option('brm_pdf_settings', array());
        $months = isset($settings['timespan_months']) ? intval($settings['timespan_months']) : 3;

        if ($direction === 'prior') {
            $rs = date('Y-m-d', strtotime("-{$months} months", strtotime($now))); $re = $now;
        } else {
            $rs = $now; $re = date('Y-m-d', strtotime("+{$months} months", strtotime($now)));
        }

        $args = array('post_type' => $post_type, 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC');
        $bgroups = wp_get_post_terms($bimonthly_id, 'group', array('fields' => 'ids'));
        if (!empty($bgroups)) $args['tax_query'] = array(array('taxonomy' => 'group', 'field' => 'term_id', 'terms' => $bgroups));

        switch ($post_type) {
            case 'news':
                $args['date_query'] = array(array('after' => $rs, 'before' => $re, 'inclusive' => true));
                break;
            case 'event':
                $args['meta_query'] = array(array('key' => 'event_date_start', 'value' => array($rs.' 00:00:00', $re.' 23:59:59'), 'compare' => 'BETWEEN', 'type' => 'DATETIME'));
                break;
            case 'products_and_resourc':
                $args['meta_query'] = array(array('key' => 'publication_date', 'value' => array($rs.' 00:00:00', $re.' 23:59:59'), 'compare' => 'BETWEEN', 'type' => 'DATETIME'));
                break;
            case 'bimonthly-highlight':
                $args['meta_query'] = array(array('key' => 'highlight_date', 'value' => array($rs.' 00:00:00', $re.' 23:59:59'), 'compare' => 'BETWEEN', 'type' => 'DATETIME'));
                break;
        }

        $posts = get_posts($args);
        $results = array();
        foreach ($posts as $p) {
            $results[] = array('id' => $p->ID, 'title' => $p->post_title, 'date' => $this->get_post_date($p));
        }
        wp_send_json_success($results);
    }

    // =========================================================================
    // AJAX: Cascading workplan drill-down
    // =========================================================================

    public function ajax_get_workplans() {
        check_ajax_referer('brm_nonce', 'nonce');
        $bid = intval($_POST['bimonthly_id']);
        $args = array('post_type' => 'workplan', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC');
        $bg = wp_get_post_terms($bid, 'group', array('fields' => 'ids'));
        if (!empty($bg)) $args['tax_query'] = array(array('taxonomy' => 'group', 'field' => 'term_id', 'terms' => $bg));
        $posts = get_posts($args);
        $r = array();
        foreach ($posts as $p) $r[] = array('id' => $p->ID, 'title' => $p->post_title);
        wp_send_json_success($r);
    }

    public function ajax_get_goals() {
        check_ajax_referer('brm_nonce', 'nonce');
        $ids = get_field('related_work_plan_goals', intval($_POST['workplan_id']), false);
        $r = array();
        if (!empty($ids)) {
            $ids = array_unique(array_map('intval', $ids));
            foreach ($ids as $gid) {
                $g = get_post($gid); if (!$g) continue;
                $l = get_field('goal_letter', $gid);
                $r[] = array('id' => $g->ID, 'title' => $l ? $l.': '.$g->post_title : $g->post_title);
            }
        }
        wp_send_json_success($r);
    }

    public function ajax_get_objectives() {
        check_ajax_referer('brm_nonce', 'nonce');
        $ids = get_field('work_plan_objectives', intval($_POST['goal_id']), false);
        $r = array();
        if (!empty($ids)) {
            $ids = array_unique(array_map('intval', $ids));
            foreach ($ids as $oid) {
                $o = get_post($oid); if (!$o) continue;
                $n = get_field('objective_number', $oid);
                $r[] = array('id' => $o->ID, 'title' => $n ? $n.': '.$o->post_title : $o->post_title);
            }
        }
        wp_send_json_success($r);
    }

    public function ajax_get_outputs() {
        check_ajax_referer('brm_nonce', 'nonce');
        $outputs = get_field('objective_outputs', intval($_POST['objective_id']));
        $r = array();
        if (!empty($outputs)) {
            foreach ($outputs as $i => $o) {
                $l = isset($o['output_letter']) ? $o['output_letter'] : '';
                $d = isset($o['output_description']) ? $o['output_description'] : '';
                $r[] = array('index' => $i, 'title' => $l ? $l.': '.wp_trim_words($d, 20) : wp_trim_words($d, 20));
            }
        }
        wp_send_json_success($r);
    }

    // =========================================================================
    // AJAX: Save / Delete items
    // =========================================================================

    public function ajax_save_report_items() {
        check_ajax_referer('brm_nonce', 'nonce');
        $pid = intval($_POST['post_id']);
        $dir = sanitize_text_field($_POST['direction']);
        $items = json_decode(stripslashes($_POST['items']), true);
        if (!$this->user_can_access_bimonthly($pid)) wp_send_json_error('Insufficient permissions');

        // Enforce max 3 items per section
        if (is_array($items) && count($items) > 3) {
            wp_send_json_error('Maximum of 3 items per section');
        }

        $clean = array();
        if (is_array($items)) {
            foreach ($items as $item) {
                $post_id = intval($item['post_id']);
                $this->ensure_summary($post_id);

                $outputs = array();
                if (isset($item['outputs']) && is_array($item['outputs'])) {
                    foreach ($item['outputs'] as $o) {
                        $outputs[] = array(
                            'workplan_id' => intval($o['workplan_id']),
                            'goal_id' => intval($o['goal_id']),
                            'objective_id' => intval($o['objective_id']),
                            'output_index' => intval($o['output_index']),
                        );
                    }
                }

                $clean[] = array(
                    'post_id' => $post_id,
                    'post_type' => sanitize_text_field($item['post_type']),
                    'outputs' => $outputs,
                );
            }
        }

        $meta_key = ($dir === 'prior') ? '_brm_prior_items' : '_brm_ahead_items';
        update_post_meta($pid, $meta_key, wp_json_encode($clean));
        wp_send_json_success($this->hydrate_items($clean));
    }

    public function ajax_delete_report_item() {
        check_ajax_referer('brm_nonce', 'nonce');
        $pid = intval($_POST['post_id']);
        $dir = sanitize_text_field($_POST['direction']);
        $idx = intval($_POST['item_index']);
        if (!$this->user_can_access_bimonthly($pid)) wp_send_json_error('Insufficient permissions');

        $meta_key = ($dir === 'prior') ? '_brm_prior_items' : '_brm_ahead_items';
        $items = get_post_meta($pid, $meta_key, true);
        $items = !empty($items) ? json_decode($items, true) : array();
        if (isset($items[$idx])) { array_splice($items, $idx, 1); update_post_meta($pid, $meta_key, wp_json_encode($items)); }
        wp_send_json_success($this->hydrate_items($items));
    }

    // =========================================================================
    // AJAX: Save summary
    // =========================================================================

    public function ajax_save_summary() {
        check_ajax_referer('brm_nonce', 'nonce');
        $pid = intval($_POST['post_id']);
        $summary = sanitize_textarea_field($_POST['summary']);
        $post_type = get_post_type($pid);

        if ($post_type === 'bimonthly-highlight') {
            // Save to highlight_text
            if (function_exists('update_field')) update_field('highlight_text', $summary, $pid);
            else update_post_meta($pid, 'highlight_text', $summary);
        } else {
            if (function_exists('update_field')) update_field('internal_reporting_summary', $summary, $pid);
            else update_post_meta($pid, 'internal_reporting_summary', $summary);
        }
        wp_send_json_success(array('summary' => $summary));
    }

    // =========================================================================
    // AJAX: Settings
    // =========================================================================

    public function ajax_save_settings() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');
        $settings = array(
            'logo_attachment_id' => intval($_POST['logo_attachment_id']),
            'logo_url' => esc_url_raw($_POST['logo_url']),
            'timespan_months' => in_array(intval($_POST['timespan_months']), array(2, 3)) ? intval($_POST['timespan_months']) : 3,
        );
        update_option('brm_pdf_settings', $settings);
        wp_send_json_success($settings);
    }

    /**
     * Save the group-to-role-to-region configuration
     */
    public function ajax_save_group_config() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

        $raw = json_decode(stripslashes($_POST['config']), true);
        $clean = array();
        if (is_array($raw)) {
            foreach ($raw as $term_id => $cfg) {
                $clean[intval($term_id)] = array(
                    'role'   => sanitize_text_field($cfg['role']),
                    'region' => !empty($cfg['region']) ? intval($cfg['region']) : '',
                );
            }
        }
        update_option('brm_group_config', $clean);
        wp_send_json_success();
    }

    /**
     * Apply edit_bimonthly_updates capability based on current group config.
     * Grants to all mapped roles and administrator. Removes from unmapped roles.
     */
    public function ajax_apply_capabilities() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

        $group_config = get_option('brm_group_config', array());

        // Collect all roles that should have the capability
        $mapped_roles = array('administrator');
        foreach ($group_config as $cfg) {
            if (!empty($cfg['role'])) {
                $mapped_roles[] = $cfg['role'];
            }
        }
        $mapped_roles = array_unique($mapped_roles);

        // Grant to mapped roles, remove from everyone else
        $all_roles = wp_roles()->roles;
        foreach (array_keys($all_roles) as $role_slug) {
            $role = get_role($role_slug);
            if (!$role) continue;

            if (in_array($role_slug, $mapped_roles)) {
                $role->add_cap('edit_bimonthly_updates');
            } else {
                if ($role->has_cap('edit_bimonthly_updates')) {
                    $role->remove_cap('edit_bimonthly_updates');
                }
            }
        }

        wp_send_json_success();
    }

    // =========================================================================
    // AJAX: Export PDF (single update)
    // =========================================================================

    public function ajax_export_pdf() {
        check_ajax_referer('brm_nonce', 'nonce');
        $pid = intval($_GET['post_id']);
        if (!$this->user_can_access_bimonthly($pid)) wp_die('Insufficient permissions');
        $post = get_post($pid);
        if (!$post || $post->post_type !== 'bimonthly') wp_die('Update not found');

        $prior = get_post_meta($pid, '_brm_prior_items', true);
        $ahead = get_post_meta($pid, '_brm_ahead_items', true);
        $prior = !empty($prior) ? json_decode($prior, true) : array();
        $ahead = !empty($ahead) ? json_decode($ahead, true) : array();

        $groups = wp_get_post_terms($pid, 'group', array('fields' => 'names'));
        $settings = get_option('brm_pdf_settings', array());
        $logo_path = '';
        if (!empty($settings['logo_attachment_id'])) {
            $a = get_attached_file($settings['logo_attachment_id']);
            if ($a && file_exists($a)) $logo_path = $a;
        }

        $payload = array(
            'title' => $post->post_title, 'network_title' => get_bloginfo('name'),
            'group_name' => !empty($groups) ? $groups[0] : '',
            'is_admin' => current_user_can('edit_others_workplans'),
            'prior_items' => $this->hydrate_items($prior), 'ahead_items' => $this->hydrate_items($ahead),
            'logo_path' => $logo_path,
        );
        $this->run_pdf_generator($payload, sanitize_file_name($post->post_title) . '-bimonthly-update.pdf');
    }

    // =========================================================================
    // AJAX: Meta Report
    // =========================================================================

    public function ajax_get_all_updates_for_meta() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('edit_others_workplans')) wp_send_json_error('Insufficient permissions');
        $posts = get_posts(array('post_type' => 'bimonthly', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC'));
        $r = array();
        foreach ($posts as $p) {
            $g = wp_get_post_terms($p->ID, 'group', array('fields' => 'names'));
            $r[] = array('id' => $p->ID, 'title' => $p->post_title, 'group' => !empty($g) ? $g[0] : '—', 'region' => $this->get_region_for_post($p->ID), 'date' => get_the_date('m/d/Y', $p->ID));
        }
        wp_send_json_success($r);
    }

    public function ajax_export_meta_pdf() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('edit_others_workplans')) wp_die('Insufficient permissions');
        $pids = array_map('intval', explode(',', $_GET['post_ids']));
        if (empty($pids)) wp_die('No updates selected');

        $show_type   = isset($_GET['show_type']) ? intval($_GET['show_type']) : 1;
        $show_author = isset($_GET['show_author']) ? intval($_GET['show_author']) : 1;
        $show_date   = isset($_GET['show_date']) ? intval($_GET['show_date']) : 1;

        usort($pids, function($a, $b) { return $this->get_region_for_post($a) - $this->get_region_for_post($b); });

        $settings = get_option('brm_pdf_settings', array());
        $logo_path = '';
        if (!empty($settings['logo_attachment_id'])) {
            $a = get_attached_file($settings['logo_attachment_id']);
            if ($a && file_exists($a)) $logo_path = $a;
        }

        $updates = array();
        foreach ($pids as $pid) {
            $post = get_post($pid); if (!$post) continue;
            $prior = get_post_meta($pid, '_brm_prior_items', true);
            $ahead = get_post_meta($pid, '_brm_ahead_items', true);
            $prior = !empty($prior) ? json_decode($prior, true) : array();
            $ahead = !empty($ahead) ? json_decode($ahead, true) : array();
            $g = wp_get_post_terms($pid, 'group', array('fields' => 'names'));
            $updates[] = array('id' => $pid, 'title' => $post->post_title, 'group_name' => !empty($g) ? $g[0] : '',
                'region' => $this->get_region_for_post($pid),
                'prior_items' => $this->hydrate_items($prior), 'ahead_items' => $this->hydrate_items($ahead));
        }

        $payload = array(
            'is_meta' => true, 'network_title' => get_bloginfo('name'),
            'logo_path' => $logo_path, 'updates' => $updates,
            'show_type' => $show_type, 'show_author' => $show_author, 'show_date' => $show_date,
        );
        $this->run_pdf_generator($payload, 'Network-Meta-Report-' . date('Y-m-d') . '.pdf');
    }

    /**
     * AJAX: Generate HTML preview for meta report
     */
    public function ajax_get_meta_preview() {
        check_ajax_referer('brm_nonce', 'nonce');
        if (!current_user_can('edit_others_workplans')) wp_send_json_error('Insufficient permissions');

        $pids = array_map('intval', explode(',', $_POST['post_ids']));
        if (empty($pids)) wp_send_json_error('No updates selected');

        $show_type   = intval($_POST['show_type']);
        $show_author = intval($_POST['show_author']);
        $show_date   = intval($_POST['show_date']);

        usort($pids, function($a, $b) { return $this->get_region_for_post($a) - $this->get_region_for_post($b); });

        $updates = array();
        foreach ($pids as $pid) {
            $post = get_post($pid); if (!$post) continue;
            $prior = get_post_meta($pid, '_brm_prior_items', true);
            $ahead = get_post_meta($pid, '_brm_ahead_items', true);
            $prior = !empty($prior) ? json_decode($prior, true) : array();
            $ahead = !empty($ahead) ? json_decode($ahead, true) : array();
            $g = wp_get_post_terms($pid, 'group', array('fields' => 'names'));
            $updates[] = array(
                'group_name' => !empty($g) ? $g[0] : 'Unknown',
                'region' => $this->get_region_for_post($pid),
                'prior_items' => $this->hydrate_items($prior),
                'ahead_items' => $this->hydrate_items($ahead),
            );
        }

        // Build HTML preview
        $html = '<div class="brm-prev-toc"><h3>Table of Contents</h3><ul>';
        foreach ($updates as $i => $u) {
            $rl = $u['region'] < 99 ? ' <span class="region-num">(Region ' . $u['region'] . ')</span>' : '';
            $html .= '<li><a href="#brm-prev-center-' . $i . '">' . esc_html($u['group_name']) . '</a>' . $rl . '</li>';
        }
        $html .= '</ul></div>';

        foreach ($updates as $i => $u) {
            $rl = $u['region'] < 99 ? ' <span class="region-label">(Region ' . $u['region'] . ')</span>' : '';
            $html .= '<div class="brm-prev-center" id="brm-prev-center-' . $i . '">';
            $html .= '<h2>' . esc_html($u['group_name']) . $rl . '</h2>';

            foreach (array('prior_items' => 'Past Highlights', 'ahead_items' => 'Future Highlights') as $key => $label) {
                $items = $u[$key];
                if (empty($items)) continue;

                $html .= '<div class="brm-prev-section"><h3>' . esc_html($label) . '</h3>';
                foreach ($items as $item) {
                    $html .= '<div class="brm-prev-item">';
                    $html .= '<div class="brm-prev-item-title">' . esc_html($item['post_title']) . '</div>';

                    $meta = array();
                    if ($show_type && !empty($item['post_type_label'])) $meta[] = $item['post_type_label'];
                    if ($show_author && !empty($item['author'])) $meta[] = 'by ' . $item['author'];
                    if ($show_date && !empty($item['date'])) $meta[] = $item['date'];
                    if (!empty($meta)) {
                        $html .= '<div class="brm-prev-item-meta">' . esc_html(implode(' · ', $meta)) . '</div>';
                    }

                    if (!empty($item['summary'])) {
                        $html .= '<div class="brm-prev-item-summary">' . esc_html($item['summary']) . '</div>';
                    }

                    if (!empty($item['outputs'])) {
                        $parts = array();
                        foreach ($item['outputs'] as $o) {
                            if (!empty($o['output_desc'])) {
                                $path = '';
                                if (!empty($o['goal_letter'])) $path .= $o['goal_letter'] . '.';
                                if (!empty($o['objective_number'])) $path .= $o['objective_number'] . '.';
                                if (!empty($o['output_letter'])) $path .= $o['output_letter'] . '.';
                                $parts[] = ($path ? $path . ' ' : '') . $o['output_desc'];
                            }
                        }
                        if (!empty($parts)) {
                            $lbl = count($parts) === 1 ? 'Associated Workplan Output:' : 'Associated Workplan Outputs:';
                            $html .= '<div class="brm-prev-item-outputs"><strong>' . $lbl . '</strong><br>' . esc_html(implode('; ', $parts)) . '</div>';
                        }
                    }

                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Run the Python PDF generator and stream the result
     */
    private function run_pdf_generator($payload, $filename) {
        $json_file = tempnam(sys_get_temp_dir(), 'brm_') . '.json';
        $pdf_file = tempnam(sys_get_temp_dir(), 'brm_') . '.pdf';
        file_put_contents($json_file, wp_json_encode($payload));

        // Auto-detect Python path
        $python = '/usr/bin/python3';
        if (!file_exists($python)) {
            $python = trim(shell_exec('which python3 2>/dev/null'));
        }
        if (empty($python)) {
            $python = 'python3';
        }

        $cmd = sprintf('%s %s %s %s 2>&1', escapeshellarg($python), escapeshellarg(BRM_PLUGIN_PATH . 'includes/generate-pdf.py'), escapeshellarg($json_file), escapeshellarg($pdf_file));
        $output = shell_exec($cmd);
        @unlink($json_file);
        if (!file_exists($pdf_file)) wp_die('PDF generation failed: ' . esc_html($output));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdf_file));
        readfile($pdf_file);
        @unlink($pdf_file);
        exit;
    }

    // =========================================================================
    // Shortcode: [bimonthly_report]
    // =========================================================================

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'bimonthly_report');
        $pid = intval($atts['id']);
        if (!$pid) $pid = get_the_ID();
        if (!$pid || get_post_type($pid) !== 'bimonthly') return '<p>No bimonthly update found.</p>';

        $prior = get_post_meta($pid, '_brm_prior_items', true);
        $ahead = get_post_meta($pid, '_brm_ahead_items', true);
        $prior = !empty($prior) ? json_decode($prior, true) : array();
        $ahead = !empty($ahead) ? json_decode($ahead, true) : array();
        ob_start();
        $this->render_table('Past Highlights', $this->hydrate_items($prior));
        $this->render_table('Future Highlights', $this->hydrate_items($ahead));
        return ob_get_clean();
    }

    private function render_table($label, $items) {
        if (empty($items)) return;
        ?>
        <div class="brm-section">
            <h3 class="brm-section-title"><?php echo esc_html($label); ?></h3>
            <table class="brm-table">
                <thead><tr><th>Type</th><th>Title</th><th>Author</th><th>Date</th><th>Summary</th><th>Associated Workplan Outputs</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item['post_type_label']); ?></td>
                        <td><a href="<?php echo esc_url($item['permalink']); ?>"><?php echo esc_html($item['post_title']); ?></a></td>
                        <td><?php echo esc_html($item['author']); ?></td>
                        <td><?php echo esc_html($item['date']); ?></td>
                        <td><?php echo esc_html($item['summary'] ?: '—'); ?></td>
                        <td><?php
                            if (!empty($item['outputs'])) {
                                $parts = array();
                                foreach ($item['outputs'] as $o) {
                                    if (!empty($o['output_desc'])) {
                                        $path = '';
                                        if (!empty($o['goal_letter'])) $path .= $o['goal_letter'] . '.';
                                        if (!empty($o['objective_number'])) $path .= $o['objective_number'] . '.';
                                        if (!empty($o['output_letter'])) $path .= $o['output_letter'] . '.';
                                        $parts[] = ($path ? $path . ' ' : '') . $o['output_desc'];
                                    }
                                }
                                echo esc_html(implode('; ', $parts) ?: '—');
                            } else { echo '—'; }
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

new BimonthlyReportManager();
