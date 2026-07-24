(function($) {
    'use strict';

    var BRM = {
        currentReportId: 0,
        currentDirection: '',
        editingIndex: -1, // -1 = adding new, >= 0 = editing existing
        priorItems: [],
        aheadItems: [],
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
                $list.append('<div class="brm-report-item' + (r.id === BRM.currentReportId ? ' active' : '') + '" data-id="' + r.id + '"><strong>' + BRM.esc(r.title) + '</strong><span class="brm-meta">' + BRM.esc(r.group) + ' · ' + BRM.esc(r.date) + '</span></div>');
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
            this.priorItems = data.prior_items;
            this.aheadItems = data.ahead_items;
            this.renderItems('prior', data.prior_items);
            this.renderItems('ahead', data.ahead_items);
        },

        renderItems: function(direction, items) {
            var $tbody = $('#brm-' + direction + '-items').empty();
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
                            outputParts.push(txt);
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
                        return path ? path + ' ' + o.output_desc : o.output_desc;
                    }).join('\n');
                }

                var summaryVal = item.summary || '';

                var $row = $('<tr>' +
                    '<td>' + self.esc(item.post_type_label) + '</td>' +
                    '<td><a href="' + self.esc(item.permalink) + '" target="_blank">' + self.esc(item.post_title) + '</a></td>' +
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

            // Disable/enable the Add Item button based on 3-item limit
            var $addBtn = $tbody.closest('.brm-section-editor').find('.brm-add-item');
            if (items.length >= 3) {
                $addBtn.prop('disabled', true).text('Limit reached (3 items)');
            } else {
                $addBtn.prop('disabled', false).text('+ Add Item');
            }
        },

        // =====================================================================
        // New / Delete update
        // =====================================================================

        showNewModal: function() { $('#brm-new-title').val(''); $('#brm-new-group').val(''); $('#brm-new-modal-overlay').show(); },
        hideNewModal: function() { $('#brm-new-modal-overlay').hide(); },

        createReport: function() {
            var self = this, title = $('#brm-new-title').val().trim();
            if (!title) { alert('Please enter a title.'); return; }
            $.post(brm_ajax.ajax_url, { action: 'brm_create_bimonthly', nonce: brm_ajax.nonce, title: title, group: $('#brm-new-group').val() }, function(res) {
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
                        label: label
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
            $('#brm-add-output-btn').prop('disabled', true);
        },

        onGoalChange: function() {
            var id = $('#brm-goal').val(), $o = $('#brm-objective');
            if (!id) { $o.prop('disabled', true).html('<option value="">— Select a goal first —</option>'); return; }
            $o.prop('disabled', true).html('<option value="">Loading objectives…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_objectives', nonce: brm_ajax.nonce, goal_id: id }, function(res) {
                if (!res.success) return;
                var h = '<option value="">— Select an objective —</option>';
                res.data.forEach(function(o) { h += '<option value="' + o.id + '">' + BRM.esc(o.title) + '</option>'; });
                $o.html(h).prop('disabled', false);
            });
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
            $('#brm-add-output-btn').prop('disabled', true);
        },

        onObjectiveChange: function() {
            var id = $('#brm-objective').val(), $out = $('#brm-output');
            if (!id) { $out.prop('disabled', true).html('<option value="">— Select an objective first —</option>'); return; }
            $out.prop('disabled', true).html('<option value="">Loading outputs…</option>');
            $.post(brm_ajax.ajax_url, { action: 'brm_get_outputs', nonce: brm_ajax.nonce, objective_id: id }, function(res) {
                if (!res.success) return;
                var h = '<option value="">— Select an output —</option>';
                res.data.forEach(function(o) { h += '<option value="' + o.index + '">' + BRM.esc(o.title) + '</option>'; });
                $out.html(h).prop('disabled', false);
                if (res.data.length === 0) $out.html('<option value="">No outputs found</option>');
            });
            $('#brm-add-output-btn').prop('disabled', true);
        },

        onOutputChange: function() {
            var val = $('#brm-output').val();
            $('#brm-add-output-btn').prop('disabled', val === '' || val === null);
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
                label: $('#brm-output option:selected').text()
            };

            this.stagedOutputs.push(output);
            this.renderStagedOutputs();

            // Reset workplan cascade for next output selection
            $('#brm-workplan').val('');
            $('#brm-goal').val('').prop('disabled', true).html('<option value="">— Select a work plan first —</option>');
            $('#brm-objective').val('').prop('disabled', true).html('<option value="">— Select a goal first —</option>');
            $('#brm-output').val('').prop('disabled', true).html('<option value="">— Select an objective first —</option>');
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
                $list.append('<li>' + BRM.esc(o.label) + ' <button type="button" class="brm-remove-staged" data-index="' + i + '" title="Remove">✕</button></li>');
            });
        },

        // =====================================================================
        // Save item
        // =====================================================================

        saveItem: function() {
            var self = this, direction = this.currentDirection;
            var postId = parseInt($('#brm-post-select').val());
            if (!postId) { alert('Please select a post.'); return; }

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
        // Save summary
        // =====================================================================

        saveSummary: function($ta) {
            var pid = $ta.data('post-id'), summary = $ta.val();
            $ta.addClass('brm-saving');
            $.post(brm_ajax.ajax_url, { action: 'brm_save_summary', nonce: brm_ajax.nonce, post_id: pid, summary: summary }, function(res) {
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
