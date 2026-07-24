<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap" id="brm-meta-report-page">
    <h1>Meta Report Generator</h1>
    <p>Select bimonthly updates to combine into a single network-wide PDF report. Updates will be ordered by Region number.</p>

    <div class="brm-meta-container">
        <div class="brm-meta-selection">
            <h2>Available Updates</h2>

            <!-- Date range filter -->
            <div class="brm-meta-filters">
                <label>Filter by date range:</label>
                <div class="brm-date-range">
                    <input type="date" id="brm-meta-date-from" placeholder="From">
                    <span>to</span>
                    <input type="date" id="brm-meta-date-to" placeholder="To">
                    <button type="button" id="brm-meta-clear-dates" class="button button-small">Clear</button>
                </div>
            </div>

            <div id="brm-meta-list">
                <p class="brm-loading">Loading updates…</p>
            </div>
        </div>

        <div class="brm-meta-actions">
            <p><strong id="brm-meta-count">0</strong> updates selected</p>

            <!-- Display toggles -->
            <div class="brm-display-options">
                <h4>Display Options</h4>
                <label><input type="checkbox" id="brm-show-type" checked> Post Type</label>
                <label><input type="checkbox" id="brm-show-author" checked> Author</label>
                <label><input type="checkbox" id="brm-show-date" checked> Date</label>
            </div>

            <button type="button" id="brm-meta-preview" class="button button-secondary button-hero" disabled>Preview Report</button>
            <button type="button" id="brm-meta-export" class="button button-primary button-hero" disabled>Generate PDF</button>
            <p class="description">The PDF will include a cover page, table of contents, and each center's update ordered by Region.</p>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="brm-preview-overlay" style="display:none;">
    <div id="brm-preview-modal">
        <div class="brm-preview-header">
            <h3>Meta Report Preview</h3>
            <div class="brm-preview-actions">
                <button type="button" id="brm-preview-export" class="button button-primary">Generate PDF</button>
                <button type="button" id="brm-preview-close" class="button">Close</button>
            </div>
        </div>
        <div id="brm-preview-content" class="brm-preview-body">
            <p class="brm-loading">Loading preview…</p>
        </div>
    </div>
</div>

<style>
.brm-meta-container {
    display: flex;
    gap: 30px;
    margin-top: 15px;
}

.brm-meta-selection {
    flex: 1;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px 20px;
    max-width: 700px;
}

.brm-meta-selection h2 {
    margin-top: 0;
    font-size: 15px;
}

.brm-meta-filters {
    margin-bottom: 12px;
    padding: 10px 12px;
    background: #f6f7f7;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.brm-meta-filters > label {
    display: block;
    font-weight: 600;
    font-size: 12px;
    margin-bottom: 6px;
    color: #555;
}

.brm-date-range {
    display: flex;
    align-items: center;
    gap: 8px;
}

.brm-date-range input[type="date"] {
    width: 150px;
}

.brm-date-range span {
    color: #888;
    font-size: 12px;
}

.brm-meta-actions {
    width: 260px;
    padding-top: 10px;
}

.brm-meta-actions p {
    margin-bottom: 12px;
}

.brm-display-options {
    margin-bottom: 16px;
    padding: 10px 12px;
    background: #f6f7f7;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.brm-display-options h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    text-transform: uppercase;
    color: #555;
}

.brm-display-options label {
    display: block;
    font-size: 13px;
    margin-bottom: 4px;
    cursor: pointer;
}

.brm-meta-actions .button-hero {
    width: 100%;
    text-align: center;
    margin-bottom: 8px;
}

.brm-meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-bottom: 1px solid #f0f0f0;
}

.brm-meta-item:hover {
    background: #f6f7f7;
}

.brm-meta-item label {
    flex: 1;
    cursor: pointer;
    font-size: 13px;
}

.brm-meta-item .brm-meta-region {
    font-size: 11px;
    color: #888;
    min-width: 60px;
    text-align: right;
}

.brm-meta-item .brm-meta-group {
    font-size: 12px;
    color: #555;
}

.brm-meta-item .brm-meta-date {
    font-size: 11px;
    color: #999;
}

.brm-meta-item.brm-hidden {
    display: none;
}

.brm-loading {
    text-align: center;
    color: #888;
    font-style: italic;
    padding: 20px;
}

#brm-meta-count {
    font-size: 24px;
    color: #2271b1;
}

/* Preview modal */
#brm-preview-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

#brm-preview-modal {
    background: #fff;
    border-radius: 8px;
    width: 90vw;
    max-width: 900px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}

.brm-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.brm-preview-header h3 {
    margin: 0;
    font-size: 16px;
}

.brm-preview-actions {
    display: flex;
    gap: 8px;
}

.brm-preview-body {
    padding: 20px 30px;
    overflow-y: auto;
    flex: 1;
}

/* Preview content styles */
.brm-preview-body .brm-prev-center {
    margin-bottom: 30px;
    page-break-inside: avoid;
}

.brm-prev-center h2 {
    font-size: 18px;
    color: #1a3a5c;
    border-bottom: 2px solid #1a3a5c;
    padding-bottom: 6px;
    margin-bottom: 12px;
}

.brm-prev-center h2 .region-label {
    font-size: 12px;
    color: #7f8c8d;
    font-weight: normal;
}

.brm-prev-section h3 {
    font-size: 14px;
    color: #555;
    margin: 16px 0 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.brm-prev-item {
    margin-bottom: 12px;
    padding: 8px 12px;
    background: #fafbfc;
    border-left: 3px solid #72aee6;
    border-radius: 2px;
}

.brm-prev-item-title {
    font-weight: 600;
    font-size: 14px;
    color: #1d2327;
}

.brm-prev-item-meta {
    font-size: 12px;
    color: #888;
    margin-top: 2px;
}

.brm-prev-item-summary {
    font-size: 13px;
    color: #444;
    margin-top: 4px;
    font-style: italic;
}

.brm-prev-item-outputs {
    font-size: 12px;
    color: #1a3a5c;
    margin-top: 4px;
}

.brm-prev-item-outputs strong {
    font-size: 11px;
    text-transform: uppercase;
    color: #666;
}

.brm-prev-toc {
    margin-bottom: 24px;
    padding: 12px 16px;
    background: #f0f6fc;
    border-radius: 4px;
}

.brm-prev-toc h3 {
    margin: 0 0 8px 0;
    font-size: 15px;
    color: #1a3a5c;
}

.brm-prev-toc ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.brm-prev-toc li {
    padding: 3px 0;
    font-size: 13px;
}

.brm-prev-toc a {
    text-decoration: none;
    color: #2271b1;
}

.brm-prev-toc a:hover {
    text-decoration: underline;
}

.brm-prev-toc .region-num {
    color: #888;
    font-size: 11px;
}
</style>

<script>
(function($) {
    var nonce = '<?php echo wp_create_nonce("brm_nonce"); ?>';
    var allUpdates = [];

    function loadUpdates() {
        $.post(ajaxurl, {
            action: 'brm_get_all_updates_for_meta',
            nonce: nonce
        }, function(res) {
            if (!res.success) return;
            allUpdates = res.data;
            allUpdates.sort(function(a, b) { return a.region - b.region; });
            renderUpdateList();
        });
    }

    function renderUpdateList() {
        var $list = $('#brm-meta-list').empty();
        var fromDate = $('#brm-meta-date-from').val();
        var toDate = $('#brm-meta-date-to').val();

        if (allUpdates.length === 0) {
            $list.html('<p>No updates found.</p>');
            return;
        }

        allUpdates.forEach(function(u) {
            // Parse the date for filtering (stored as m/d/Y)
            var parts = u.date.split('/');
            var itemDate = parts.length === 3 ? parts[2] + '-' + parts[0].padStart(2, '0') + '-' + parts[1].padStart(2, '0') : '';

            var hidden = false;
            if (fromDate && itemDate < fromDate) hidden = true;
            if (toDate && itemDate > toDate) hidden = true;

            var regionLabel = u.region < 99 ? 'Region ' + u.region : 'Unknown';
            var $item = $(
                '<div class="brm-meta-item' + (hidden ? ' brm-hidden' : '') + '" data-date="' + esc(itemDate) + '">' +
                    '<input type="checkbox" class="brm-meta-check" value="' + u.id + '" id="brm-meta-' + u.id + '"' + (hidden ? ' disabled' : '') + '>' +
                    '<label for="brm-meta-' + u.id + '">' +
                        '<span class="brm-meta-group">' + esc(u.group) + '</span> — ' +
                        '<span class="brm-meta-date">' + esc(u.title) + ' (' + esc(u.date) + ')</span>' +
                    '</label>' +
                    '<span class="brm-meta-region">' + esc(regionLabel) + '</span>' +
                '</div>'
            );
            $list.append($item);
        });

        updateCount();
    }

    function updateCount() {
        var count = $('.brm-meta-check:checked').length;
        $('#brm-meta-count').text(count);
        $('#brm-meta-export, #brm-meta-preview').prop('disabled', count === 0);
    }

    function getSelectedIds() {
        var ids = [];
        $('.brm-meta-check:checked').each(function() { ids.push($(this).val()); });
        return ids;
    }

    function getDisplayOptions() {
        return {
            show_type: $('#brm-show-type').is(':checked'),
            show_author: $('#brm-show-author').is(':checked'),
            show_date: $('#brm-show-date').is(':checked')
        };
    }

    function buildExportUrl() {
        var ids = getSelectedIds();
        var opts = getDisplayOptions();
        return ajaxurl +
            '?action=brm_export_meta_pdf' +
            '&post_ids=' + ids.join(',') +
            '&show_type=' + (opts.show_type ? '1' : '0') +
            '&show_author=' + (opts.show_author ? '1' : '0') +
            '&show_date=' + (opts.show_date ? '1' : '0') +
            '&nonce=' + nonce;
    }

    // Date filter change
    $('#brm-meta-date-from, #brm-meta-date-to').on('change', function() {
        renderUpdateList();
    });

    $('#brm-meta-clear-dates').on('click', function() {
        $('#brm-meta-date-from').val('');
        $('#brm-meta-date-to').val('');
        renderUpdateList();
    });

    // Checkbox change
    $(document).on('change', '.brm-meta-check', function() {
        updateCount();
    });

    // Export PDF
    $('#brm-meta-export').on('click', function() {
        if (getSelectedIds().length === 0) return;
        window.open(buildExportUrl(), '_blank');
    });

    // Preview
    $('#brm-meta-preview').on('click', function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;
        var opts = getDisplayOptions();

        $('#brm-preview-content').html('<p class="brm-loading">Loading preview…</p>');
        $('#brm-preview-overlay').show();

        $.post(ajaxurl, {
            action: 'brm_get_meta_preview',
            nonce: nonce,
            post_ids: ids.join(','),
            show_type: opts.show_type ? '1' : '0',
            show_author: opts.show_author ? '1' : '0',
            show_date: opts.show_date ? '1' : '0'
        }, function(res) {
            if (res.success) {
                $('#brm-preview-content').html(res.data.html);
            } else {
                $('#brm-preview-content').html('<p>Error loading preview.</p>');
            }
        });
    });

    // Preview export button
    $('#brm-preview-export').on('click', function() {
        window.open(buildExportUrl(), '_blank');
    });

    // Close preview
    $('#brm-preview-close').on('click', function() {
        $('#brm-preview-overlay').hide();
    });

    function esc(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    loadUpdates();
})(jQuery);
</script>
