<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap" id="brm-app">
    <h1>Bimonthly Update Manager</h1>

    <div id="brm-container">
        <div id="brm-sidebar">
            <div class="brm-sidebar-header">
                <h2>Updates</h2>
                <button id="brm-new-report" class="button button-primary">+ New Update</button>
            </div>
            <div id="brm-report-list" class="brm-list"><p class="brm-loading">Loading updates…</p></div>
        </div>

        <div id="brm-editor">
            <div id="brm-no-selection"><p>Select a bimonthly update from the list, or create a new one.</p></div>

            <div id="brm-editor-content" style="display:none;">
                <div class="brm-editor-header">
                    <h2 id="brm-report-title"></h2>
                    <span id="brm-report-group" class="brm-badge"></span>
                    <div class="brm-editor-actions">
                        <button id="brm-export-pdf" class="button button-secondary">Export PDF</button>
                        <button id="brm-delete-report" class="button button-link-delete">Delete Update</button>
                    </div>
                </div>

                <div class="brm-help-text"><p>Items are saved automatically as you add them. You can close this page at any time and return later to continue editing.</p></div>

                <?php foreach (array('prior' => 'Past Highlights', 'ahead' => 'Future Highlights') as $dir => $label) : ?>
                <div class="brm-section-editor" data-direction="<?php echo $dir; ?>">
                    <h3><?php echo $label; ?></h3>
                    <table class="widefat brm-items-table">
                        <thead><tr><th>Type</th><th>Title</th><th>Author</th><th>Date</th><th>Summary</th><th>Associated Workplan Outputs</th><th width="40"></th></tr></thead>
                        <tbody id="brm-<?php echo $dir; ?>-items"><tr class="brm-empty-row"><td colspan="7">No items yet.</td></tr></tbody>
                    </table>
                    <button class="button brm-add-item" data-direction="<?php echo $dir; ?>">+ Add Item</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal: Add Item (two-step) -->
    <div id="brm-modal-overlay" style="display:none;">
        <div id="brm-modal">
            <div class="brm-modal-header">
                <h3>Add Update Item</h3>
                <button id="brm-modal-close" class="brm-close">&times;</button>
            </div>
            <div class="brm-modal-body">
                <!-- Step 1: Select post -->
                <div class="brm-step" id="brm-step-post">
                    <h4>Step 1: Select a Post</h4>
                    <div class="brm-field-row">
                        <label for="brm-post-type">Post Type</label>
                        <select id="brm-post-type">
                            <option value="">— Select type —</option>
                            <option value="news">News</option>
                            <option value="event">Event</option>
                            <option value="products_and_resourc">Product / Resource</option>
                            <option value="bimonthly-highlight">Bi-Monthly Highlight</option>
                        </select>
                    </div>
                    <div class="brm-field-row">
                        <label for="brm-post-select">Post</label>
                        <select id="brm-post-select" disabled><option value="">— Select a post type first —</option></select>
                    </div>

                    <div class="brm-field-row" id="brm-add-highlight-wrap" style="display:none;">
                        <button type="button" id="brm-add-highlight-btn" class="button">+ Add Bi-Monthly Highlight</button>
                    </div>

                    <div id="brm-new-highlight-region" class="brm-staged-list" style="display:none;">
                        <h4>New Bi-Monthly Highlight</h4>
                        <div class="brm-field-row">
                            <label for="brm-highlight-title">Post Title</label>
                            <input type="text" id="brm-highlight-title">
                        </div>
                        <div class="brm-field-row">
                            <label for="brm-highlight-date">Highlight Date</label>
                            <input type="date" id="brm-highlight-date">
                        </div>
                        <div class="brm-field-row">
                            <label for="brm-highlight-text">Highlight Text</label>
                            <textarea id="brm-highlight-text" rows="4"></textarea>
                        </div>
                        <button type="button" id="brm-highlight-save" class="button button-primary">Save Highlight</button>
                        <button type="button" id="brm-highlight-cancel" class="button">Cancel</button>
                    </div>
                </div>

                <!-- Step 2: Workplan outputs (can add multiple) -->
                <div class="brm-step" id="brm-step-workplan">
                    <h4>Step 2: Associate Workplan Outputs</h4>
                    <p class="brm-step-help">Drill down to an output, then click "Add Output" to associate it. You can add multiple outputs per post.</p>
                    <p class="brm-step-help"><em>This step is optional. You can save your Bi-Monthly Update without attaching workplans.</em></p>

                    <div class="brm-field-row">
                        <label for="brm-workplan">Work Plan</label>
                        <select id="brm-workplan" disabled><option value="">— Select a post first —</option></select>
                    </div>
                    <div class="brm-field-row">
                        <label for="brm-goal">Goal</label>
                        <select id="brm-goal" disabled><option value="">— Select a work plan first —</option></select>
                    </div>
                    <div class="brm-field-row">
                        <label for="brm-objective">Objective</label>
                        <select id="brm-objective" disabled><option value="">— Select a goal first —</option></select>
                    </div>
                    <div class="brm-field-row">
                        <label for="brm-output">Output</label>
                        <select id="brm-output" disabled><option value="">— Select an objective first —</option></select>
                    </div>

                    <button type="button" id="brm-add-output-btn" class="button" disabled>+ Add Output</button>

                    <div id="brm-staged-outputs" class="brm-staged-list" style="display:none;">
                        <h4>Selected Outputs:</h4>
                        <ul id="brm-staged-outputs-list"></ul>
                    </div>
                </div>
            </div>
            <div class="brm-modal-footer">
                <button id="brm-modal-save" class="button button-primary" disabled>Save & Close</button>
                <button id="brm-modal-cancel" class="button">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Modal: New Update -->
    <div id="brm-new-modal-overlay" style="display:none;">
        <div id="brm-new-modal">
            <div class="brm-modal-header">
                <h3>New Bimonthly Update</h3>
                <button id="brm-new-modal-close" class="brm-close">&times;</button>
            </div>
            <div class="brm-modal-body">
                <div class="brm-field-row">
                    <label for="brm-new-title">Update Title</label>
                    <input type="text" id="brm-new-title" placeholder="e.g. March–April 2026 Update">
                </div>
                <div class="brm-field-row">
                    <label for="brm-new-group">Group</label>
                    <select id="brm-new-group">
                        <option value="">— Select group —</option>
                        <?php
                        $is_admin = current_user_can('edit_others_workplans');

                        if ($is_admin) {
                            // Admins see all groups
                            $groups = get_terms(array('taxonomy' => 'group', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
                            if (!is_wp_error($groups)) {
                                foreach ($groups as $g) printf('<option value="%d">%s</option>', $g->term_id, esc_html($g->name));
                            }
                        } else {
                            // Center editors see only their assigned group(s) from config
                            $group_config = get_option('brm_group_config', array());
                            $user = wp_get_current_user();
                            // Build reverse: role => term_ids
                            foreach ($group_config as $term_id => $cfg) {
                                if (!empty($cfg['role']) && in_array($cfg['role'], $user->roles)) {
                                    $term = get_term(intval($term_id), 'group');
                                    if ($term && !is_wp_error($term)) {
                                        printf('<option value="%d" selected>%s</option>', $term->term_id, esc_html($term->name));
                                    }
                                }
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="brm-modal-footer">
                <button id="brm-new-save" class="button button-primary">Create Update</button>
                <button id="brm-new-cancel" class="button">Cancel</button>
            </div>
        </div>
    </div>
</div>
