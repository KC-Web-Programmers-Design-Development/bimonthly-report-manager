(function($) {
    'use strict';

    var BRM = {
        currentReportId: 0,
        currentDirection: '',
        editingIndex: -1, // -1 = adding new, >= 0 = editing existing
        priorItems: [],
        aheadItems: [],
        outputTopics: {}, // topics per output index for the selected objective
        outputTopicValues: {}, // topic slugs per output index for the selected objective
        stagedOutputs: [], // outputs staged in the modal before saving

        init: function() {
            this.bindEvents();
            this.loadReportList();
        },

        bindEvents: function() {
            var self = this;

            $(document).on('click', '.brm-report-item', function() { self.loadReport($(this).data('id')); });

            $('#brm-new-report').on('click', function() { self.showNewModal(); });
            $('#brm-new-modal-close, #brm-new-cancel').on('click', function() { self.hideNewModal(); });
            $('#brm-new-save').on('click', function() { self.createReport(); });
            $('#brm-delete-report').on('click', function() { self.deleteReport(); });
            $('#brm-export-pdf').on('click', function() { self.exportPdf(); });

            // Period editing
            $('#brm-edit-period').on('click', function() { self.showPeriodEditor(); });
            $('#brm-cancel-period').on('click', function() { self.hidePeriodEditor(); });
            $('#brm-save-period').on('click', function() { self.savePeriod(); });

            $(document).on('click', '.brm-add-item', function() {
                self.currentDirection = $(this).data('direction');
                var currentItems = (self.currentDirection === 'prior') ? self.priorItems : self.aheadItems;
                if (currentItems.length >= 3) {
                    alert('You can add a maximum of 3 items per section.');
                    return;
                }
                self.showItemModal();
            });

            $('#brm-modal-close, #brm-modal-cancel').on('click', function() { self.hideItemModal(); });

            $('#brm-post-type').on('change', function() { self.onPostTypeChange(); });
            $('#brm-post-select').on('change', function() { self.onPostSelected(); });

            $('#brm-add-highlight-btn').on('click', function() { self.showNewHighlightRegion(); });
            $('#brm-highlight-cancel').on('click', function() { self.hideNewHighlightRegion(); });
            $('#brm-highlight-save').on('click', function() { self.createHighlight(); });
            $('#brm-workplan').on('change', function() { self.onWorkplanChange(); });
            $('#brm-goal').on('change', function() { self.onGoalChange(); });
            $('#brm-objective').on('change', function() { self.onObjectiveChange(); });
            $('#brm-output').on('change', function() { self.onOutputChange(); });

            // Add output to staged list
            $('#brm-add-output-btn').on('click', function() { self.stageOutput(); });
            $(document).on('change', '.brm-topic-editor input[type=checkbox]', function() {
                self.saveOutputTopics($(this).closest('.brm-topic-editor'), $(this));
            });

            // Remove staged output
            $(document).on('click', '.brm-remove-staged', function() {
                var idx = $(this).data('index');
                self.stagedOutputs.splice(idx, 1);
                self.renderStagedOutputs();
            });

            // Save item (with all staged outputs)
            $('#brm-modal-save').on('click', function() { self.saveItem(); });

            $(document).on('click', '.brm-delete-item', function() {
                self.deleteItem($(this).data('direction'), $(this).data('index'));
            });

            // Edit item
            $(document).on('click', '.brm-edit-item', function() {
                self.currentDirection = $(this).data('direction');
                self.editItem($(this).data('index'));
            });

            $(document).on('blur', '.brm-summary-textarea', function() { self.saveSummary($(this)); });
            $(document).on('click', '.brm-edit-title', function() { self.startTitleEdit($(this).closest('td')); });
            $(document).on('keydown', '.brm-title-input', function(e) {
                if (e.which === 13) { e.preventDefault(); $(this).data('commit', true).blur(); }
                else if (e.which === 27) { e.preventDefault(); $(this).data('cancel', true).blur(); }
            });
            $(document).on('blur', '.brm-title-input', function() { self.finishTitleEdit($(this)); });
        },

        // =====================================================================
        // Report list
        // =====================================================================

        loadReportList: function() {
            var self = this;
            $.post(brm_ajax.ajax_url, { action: 'brm_get_bimonthly_list', nonce: brm_ajax.nonce }, function(res) {
                if (res.success) self.renderReportList(res.data);
            });
        },

        renderReportList: function(reports) {
            var $list = $('#brm-report-list').empty();
            if (reports.length === 0) { $list.html('<p class="brm-empty">No updates found.</p>'); return; }
            reports.forEach(function(r) {
                var meta = BRM.esc(r.group) + ' · ' + BRM.esc(r.date);
                if (r.period) meta = BRM.esc(r.period) + ' · ' + BRM.esc(r.group);
                $list.append('<div class="brm-report-item' + (r.id === BRM.currentReportId ? ' active' : '') + '" data-id="' + r.id + '"><strong>' + BRM.esc(r.title) + '</strong><span class="brm-meta">' + meta + '</span></div>');
            });
        },

        // =====================================================================
        // Load report
        // =====================================================================

        loadReport: function(pid) {
            var self = this;
            this.currentReportId = pid;
            $('.brm-report-item').removeClass('active');
            $('.brm-report-item[data-id="' + pid + '"]').addClass('active');
            $('#brm-no-selection').hide();
            $('#brm-editor-content').show();

            $.post(brm_ajax.ajax_url, { action: 'brm_get_report_data', nonce: brm_ajax.nonce, post_id: pid }, function(res) {
                if (res.success) self.renderReport(res.data);
                else alert('Error loading update: ' + (res.data || ''));
            });
        },

        renderReport: function(data) {
            $('#brm-report-title').text(data.title);
            $('#brm-report-group').text(data.groups.map(function(g) { return g.name; }).join(', ') || 'No group');

            // Display period info
            this.hidePeriodEditor(); // reset editor state
			this.currentPeriod = data.period || null;
            if (data.period) {
                $('#brm-report-period').text(data.period.span_label).show();
                $('#brm-range-prior').text('(' + data.period.prior_label + ')');
                $('#brm-range-ahead').text('(' + data.period.ahead_label + ')');
            } else {
                $('#brm-report-period').text('No period set').show();
                $('#brm-range-prior, #brm-range-ahead').text('');
            }
            $('#brm-edit-period').show();

            this.priorItems = data.prior_items;
            this.aheadItems = data.ahead_items;
            this.renderItems('prior', data.prior_items);
            this.renderItems('ahead', data.ahead_items);
        },

        renderItems: function(direction, items) {
            var $tbody = $('#brm-' + direction + '-items').empty();

            // Update button state first — before any early return
            var $addBtn = $tbody.closest('.brm-section-editor').find('.brm-add-item');
            if (items.length >= 3) {
                $addBtn.prop('disabled', true).text('Limit reached (3 items)');
            } else {
                $addBtn.prop('disabled', false).text('+ Add Item');
            }

            if (items.length === 0) { $tbody.html('<tr class="brm-empty-row"><td colspan="7">No items yet.</td></tr>'); return; }

            var self = this;
            items.forEach(function(item, idx) {
                // Build outputs display with full path: GoalLetter.ObjNumber.OutputLetter
                var outputParts = [];
                if (item.outputs && item.outputs.length) {
                    item.outputs.forEach(function(o) {
                        if (o.output_desc) {
                            var path = '';
                            if (o.goal_letter) path += o.goal_letter + '.';
                            if (o.objective_number) path += o.objective_number + '.';
                            if (o.output_letter) path += o.output_letter + '.';
                            var txt = path ? path + ' ' + o.output_desc : o.output_desc;
                            if (txt.length > 60) txt = txt.substring(0, 57) + '…';
                            var part = self.esc(txt);
                            if (o.topics && o.topics.length) part += '<span class="brm-output-topics">Topics: ' + self.esc(o.topics.join(', ')) + '</span>';
                            outputParts.push(part);
                        }
                    });
                }
                var outputDisplay = outputParts.length ? outputParts.join('<br>') : '—';
                var outputFullText = '';
                if (item.outputs && item.outputs.length) {
                    outputFullText = item.outputs.map(function(o) {
                        var path = '';
                        if (o.goal_letter) path += o.goal_letter + '.';
                        if (o.objective_number) path += o.objective_number + '.';
                        if (o.output_letter) path += o.output_letter + '.';
                        var t = path ? path + ' ' + o.output_desc : o.output_desc;
                        if (o.topics && o.topics.length) t += ' [Topics: ' + o.topics.join(', ') + ']';
                        return t;
                    }).join('\n');
                }

                var summaryVal = item.summary || '';

                var $row = $('<tr>' +
                    '<td>' + self.esc(item.post_type_label) + '</td>' +
                    '<td class="brm-title-cell"><a href="' + self.esc(item.permalink) + '" target="_blank">' + self.esc(item.post_title) + '</a>' +
                        (item.post_type === 'bimonthly-highlight' ? ' <button type="button" class="button-link brm-edit-title" data-post-id="' + item.post_id + '" data-direction="' + direction + '" data-index="' + idx + '" title="Edit title">✎</button>' : '') +
                    '</td>' +
                    '<td>' + self.esc(item.author) + '</td>' +
                    '<td>' + self.esc(item.date) + '</td>' +
                    '<td class="brm-summary-cell"><textarea class="brm-summary-textarea" data-post-id="' + item.post_id + '" rows="3">' + self.esc(summaryVal) + '</textarea></td>' +
                    '<td class="brm-output-cell" title="' + self.esc(outputFullText) + '">' + outputDisplay + '</td>' +
                    '<td class="brm-action-cell">' +
                        '<button class="button button-small brm-edit-item" data-direction="' + direction + '" data-index="' + idx + '" title="Edit">✎</button> ' +
                        '<button class="button button-link-delete brm-delete-item" data-direction="' + direction + '" data-index="' + idx + '" title="Remove">✕</button>' +
                    '</td>' +
                    '</tr>');

                $tbody.append($row);
            });
        },

        // =====================================================================
        // New / Delete update
        // =====================================================================

        showNewModal: function() { $('#brm-new-title').val(''); $('#brm-new-group').val(''); $('#brm-new-period').val(''); $('#brm-new-modal-overlay').show(); },
        hideNewModal: function() { $('#brm-new-modal-overlay').hide(); },

        createReport: function() {
            var self = this, title = $('#brm-new-title').val().trim();
            var period = $('#brm-new-period').val();
            var year = $('#brm-new-year').val();
            if (!title) { alert('Please enter a title.'); return; }
            if (!period) { alert('Please select a reporting period.'); return; }
            $.post(brm_ajax.ajax_url, {
                action: 'brm_create_bimonthly', nonce: brm_ajax.nonce,
                title: title, group: $('#brm-new-group').val(),
                period: period, year: year
            }, function(res) {
                if (res.success) { self.hideNewModal(); self.loadReportList(); self.loadReport(res.data.id); }
                else alert('Error: ' + (res.data || ''));
            });
        },

        deleteReport: function() {
            if (!this.currentReportId || !confirm('Permanently delete this update?')) return;
            var self = this;
            $.post(brm_ajax.ajax_url, { action: 'brm_delete_bimonthly', nonce: brm_ajax.nonce, post_id: this.currentReportId }, function(res) {
                if (res.success) { self.currentReportId = 0; $('#brm-editor-content').hide(); $('#brm-no-selection').show(); self.loadReportList(); }
                else alert('Error: ' + (res.data || ''));
            });
        },

        exportPdf: function() {
            if (!this.currentReportId) return;
            window.open(brm_ajax.ajax_url + '?action=brm_export_pdf&post_id=' + this.currentReportId + '&nonce=' + brm_ajax.nonce, '_blank');
        },

        // =====================================================================
        // Period editing
        // =====================================================================

        showPeriodEditor: function() {
            // Pre-select current values from the badge text
            var $periodBadge = $('#brm-report-period');
            var text = $periodBadge.text(); // e.g. "January – February 2026"

            // Try to detect current period/year from stored data
            // We'll read from the last loaded report data
            var $editPeriod = $('#brm-edit-period-select');
            var $editYear = $('#brm-edit-year-select');

            // Reset
                       if (this.currentPeriod) {
                $editPeriod.val(this.currentPeriod.period_key);
                $editYear.val(this.currentPeriod.year);
            } else {
                $editPeriod.val('');
                $editYear.val(new Date().getFullYear());
            }
            $('#brm-edit-title-input').val($('#brm-report-title').text());
            // Show editor, hide badge and edit button
            $periodBadge.hide();
            $('#brm-edit-period').hide();
            $('#brm-period-editor').show();
        },

        hidePeriodEditor: function() {
            $('#brm-period-editor').hide();
            $('#brm-report-period').show();
            $('#brm-edit-period').show();
        },

        savePeriod: function() {
            var self = this;
            var period = $('#brm-edit-period-select').val();
            var year = $('#brm-edit-year-select').val();

            var title = $.trim($('#brm-edit-title-input').val());

            if (!title) { alert('Please enter a title.'); return; }
            if (!period) { alert('Please select a period.'); return; }

            $('#brm-save-period').prop('disabled', true).text('Saving…');

            $.post(brm_ajax.ajax_url, {
                action: 'brm_save_period',
                nonce: brm_ajax.nonce,
                post_id: this.currentReportId,
                title: title,
                period: period,
                year: year
            }, function(res) {
                $('#brm-save-period').prop('disabled', false).text('Save');
                if (res.success) {
                    self.hidePeriodEditor();
                    if (res.data.title) $('#brm-report-title').text(res.data.title);

                    // Update the display
                    var p = res.data.period;
                    if (p) {
						self.currentPeriod = p;
                        $('#brm-report-period').text(p.span_label).show();
                        $('#brm-range-prior').text('(' + p.prior_label + ')');
                        $('#brm-range-ahead').text('(' + p.ahead_label + ')');
						
                    }

                    // Refresh the sidebar list to reflect updated period
                    self.loadReportList();
                } else {
                    alert('Error: ' + (res.data || ''));
                }
            });
        },

        // =====================================================================
        // Item modal
        // =====================================================================

        showItemModal: function() {
            this.editingIndex = -1;
            this.stagedOutputs = [];
            $('#brm-post-type').val('').prop('disabled', false);
            $('#brm-post-select').val('').prop('disabled', true).html('<option value="">— Select a post type first —</option>');
            $('#brm-add-highlight-wrap').hide();
            this.hideNewHighlightRegion();
            this.resetWorkplanSelects();
            $('#brm-staged-outputs').hide();
            $('#brm-staged-outputs-list').empty();
            $('#brm-add-output-btn').prop('disabled', true);
            $('#brm-modal-save').prop('disabled', true).text('Save & Close');
            $('#brm-modal .brm-modal-header h3').text('Add Update Item');
            $('#brm-modal-overlay').show();
        },

        editItem: function(index) {
            var items = (this.currentDirection === 'prior') ? this.priorItems : this.aheadItems;
            var item = items[index];
            if (!item) return;

            this.editingIndex = index;
            this.stagedOutputs = [];

            // Pre-populate staged outputs from existing item
            if (item.outputs && item.outputs.length) {
                item.outputs.forEach(function(o) {
                    var path = '';
                    if (o.goal_letter) path += o.goal_letter + '.';
                    if (o.objective_number) path += o.objective_number + '.';
                    if (o.output_letter) path += o.output_letter + '.';
                    var label = path ? path + ' ' + (o.output_desc || '') : (o.output_desc || 'Output');

                    BRM.stagedOutputs.push({
                        workplan_id: o.workplan_id,
                        goal_id: o.goal_id,
                        objective_id: o.objective_id,
                        output_index: o.output_index,
                        label: label,
                        topics: o.topics || [],
                        topic_values: o.topic_values || []
                    });
                });
            }

            // Lock the post selection — show the current post
            $('#brm-post-type').val(item.post_type).prop('disabled', true);
            $('#brm-post-select').html('<option value="' + item.post_id + '">' + BRM.esc(item.post_title) + ' (' + BRM.esc(item.date) + ')</option>').val(item.post_id).prop('disabled', true);
            $('#brm-add-highlight-wrap').hide();
            this.hideNewHighlightRegion();

            // Load workplans for the cascade
            var self = this;
            $('#brm-workplan').prop('disabled', true).html('<option value="">Loading work plans…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_workplans', nonce: brm_ajax.nonce, bimonthly_id: this.currentReportId }, function(res) {
                if (res.success) {
                    var h = '<option value="">— Select a work plan —</option>';
                    res.data.forEach(function(w) { h += '<option value="' + w.id + '">' + BRM.esc(w.title) + '</option>'; });
                    $('#brm-workplan').html(h).prop('disabled', false);
                }
            });

            $('#brm-goal').val('').prop('disabled', true).html('<option value="">— Select a work plan first —</option>');
            $('#brm-objective').val('').prop('disabled', true).html('<option value="">— Select a goal first —</option>');
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
            BRM.outputTopics = {}; BRM.outputTopicValues = {}; $('#brm-output-topics').hide().empty();
            $('#brm-add-output-btn').prop('disabled', true);

            this.renderStagedOutputs();
            $('#brm-modal-save').prop('disabled', false).text('Save & Close');
            $('#brm-modal .brm-modal-header h3').text('Edit Update Item');
            $('#brm-modal-overlay').show();
        },

        hideItemModal: function() {
            $('#brm-modal-overlay').hide();
            // Re-enable fields that may have been locked in edit mode
            $('#brm-post-type').prop('disabled', false);
            $('#brm-post-select').prop('disabled', false);
            $('#brm-add-highlight-wrap').hide();
            this.hideNewHighlightRegion();
            this.editingIndex = -1;
        },

        resetWorkplanSelects: function() {
            $('#brm-workplan').val('').prop('disabled', true).html('<option value="">— Select a post first —</option>');
            $('#brm-goal').val('').prop('disabled', true).html('<option value="">— Select a work plan first —</option>');
            $('#brm-objective').val('').prop('disabled', true).html('<option value="">— Select a goal first —</option>');
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
            BRM.outputTopics = {}; BRM.outputTopicValues = {}; $('#brm-output-topics').hide().empty();
            $('#brm-add-output-btn').prop('disabled', true);
        },

        // =====================================================================
        // Cascading AJAX
        // =====================================================================

        onPostTypeChange: function() {
            var type = $('#brm-post-type').val(), $sel = $('#brm-post-select');

            $('#brm-add-highlight-wrap').toggle(type === 'bimonthly-highlight');
            this.hideNewHighlightRegion();

            if (!type) { $sel.prop('disabled', true).html('<option value="">— Select a post type first —</option>'); return; }
            $sel.prop('disabled', true).html('<option value="">Loading…</option>');

            $.post(brm_ajax.ajax_url, { action: 'brm_get_filtered_posts', nonce: brm_ajax.nonce, direction: this.currentDirection, post_type: type, bimonthly_id: this.currentReportId }, function(res) {
                if (!res.success) return;
                var h = '<option value="">— Select a post —</option>';
                res.data.forEach(function(p) { h += '<option value="' + p.id + '">' + BRM.esc(p.title) + ' (' + BRM.esc(p.date) + ')</option>'; });
                $sel.html(h).prop('disabled', false);
                if (res.data.length === 0) $sel.html('<option value="">No posts found in date range</option>');
            });

            this.resetWorkplanSelects();
            this.stagedOutputs = [];
            this.renderStagedOutputs();
            $('#brm-modal-save').prop('disabled', true);
        },

        // =====================================================================
        // Inline "Add Bi-Monthly Highlight" creation
        // =====================================================================

        showNewHighlightRegion: function() {
            $('#brm-highlight-title').val('');
            $('#brm-highlight-date').val('');
            $('#brm-highlight-text').val('');
            $('#brm-new-highlight-region').show();
        },

        hideNewHighlightRegion: function() {
            $('#brm-new-highlight-region').hide();
        },

        createHighlight: function() {
            var self = this;
            var title = $('#brm-highlight-title').val().trim();
            var date = $('#brm-highlight-date').val();
            var text = $('#brm-highlight-text').val().trim();

            if (!title) { alert('Please enter a post title.'); return; }
            if (!date) { alert('Please enter a highlight date.'); return; }

            $('#brm-highlight-save').prop('disabled', true).text('Saving…');
            $.post(brm_ajax.ajax_url, {
                action: 'brm_create_highlight', nonce: brm_ajax.nonce,
                bimonthly_id: this.currentReportId, title: title, highlight_date: date, highlight_text: text
            }, function(res) {
                $('#brm-highlight-save').prop('disabled', false).text('Save Highlight');
                if (res.success) {
                    self.hideNewHighlightRegion();
                    self.refreshHighlightSelect(res.data);
                } else {
                    alert('Error: ' + (res.data || ''));
                }
            });
        },

        // newPost (optional) is the {id, title, date} just created — used to select it once the
        // refreshed list comes back, and to detect (for messaging only) whether its highlight_date
        // falls outside the prior/ahead date-range filter, same as any other post type here.
        refreshHighlightSelect: function(newPost) {
            var self = this, $sel = $('#brm-post-select');
            $sel.prop('disabled', true).html('<option value="">Loading…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_filtered_posts', nonce: brm_ajax.nonce, direction: this.currentDirection, post_type: 'bimonthly-highlight', bimonthly_id: this.currentReportId }, function(res) {
                var list = (res.success && res.data) ? res.data : [];

                var h = '<option value="">— Select a post —</option>';
                list.forEach(function(p) { h += '<option value="' + p.id + '">' + BRM.esc(p.title) + ' (' + BRM.esc(p.date) + ')</option>'; });
                if (list.length === 0) h = '<option value="">No posts found in date range</option>';
                $sel.html(h).prop('disabled', false);

                if (newPost) {
                    var found = list.some(function(p) { return p.id === newPost.id; });
                    if (found) {
                        $sel.val(newPost.id);
                        self.onPostSelected();
                    } else {
                        alert('The highlight was created, but its date (' + newPost.date + ') falls outside the ' +
                            (self.currentDirection === 'prior' ? 'past' : 'upcoming') +
                            ' date range for this section, so it isn\'t available to select here.');
                    }
                }
            });
        },

        onPostSelected: function() {
            var pid = $('#brm-post-select').val(), $wp = $('#brm-workplan');
            if (!pid) { this.resetWorkplanSelects(); $('#brm-modal-save').prop('disabled', true); return; }

            // Enable save button once a post is selected (outputs are optional)
            $('#brm-modal-save').prop('disabled', false);

            $wp.prop('disabled', true).html('<option value="">Loading work plans…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_workplans', nonce: brm_ajax.nonce, bimonthly_id: this.currentReportId }, function(res) {
                if (!res.success) return;
                var h = '<option value="">— Select a work plan —</option>';
                res.data.forEach(function(w) { h += '<option value="' + w.id + '">' + BRM.esc(w.title) + '</option>'; });
                $wp.html(h).prop('disabled', false);
            });

            $('#brm-goal').val('').prop('disabled', true).html('<option value="">— Select a work plan first —</option>');
            $('#brm-objective').val('').prop('disabled', true).html('<option value="">— Select a goal first —</option>');
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
            BRM.outputTopics = {}; BRM.outputTopicValues = {}; $('#brm-output-topics').hide().empty();
            $('#brm-add-output-btn').prop('disabled', true);
        },

        onWorkplanChange: function() {
            var id = $('#brm-workplan').val(), $g = $('#brm-goal');
            if (!id) { $g.prop('disabled', true).html('<option value="">— Select a work plan first —</option>'); return; }
            $g.prop('disabled', true).html('<option value="">Loading goals…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_goals', nonce: brm_ajax.nonce, workplan_id: id }, function(res) {
                if (!res.success) return;
                var h = '<option value="">— Select a goal —</option>';
                res.data.forEach(function(g) { h += '<option value="' + g.id + '">' + BRM.esc(g.title) + '</option>'; });
                $g.html(h).prop('disabled', false);
            });
            $('#brm-objective').val('').prop('disabled', true).html('<option value="">— Select a goal first —</option>');
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
            BRM.outputTopics = {}; BRM.outputTopicValues = {}; $('#brm-output-topics').hide().empty();
            $('#brm-add-output-btn').prop('disabled', true);
        },

        onGoalChange: function() {
            var id = $('#brm-goal').val(), $o = $('#brm-objective');
            if (!id) { $o.prop('disabled', true).html('<option value="">— Select a goal first —</option>'); return; }
            $o.prop('disabled', true).html('<option value="">Loading objectives…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_objectives', nonce: brm_ajax.nonce, workplan_id: $('#brm-workplan').val(), goal_id: id }, function(res) {
                if (!res.success) return;
                var h = '<option value="">— Select an objective —</option>';
                res.data.forEach(function(o) { h += '<option value="' + o.id + '">' + BRM.esc(o.title) + '</option>'; });
                $o.html(h).prop('disabled', false);
            });
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
            BRM.outputTopics = {}; BRM.outputTopicValues = {}; $('#brm-output-topics').hide().empty();
            $('#brm-add-output-btn').prop('disabled', true);
        },

        onObjectiveChange: function() {
            var id = $('#brm-objective').val(), $out = $('#brm-output');
            if (!id) { $out.prop('disabled', true).html('<option value="">— Select an objective first —</option>'); return; }
            $out.prop('disabled', true).html('<option value="">Loading outputs…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_outputs', nonce: brm_ajax.nonce, workplan_id: $('#brm-workplan').val(), goal_id: $('#brm-goal').val(), objective_id: id }, function(res) {
                if (!res.success) return;
                var h = '<option value="">— Select an output —</option>';
                BRM.outputTopics = {}; BRM.outputTopicValues = {};
                res.data.forEach(function(o) { BRM.outputTopics[o.index] = o.topics || []; BRM.outputTopicValues[o.index] = o.topic_values || []; h += '<option value="' + o.index + '">' + BRM.esc(o.title) + '</option>'; });
                $out.html(h).prop('disabled', false);
                if (res.data.length === 0) $out.html('<option value="">No outputs found</option>');
            });
            $('#brm-add-output-btn').prop('disabled', true);
        },

        onOutputChange: function() {
            var val = $('#brm-output').val();
            $('#brm-add-output-btn').prop('disabled', val === '' || val === null);
            if (val === '' || val === null) { $('#brm-output-topics').hide().empty(); return; }
            $('#brm-output-topics').html(this.topicEditorHtml({
                workplan_id: parseInt($('#brm-workplan').val()) || 0,
                goal_id: parseInt($('#brm-goal').val()) || 0,
                objective_id: parseInt($('#brm-objective').val()) || 0,
                output_index: parseInt(val) || 0
            }, BRM.outputTopicValues[val] || [])).show();
        },

        // =====================================================================
        // Topics of Interest editor (checkboxes on a work plan output)
        // =====================================================================

        topicEditorHtml: function(ctx, values) {
            var choices = brm_ajax.topic_choices || {}, keys = Object.keys(choices);
            if (!keys.length) return '';
            values = (values || []).map(String);
            var h = '<div class="brm-topic-editor" data-workplan="' + ctx.workplan_id + '" data-goal="' + ctx.goal_id +
                '" data-objective="' + ctx.objective_id + '" data-index="' + ctx.output_index + '">' +
                '<div class="brm-topic-editor-title">Topics of Interest <span class="brm-topic-status"></span></div>';
            keys.forEach(function(k) {
                h += '<label class="brm-topic-option"><input type="checkbox" value="' + BRM.esc(k) + '"' +
                    (values.indexOf(String(k)) !== -1 ? ' checked' : '') + '> ' + BRM.esc(choices[k]) + '</label>';
            });
            return h + '</div>';
        },

        saveOutputTopics: function($editor, $changed) {
            var ctx = {
                workplan_id: parseInt($editor.data('workplan')), goal_id: parseInt($editor.data('goal')),
                objective_id: parseInt($editor.data('objective')), output_index: parseInt($editor.data('index'))
            };
            var checked = $editor.find('input:checked').map(function() { return this.value; }).get();
            var $status = $editor.find('.brm-topic-status').text('Saving…').removeClass('brm-topic-error');
            var $inputs = $editor.find('input').prop('disabled', true);

            $.post(brm_ajax.ajax_url, $.extend({ action: 'brm_save_output_topics', nonce: brm_ajax.nonce, topics: checked }, ctx), function(res) {
                $inputs.prop('disabled', false);
                if (!res.success) {
                    $changed.prop('checked', !$changed.prop('checked')); // revert
                    $status.addClass('brm-topic-error').text('Not saved: ' + (res.data || 'error'));
                    return;
                }
                BRM.syncOutputTopics(ctx, res.data.topic_values, res.data.topics);
                $status.text('Saved');
                setTimeout(function() { $status.text(''); }, 1500);
            }).fail(function() {
                $inputs.prop('disabled', false);
                $changed.prop('checked', !$changed.prop('checked'));
                $status.addClass('brm-topic-error').text('Not saved: request failed');
            });
        },

        // Push new topics to everything in memory that shows the same output
        syncOutputTopics: function(ctx, values, labels) {
            var same = function(o) {
                return o.workplan_id === ctx.workplan_id && o.goal_id === ctx.goal_id &&
                       o.objective_id === ctx.objective_id && o.output_index === ctx.output_index;
            };
            var apply = function(o) { if (same(o)) { o.topic_values = values.slice(); o.topics = labels.slice(); } };
            this.stagedOutputs.forEach(apply);
            [this.priorItems, this.aheadItems].forEach(function(list) {
                (list || []).forEach(function(it) { (it.outputs || []).forEach(apply); });
            });
            if (parseInt($('#brm-objective').val()) === ctx.objective_id) {
                this.outputTopics[ctx.output_index] = labels.slice();
                this.outputTopicValues[ctx.output_index] = values.slice();
            }
            // Keep any other open editor for this output in step
            $('.brm-topic-editor').each(function() {
                var $e = $(this);
                if (parseInt($e.data('objective')) !== ctx.objective_id || parseInt($e.data('index')) !== ctx.output_index) return;
                $e.find('input').each(function() { this.checked = values.indexOf(this.value) !== -1; });
            });
            this.renderItems('prior', this.priorItems);
            this.renderItems('ahead', this.aheadItems);
        },

        // =====================================================================
        // Staged outputs
        // =====================================================================

        stageOutput: function() {
            var output = {
                workplan_id: parseInt($('#brm-workplan').val()) || 0,
                goal_id: parseInt($('#brm-goal').val()) || 0,
                objective_id: parseInt($('#brm-objective').val()) || 0,
                output_index: parseInt($('#brm-output').val()) || 0,
                label: $('#brm-output option:selected').text(),
                topics: (BRM.outputTopics && BRM.outputTopics[$('#brm-output').val()]) || [],
                topic_values: (BRM.outputTopicValues && BRM.outputTopicValues[$('#brm-output').val()]) || []
            };

            this.stagedOutputs.push(output);
            this.renderStagedOutputs();

            // Reset workplan cascade for next output selection
            $('#brm-workplan').val('');
            $('#brm-goal').val('').prop('disabled', true).html('<option value="">— Select a work plan first —</option>');
            $('#brm-objective').val('').prop('disabled', true).html('<option value="">— Select a goal first —</option>');
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
            BRM.outputTopics = {}; BRM.outputTopicValues = {}; $('#brm-output-topics').hide().empty();
            $('#brm-add-output-btn').prop('disabled', true);

            // Enable save since we have at least one output
            $('#brm-modal-save').prop('disabled', false);
        },

        renderStagedOutputs: function() {
            var $list = $('#brm-staged-outputs-list').empty();
            var $container = $('#brm-staged-outputs');

            if (this.stagedOutputs.length === 0) {
                $container.hide();
                return;
            }

            $container.show();
            this.stagedOutputs.forEach(function(o, i) {
                var topicsHtml = BRM.topicEditorHtml(o, o.topic_values || []);
                $list.append('<li><span>' + BRM.esc(o.label) + topicsHtml + '</span> <button type="button" class="brm-remove-staged" data-index="' + i + '" title="Remove">✕</button></li>');
            });
        },

        // =====================================================================
        // Save item
        // =====================================================================

        saveItem: function() {
            var self = this, direction = this.currentDirection;
            var postId = parseInt($('#brm-post-select').val());
            if (!postId) { alert('Please select a post.'); return; }

            // An output that is selected in the dropdowns but was never added with "+ Add Output"
            // would otherwise be dropped silently — stage it now.
            var pendingOutput = $('#brm-output').val();
            if (pendingOutput !== '' && pendingOutput !== null && !$('#brm-add-output-btn').prop('disabled')) {
                var pending = {
                    workplan_id: parseInt($('#brm-workplan').val()) || 0,
                    goal_id: parseInt($('#brm-goal').val()) || 0,
                    objective_id: parseInt($('#brm-objective').val()) || 0,
                    output_index: parseInt(pendingOutput) || 0
                };
                var already = this.stagedOutputs.some(function(o) {
                    return o.workplan_id === pending.workplan_id && o.goal_id === pending.goal_id &&
                           o.objective_id === pending.objective_id && o.output_index === pending.output_index;
                });
                if (!already) this.stagedOutputs.push(pending);
            }

            var newItem = {
                post_id: postId,
                post_type: $('#brm-post-type').val(),
                outputs: this.stagedOutputs.map(function(o) {
                    return { workplan_id: o.workplan_id, goal_id: o.goal_id, objective_id: o.objective_id, output_index: o.output_index };
                })
            };

            var items = (direction === 'prior') ? this.priorItems : this.aheadItems;
            var rawItems = items.map(function(item) {
                return {
                    post_id: item.post_id, post_type: item.post_type,
                    outputs: (item.outputs || []).map(function(o) {
                        return { workplan_id: o.workplan_id, goal_id: o.goal_id, objective_id: o.objective_id, output_index: o.output_index };
                    })
                };
            });

            // Edit mode: replace at index. Add mode: append.
            if (this.editingIndex >= 0 && this.editingIndex < rawItems.length) {
                rawItems[this.editingIndex] = newItem;
            } else {
                rawItems.push(newItem);
            }

            $('#brm-modal-save').prop('disabled', true).text('Saving…');

            $.post(brm_ajax.ajax_url, {
                action: 'brm_save_report_items', nonce: brm_ajax.nonce,
                post_id: this.currentReportId, direction: direction, items: JSON.stringify(rawItems)
            }, function(res) {
                $('#brm-modal-save').prop('disabled', false).text('Save & Close');
                if (res.success) {
                    if (direction === 'prior') self.priorItems = res.data; else self.aheadItems = res.data;
                    self.renderItems(direction, res.data);
                    self.hideItemModal();
                } else alert('Error saving: ' + (res.data || ''));
            });
        },

        // =====================================================================
        // Delete item
        // =====================================================================

        deleteItem: function(direction, index) {
            if (!confirm('Remove this item from the update?')) return;
            var self = this;
            $.post(brm_ajax.ajax_url, { action: 'brm_delete_report_item', nonce: brm_ajax.nonce, post_id: this.currentReportId, direction: direction, item_index: index }, function(res) {
                if (res.success) {
                    if (direction === 'prior') self.priorItems = res.data; else self.aheadItems = res.data;
                    self.renderItems(direction, res.data);
                } else alert('Error: ' + (res.data || ''));
            });
        },

        // =====================================================================
        // Edit highlight title (inline)
        // =====================================================================

        startTitleEdit: function($td) {
            if ($td.find('.brm-title-input').length) return;
            var $btn = $td.find('.brm-edit-title');
            var items = ($btn.data('direction') === 'prior') ? this.priorItems : this.aheadItems;
            var item = items[$btn.data('index')];
            if (!item) return;
            $td.data('orig', $td.html());
            var $input = $('<input type="text" class="brm-title-input" maxlength="200">')
                .val(item.post_title)
                .data({ postId: item.post_id, direction: $btn.data('direction'), index: $btn.data('index'), original: item.post_title });
            $td.empty().append($input).append('<div class="brm-title-hint">Enter to save · Esc to cancel</div>');
            $input.focus().select();
        },

        finishTitleEdit: function($input) {
            var self = this, $td = $input.closest('td');
            var d = $input.data(), title = $.trim($input.val());
            if (d.done) return; // disabling the field can fire a second blur
            $input.data('done', true);
            var items = (d.direction === 'prior') ? this.priorItems : this.aheadItems;

            // Blur without Enter saves too (matches the summary field); Esc or unchanged/empty just restores.
            if (d.cancel || title === '' || title === d.original) { $td.html($td.data('orig')); return; }

            $input.prop('disabled', true).addClass('brm-saving');
            $.post(brm_ajax.ajax_url, { action: 'brm_save_title', nonce: brm_ajax.nonce, report_id: this.currentReportId, post_id: d.postId, title: title }, function(res) {
                if (res.success) {
                    // The same highlight may appear in several rows/sections — keep all copies in sync
                    [self.priorItems, self.aheadItems].forEach(function(list) {
                        list.forEach(function(it) { if (it.post_id === d.postId) it.post_title = res.data.title; });
                    });
                    self.renderItems('prior', self.priorItems);
                    self.renderItems('ahead', self.aheadItems);
                } else {
                    $td.html($td.data('orig'));
                    alert('Error saving title: ' + (res.data || ''));
                }
            });
        },

        // =====================================================================
        // Save summary
        // =====================================================================

        saveSummary: function($ta) {
            var pid = $ta.data('post-id'), summary = $ta.val();
            $ta.addClass('brm-saving');
            $.post(brm_ajax.ajax_url, { action: 'brm_save_summary', nonce: brm_ajax.nonce, report_id: this.currentReportId, post_id: pid, summary: summary }, function(res) {
                $ta.removeClass('brm-saving');
                if (res.success) { $ta.addClass('brm-saved'); setTimeout(function() { $ta.removeClass('brm-saved'); }, 1500); }
                else alert('Error saving summary: ' + (res.data || ''));
            });
        },

        // =====================================================================
        // Utility
        // =====================================================================

        esc: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    window.BRM = BRM;
    $(document).ready(function() { BRM.init(); });
})(jQuery);
