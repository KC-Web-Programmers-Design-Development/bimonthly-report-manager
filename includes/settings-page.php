<?php
if (!defined('ABSPATH')) exit;

$settings = get_option('brm_pdf_settings', array());
$logo_id  = isset($settings['logo_attachment_id']) ? intval($settings['logo_attachment_id']) : 0;
$logo_url = isset($settings['logo_url']) ? $settings['logo_url'] : '';
$timespan = isset($settings['timespan_months']) ? intval($settings['timespan_months']) : 3;
$network_title = get_bloginfo('name');

// Get current group config
$group_config = get_option('brm_group_config', array());

// Get all group taxonomy terms
$groups = get_terms(array('taxonomy' => 'group', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
if (is_wp_error($groups)) $groups = array();

// Get all non-admin roles
$all_roles = wp_roles()->roles;
$editor_roles = array();
foreach ($all_roles as $slug => $role_data) {
    if ($slug === 'administrator') continue;
    $editor_roles[$slug] = $role_data['name'];
}
asort($editor_roles);
?>
<div class="wrap" id="brm-settings-page">
    <h1>Bimonthly Update Settings</h1>

    <!-- ============================================ -->
    <!-- Group & Role Configuration                   -->
    <!-- ============================================ -->
    <h2 class="title">Group &amp; Role Configuration</h2>
    <p class="description">Map each group to an editor role and assign a region number for report ordering. Only roles with a group assignment will have access to the Bimonthly Updates page.</p>

    <table class="widefat brm-group-config-table" id="brm-group-config-table">
        <thead>
            <tr>
                <th>Group</th>
                <th>Assigned Editor Role</th>
                <th>Region #</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($groups as $g) :
                $cfg = isset($group_config[$g->term_id]) ? $group_config[$g->term_id] : array();
                $assigned_role = isset($cfg['role']) ? $cfg['role'] : '';
                $region_num = isset($cfg['region']) ? intval($cfg['region']) : '';
            ?>
            <tr data-term-id="<?php echo esc_attr($g->term_id); ?>">
                <td><strong><?php echo esc_html($g->name); ?></strong><br><code><?php echo esc_html($g->slug); ?></code></td>
                <td>
                    <select class="brm-cfg-role">
                        <option value="">— None —</option>
                        <?php foreach ($editor_roles as $rs => $rn) : ?>
                            <option value="<?php echo esc_attr($rs); ?>" <?php selected($assigned_role, $rs); ?>><?php echo esc_html($rn); ?> (<?php echo esc_html($rs); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" class="brm-cfg-region small-text" value="<?php echo esc_attr($region_num); ?>" min="1" max="99" placeholder="—">
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($groups)) : ?>
            <tr><td colspan="3">No group taxonomy terms found. Create groups first.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="submit">
        <button type="button" id="brm-save-group-config" class="button button-primary">Save Group Configuration</button>
        <button type="button" id="brm-apply-caps" class="button">Apply Capabilities to Roles</button>
        <span id="brm-config-saved" class="brm-save-notice" style="display:none;">Saved.</span>
        <span id="brm-caps-applied" class="brm-save-notice" style="display:none;">Capabilities applied.</span>
    </p>
    <p class="description">After saving, click "Apply Capabilities to Roles" to grant the <code>edit_bimonthly_updates</code> capability to mapped roles (and remove it from unmapped ones).</p>

    <hr>

    <!-- ============================================ -->
    <!-- General Settings                              -->
    <!-- ============================================ -->
    <h2 class="title">General</h2>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="brm-timespan">Date Range</label></th>
            <td>
                <select id="brm-timespan">
                    <option value="2" <?php selected($timespan, 2); ?>>2 months</option>
                    <option value="3" <?php selected($timespan, 3); ?>>3 months</option>
                </select>
                <p class="description">How far back (prior) and ahead (future) to look when filtering posts for selection.</p>
            </td>
        </tr>
    </table>

    <hr>

    <!-- ============================================ -->
    <!-- PDF Cover Page                                -->
    <!-- ============================================ -->
    <h2 class="title">PDF Cover Page</h2>
    <table class="form-table">
        <tr>
            <th scope="row"><label>Logo</label></th>
            <td>
                <div id="brm-logo-preview" class="brm-logo-preview" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>>
                    <img id="brm-logo-img" src="<?php echo esc_url($logo_url); ?>" alt="Logo preview" style="max-width:200px; max-height:100px;">
                </div>
                <p style="margin-top:8px;">
                    <button type="button" id="brm-upload-logo" class="button">Select Logo</button>
                    <button type="button" id="brm-remove-logo" class="button button-link-delete" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>>Remove Logo</button>
                </p>
                <input type="hidden" id="brm-logo-attachment-id" value="<?php echo esc_attr($logo_id); ?>">
                <input type="hidden" id="brm-logo-url" value="<?php echo esc_url($logo_url); ?>">
                <p class="description">Upload or select a logo image for the PDF cover page.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label>Network Title</label></th>
            <td>
                <input type="text" id="brm-network-title" class="regular-text" value="<?php echo esc_attr($network_title); ?>" disabled>
                <p class="description">Pulled from your site title (Settings → General).</p>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="button" id="brm-save-settings" class="button button-primary">Save PDF Settings</button>
        <span id="brm-settings-saved" class="brm-save-notice" style="display:none;">Settings saved.</span>
    </p>
</div>

<script>
(function($) {
    var nonce = '<?php echo wp_create_nonce("brm_nonce"); ?>';

    // ============================
    // Save group configuration
    // ============================
    $('#brm-save-group-config').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving…');

        var config = {};
        $('#brm-group-config-table tbody tr').each(function() {
            var termId = $(this).data('term-id');
            if (!termId) return;
            var role = $(this).find('.brm-cfg-role').val();
            var region = $(this).find('.brm-cfg-region').val();
            config[termId] = { role: role, region: region ? parseInt(region) : '' };
        });

        $.post(ajaxurl, {
            action: 'brm_save_group_config',
            nonce: nonce,
            config: JSON.stringify(config)
        }, function(res) {
            $btn.prop('disabled', false).text('Save Group Configuration');
            if (res.success) {
                $('#brm-config-saved').show().delay(2000).fadeOut();
            } else {
                alert('Error: ' + (res.data || 'Unknown error'));
            }
        });
    });

    // ============================
    // Apply capabilities
    // ============================
    $('#brm-apply-caps').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Applying…');

        $.post(ajaxurl, {
            action: 'brm_apply_capabilities',
            nonce: nonce
        }, function(res) {
            $btn.prop('disabled', false).text('Apply Capabilities to Roles');
            if (res.success) {
                $('#brm-caps-applied').show().delay(2000).fadeOut();
            } else {
                alert('Error: ' + (res.data || 'Unknown error'));
            }
        });
    });

    // ============================
    // Media uploader
    // ============================
    $('#brm-upload-logo').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'Select Logo',
            button: { text: 'Use as Logo' },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#brm-logo-attachment-id').val(attachment.id);
            $('#brm-logo-url').val(attachment.url);
            $('#brm-logo-img').attr('src', attachment.url);
            $('#brm-logo-preview').show();
            $('#brm-remove-logo').show();
        });
        frame.open();
    });

    $('#brm-remove-logo').on('click', function(e) {
        e.preventDefault();
        $('#brm-logo-attachment-id').val(0);
        $('#brm-logo-url').val('');
        $('#brm-logo-preview').hide();
        $(this).hide();
    });

    // ============================
    // Save PDF settings
    // ============================
    $('#brm-save-settings').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving…');

        $.post(ajaxurl, {
            action: 'brm_save_settings',
            nonce: nonce,
            logo_attachment_id: $('#brm-logo-attachment-id').val(),
            logo_url: $('#brm-logo-url').val(),
            timespan_months: $('#brm-timespan').val()
        }, function(res) {
            $btn.prop('disabled', false).text('Save PDF Settings');
            if (res.success) {
                $('#brm-settings-saved').show().delay(2000).fadeOut();
            } else {
                alert('Error saving settings: ' + (res.data || 'Unknown error'));
            }
        });
    });
})(jQuery);
</script>

<style>
.brm-logo-preview {
    margin-bottom: 4px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    display: inline-block;
}
.brm-save-notice {
    color: #00a32a;
    font-weight: 600;
    margin-left: 10px;
    vertical-align: middle;
}
.brm-group-config-table {
    max-width: 800px;
}
.brm-group-config-table td {
    vertical-align: middle;
}
.brm-group-config-table code {
    font-size: 11px;
    color: #888;
}
.brm-group-config-table select {
    min-width: 250px;
}
</style>
