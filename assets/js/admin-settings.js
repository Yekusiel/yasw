(function($) {
    'use strict';

    $(function() {

        // --- Sandbox toggle visual update ---
        $('.yasw-sandbox-toggle input[type="checkbox"]').on('change', function() {
            var $wrap = $(this).closest('.yasw-sandbox-toggle');
            if ($(this).is(':checked')) {
                $wrap.removeClass('yasw-sandbox-inactive').addClass('yasw-sandbox-active');
            } else {
                $wrap.removeClass('yasw-sandbox-active').addClass('yasw-sandbox-inactive');
            }
        });

        // --- Tab switching ---
        $('.yasw-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            $('.yasw-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.yasw-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');

            window.location.hash = tab;
        });

        var hash = window.location.hash.replace('#', '');
        if (hash) {
            $('.yasw-tabs .nav-tab[data-tab="' + hash + '"]').trigger('click');
        }

        // =====================================================================
        // Donation Types (Settings page)
        // =====================================================================
        var $table = $('#yasw-donation-types-table tbody');

        $('#yasw-add-type').on('click', function() {
            var idx = $table.find('tr').length;
            var row = '<tr class="yasw-type-row">' +
                '<td class="yasw-drag-handle">&#9776;</td>' +
                '<td><input type="text" name="yasw_donation_types[' + idx + '][label]" value="" class="regular-text yasw-type-label" placeholder="e.g. Building Fund"></td>' +
                '<td><input type="text" name="yasw_donation_types[' + idx + '][slug]" value="" class="regular-text yasw-type-slug" placeholder="e.g. building-fund" pattern="[a-z0-9\\-_]+" title="Lowercase letters, numbers, hyphens, underscores only"></td>' +
                '<td><button type="button" class="button yasw-remove-type" title="Remove">&times;</button></td>' +
                '</tr>';
            $table.append(row);
        });

        $table.on('click', '.yasw-remove-type', function() {
            if ($table.find('tr').length <= 1) {
                alert('You must have at least one donation type.');
                return;
            }
            $(this).closest('tr').remove();
            reindexRows();
        });

        $table.on('input', '.yasw-type-label', function() {
            var $slug = $(this).closest('tr').find('.yasw-type-slug');
            if (!$slug.data('manual')) {
                $slug.val($(this).val().toLowerCase().replace(/[^a-z0-9\s\-_]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-'));
            }
        });

        $table.on('input', '.yasw-type-slug', function() {
            $(this).data('manual', true);
        });

        function reindexRows() {
            $table.find('tr').each(function(i) {
                $(this).find('input').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
                    }
                });
            });
        }

        // Drag and drop
        var dragRow = null;
        $table.on('mousedown', '.yasw-drag-handle', function(e) {
            dragRow = $(this).closest('tr').addClass('dragging');
            e.preventDefault();
        });
        $(document).on('mousemove', function(e) {
            if (!dragRow) return;
            var $rows = $table.find('tr:not(.dragging)');
            $rows.each(function() {
                if (e.pageY < $(this).offset().top + $(this).outerHeight() / 2) {
                    dragRow.insertBefore($(this));
                    return false;
                } else if ($(this).is($rows.last())) {
                    dragRow.insertAfter($(this));
                }
            });
        });
        $(document).on('mouseup', function() {
            if (dragRow) {
                dragRow.removeClass('dragging');
                dragRow = null;
                reindexRows();
            }
        });

        // =====================================================================
        // Donations List Page
        // =====================================================================
        if ($('#yasw-donations-table').length === 0) return;

        var currentPage = 1;
        var currentSort = 'created_at';
        var currentDir = 'DESC';
        var searchTimer = null;
        var methodLabels = {
            'credit_card': 'Credit Card',
            'donors_fund': 'Donors Fund',
            'ojc_fund': 'OJC Fund',
            'pledger': 'Pledger'
        };

        // Load on init
        loadDonations();

        // Search with debounce
        $('#yasw-search').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                currentPage = 1;
                loadDonations();
            }, 400);
        });

        // Filters
        $('#yasw-filter-status, #yasw-filter-method, #yasw-filter-type, #yasw-filter-date-from, #yasw-filter-date-to').on('change', function() {
            currentPage = 1;
            loadDonations();
        });

        // Sorting
        $('.yasw-sortable').on('click', function() {
            var col = $(this).data('sort');
            if (currentSort === col) {
                currentDir = currentDir === 'DESC' ? 'ASC' : 'DESC';
            } else {
                currentSort = col;
                currentDir = 'DESC';
            }
            $('.yasw-sortable').removeClass('sort-asc sort-desc');
            $(this).addClass(currentDir === 'ASC' ? 'sort-asc' : 'sort-desc');
            loadDonations();
        });

        // Pagination clicks
        $('#yasw-pagination').on('click', '.yasw-page-btn', function() {
            if ($(this).prop('disabled') || $(this).hasClass('active')) return;
            currentPage = parseInt($(this).data('page'));
            loadDonations();
        });

        // View detail
        $('#yasw-donations-tbody').on('click', '.yasw-view-btn', function() {
            var id = $(this).data('id');
            loadDonationDetail(id);
        });

        // Resend receipt (placeholder)
        $('#yasw-donations-tbody').on('click', '.yasw-resend-btn', function() {
            alert('Receipt resend is not yet implemented.');
        });

        // Close modal
        $('#yasw-modal-close, #yasw-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                $('#yasw-modal-overlay').hide();
            }
        });
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') $('#yasw-modal-overlay').hide();
        });

        // --- Load donations via AJAX ---
        function loadDonations() {
            var $tbody = $('#yasw-donations-tbody');
            $tbody.html('<tr><td colspan="9" class="yasw-loading">Loading...</td></tr>');

            $.post(yaswAdmin.ajaxUrl, {
                action: 'yasw_get_donations',
                nonce: yaswAdmin.nonce,
                page: currentPage,
                search: $('#yasw-search').val(),
                status: $('#yasw-filter-status').val(),
                method: $('#yasw-filter-method').val(),
                donation_type: $('#yasw-filter-type').val(),
                date_from: $('#yasw-filter-date-from').val(),
                date_to: $('#yasw-filter-date-to').val(),
                sort: currentSort,
                sort_dir: currentDir
            }, function(response) {
                if (!response.success) {
                    $tbody.html('<tr><td colspan="9" class="yasw-no-results">Error loading donations.</td></tr>');
                    return;
                }

                var data = response.data;
                renderStats(data.stats);
                renderTable(data.donations);
                renderPagination(data.page, data.pages, data.total);
            });
        }

        // --- Render stats ---
        function renderStats(stats) {
            $('#yasw-donations-stats').html(
                '<div class="yasw-stat-card">' +
                    '<span class="yasw-stat-value">' + stats.total + '</span>' +
                    '<span class="yasw-stat-label">Total</span>' +
                '</div>' +
                '<div class="yasw-stat-card yasw-stat-approved">' +
                    '<span class="yasw-stat-value">' + stats.approved + '</span>' +
                    '<span class="yasw-stat-label">Approved</span>' +
                '</div>' +
                '<div class="yasw-stat-card yasw-stat-declined">' +
                    '<span class="yasw-stat-value">' + stats.declined + '</span>' +
                    '<span class="yasw-stat-label">Declined</span>' +
                '</div>' +
                '<div class="yasw-stat-card yasw-stat-revenue">' +
                    '<span class="yasw-stat-value">$' + parseFloat(stats.sum).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>' +
                    '<span class="yasw-stat-label">Revenue</span>' +
                '</div>'
            );
        }

        // --- Render table rows ---
        function renderTable(donations) {
            var $tbody = $('#yasw-donations-tbody');

            if (!donations || donations.length === 0) {
                $tbody.html(
                    '<tr><td colspan="8">' +
                    '<div class="yasw-empty-state">' +
                        '<div class="yasw-empty-icon">&#128176;</div>' +
                        '<p class="yasw-empty-title">No donations found</p>' +
                        '<p class="yasw-empty-text">Donations will appear here once they are submitted through the form.</p>' +
                    '</div>' +
                    '</td></tr>'
                );
                return;
            }

            var html = '';
            $.each(donations, function(i, d) {
                var date = new Date(d.created_at);
                var dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                var timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                html += '<tr>' +
                    '<td>' + d.id + '</td>' +
                    '<td><span class="yasw-date-primary">' + dateStr + '</span><span class="yasw-date-secondary">' + timeStr + '</span></td>' +
                    '<td><span class="yasw-donor-name">' + escHtml(d.full_name) + '</span><span class="yasw-donor-email">' + escHtml(d.email) + '</span></td>' +
                    '<td><span class="yasw-type-label">' + escHtml(d.donation_type) + '</span></td>' +
                    '<td><span class="yasw-method-label">' + (methodLabels[d.payment_method] || d.payment_method) + '</span></td>' +
                    '<td><span class="yasw-amount">$' + parseFloat(d.total).toFixed(2) + '</span></td>' +
                    '<td><span class="yasw-badge yasw-badge-' + d.status + '">' + d.status + '</span></td>' +
                    '<td class="yasw-actions">' +
                        '<button type="button" class="yasw-action-btn yasw-view-btn" data-id="' + d.id + '" title="View Details">&#128065;</button>' +
                        '<button type="button" class="yasw-action-btn yasw-resend-btn" data-id="' + d.id + '" title="Resend Receipt">&#9993;</button>' +
                    '</td>' +
                '</tr>';
            });

            $tbody.html(html);
        }

        // --- Render pagination ---
        function renderPagination(page, pages, total) {
            var $pag = $('#yasw-pagination');
            if (pages <= 1) {
                $pag.html('<span class="yasw-pagination-info">Showing all ' + total + ' donation' + (total !== 1 ? 's' : '') + '</span>');
                return;
            }

            var from = (page - 1) * 20 + 1;
            var to = Math.min(page * 20, total);

            var html = '<span class="yasw-pagination-info">Showing ' + from + '–' + to + ' of ' + total + '</span>';
            html += '<div class="yasw-pagination-buttons">';

            // Prev
            html += '<button class="yasw-page-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>&laquo;</button>';

            // Page numbers
            var start = Math.max(1, page - 2);
            var end = Math.min(pages, page + 2);

            if (start > 1) {
                html += '<button class="yasw-page-btn" data-page="1">1</button>';
                if (start > 2) html += '<span style="padding:0 6px;color:#888;">...</span>';
            }

            for (var i = start; i <= end; i++) {
                html += '<button class="yasw-page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
            }

            if (end < pages) {
                if (end < pages - 1) html += '<span style="padding:0 6px;color:#888;">...</span>';
                html += '<button class="yasw-page-btn" data-page="' + pages + '">' + pages + '</button>';
            }

            // Next
            html += '<button class="yasw-page-btn" data-page="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + '>&raquo;</button>';
            html += '</div>';

            $pag.html(html);
        }

        // --- Load donation detail into modal ---
        function loadDonationDetail(id) {
            var $body = $('#yasw-modal-body');
            $body.html('<p style="text-align:center;padding:40px;color:#888;">Loading...</p>');
            $('#yasw-modal-overlay').show();

            $.post(yaswAdmin.ajaxUrl, {
                action: 'yasw_get_donation_detail',
                nonce: yaswAdmin.nonce,
                donation_id: id
            }, function(response) {
                if (!response.success) {
                    $body.html('<p style="color:red;">Error loading donation details.</p>');
                    return;
                }

                var d = response.data.donation;
                var errors = response.data.errors;
                var date = new Date(d.created_at);

                var html = '<div class="yasw-detail-grid">';

                // Row 1: Status + ID
                html += detailItem('Donation ID', '#' + d.id);
                html += detailItem('Status', '<span class="yasw-badge yasw-badge-' + d.status + '">' + d.status + '</span>');

                // Row 2: Date + Environment
                html += detailItem('Date', date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) + ' at ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }));
                html += detailItem('Environment', '<span class="yasw-env-badge ' + (d.sandbox == 1 ? 'yasw-env-sandbox' : 'yasw-env-live') + '">' + (d.sandbox == 1 ? 'Sandbox' : 'Live') + '</span>');

                html += '</div>';

                // Donor Info
                html += '<div class="yasw-detail-section-title">Donor Information</div>';
                html += '<div class="yasw-detail-grid">';
                html += detailItem('Full Name', d.full_name);
                html += detailItem('Email', d.email);
                html += detailItem('Phone', d.phone || '—');
                html += detailItem('ZIP', d.zip || '—');
                html += detailItem('Street Address', d.street_address || '—', true);
                if (d.message) html += detailItem('Message', d.message, true);
                html += '</div>';

                // Payment Info
                html += '<div class="yasw-detail-section-title">Payment Details</div>';
                html += '<div class="yasw-detail-grid">';
                html += detailItem('Donation Type', d.donation_type);
                html += detailItem('Payment Method', methodLabels[d.payment_method] || d.payment_method);
                html += detailItem('Amount', '$' + parseFloat(d.amount).toFixed(2));
                html += detailItem('Processing Fees', d.cover_fees == 1 ? 'Yes' : 'No');
                html += detailItem('Total Charged', '<strong>$' + parseFloat(d.total).toFixed(2) + '</strong>');

                if (d.payment_schedule) {
                    html += detailItem('Schedule', d.payment_schedule);
                    if (d.installment_months > 0) html += detailItem('Installment Months', d.installment_months);
                    if (d.repeat_frequency) html += detailItem('Frequency', d.repeat_frequency);
                }

                if (d.transaction_id) html += detailItem('Transaction ID', d.transaction_id);
                if (d.confirmation_number) html += detailItem('Confirmation #', d.confirmation_number);
                if (d.masked_card) html += detailItem('Card', d.masked_card);

                html += '</div>';

                // Errors
                if (errors && errors.length > 0) {
                    html += '<div class="yasw-detail-section-title">Errors</div>';
                    html += '<ul class="yasw-error-list">';
                    $.each(errors, function(i, err) {
                        var errDate = new Date(err.created_at);
                        html += '<li>';
                        if (err.error_code) html += '<span class="yasw-error-code">[' + escHtml(err.error_code) + ']</span>';
                        html += escHtml(err.error_message);
                        html += '<span class="yasw-error-time">' + errDate.toLocaleString() + '</span>';
                        html += '</li>';
                    });
                    html += '</ul>';
                }

                $body.html(html);
            });
        }

        function detailItem(label, value, full) {
            return '<div class="yasw-detail-item' + (full ? ' yasw-detail-full' : '') + '">' +
                '<span class="yasw-detail-label">' + label + '</span>' +
                '<span class="yasw-detail-value">' + (value || '—') + '</span>' +
            '</div>';
        }

        function escHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    });

})(jQuery);
