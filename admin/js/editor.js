jQuery(function($) {
    const $modal = $('#azevent-studio-modal');
    const $openButton = $('#azevent-open-studio');
    const $setupView = $('#azevent-setup-view');
    const $workflowView = $('#azevent-workflow-view');
    const $reviewView = $('#azevent-review-view');
    const $completeView = $('#azevent-complete-view');
    const $queueView = $('#azevent-queue-view');
    const $startButton = $('#azevent-start-btn');
    const $openQueueButton = $('#azevent-open-queue');
    const $approveButton = $('#azevent-approve-btn');
    const $regenerateButton = $('#azevent-regenerate-content-btn');
    const $restartButton = $('#azevent-restart-btn');
    const $retryButton = $('#azevent-retry-btn');
    const $errorActions = $('#azevent-error-actions');
    const $processingPanel = $('#azevent-processing-panel');
    const $statusText = $('#azevent-status-text');
    const $log = $('#azevent-log');
    const $keywords = $('#azevent-keywords');
    const $keywordHelp = $('#azevent-keyword-help');
    const $regenerateImage = $('#azevent-regenerate-image');
    const $reviewFrame = $('#azevent-review-frame');
    const $queueRows = $('#azevent-queue-rows');
    const $queueEmpty = $('#azevent-queue-empty');
    const $queueNotice = $('#azevent-queue-notice');
    const stepOrder = ['intent', 'outline', 'content', 'seo', 'review', 'finish'];

    let studioState = 'idle';
    let isProcessing = false;
    let returnFocus = null;
    let mode = 'create';
    let language = azevent_seo.default_language || 'Vietnamese';
    let keywordQueue = [];
    let keywordIndex = 0;
    let currentPostId = 0;
    let currentContext = {};
    let pendingNextStep = '';
    let results = [];
    let lastRequestStep = '';
    let lastRequestContext = {};
    let queuePollTimer = null;
    let queueLoading = false;

    function getMode() {
        return $('input[name="azevent_mode"]:checked').val() || 'create';
    }

    function getKeywords() {
        const seen = {};
        return $keywords.val().split(/\r?\n/).map(function(keyword) {
            return keyword.trim();
        }).filter(function(keyword) {
            const key = keyword.toLowerCase();
            if (!keyword || seen[key]) {
                return false;
            }
            seen[key] = true;
            return true;
        });
    }

    function showView(view) {
        $setupView.prop('hidden', view !== 'setup');
        $workflowView.prop('hidden', view !== 'workflow');
        $reviewView.prop('hidden', view !== 'review');
        $completeView.prop('hidden', view !== 'complete');
        $queueView.prop('hidden', view !== 'queue');
    }

    function openModal() {
        returnFocus = document.activeElement;
        if (studioState === 'complete') {
            resetStudio();
        }
        $modal.attr('aria-hidden', 'false');
        $('body').addClass('azevent-modal-open');
        window.setTimeout(function() {
            $('#azevent-modal-close').trigger('focus');
        }, 20);
    }

    function closeModal() {
        if (isProcessing) {
            updateStatus('Quy trình đang chạy. Vui lòng chờ đến bước duyệt nội dung.');
            return;
        }
        $modal.attr('aria-hidden', 'true');
        $('body').removeClass('azevent-modal-open');
        stopQueuePolling();
        if (returnFocus) {
            $(returnFocus).trigger('focus');
        }
    }

    function syncModeHelp() {
        const selectedMode = getMode();
        if (selectedMode === 'background') {
            $keywordHelp.text('Background Queue: mỗi dòng là một Job, tối đa 100 từ khóa mỗi lần.');
            $startButton.html('Thêm vào Background Queue <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>');
            return;
        }
        if (selectedMode === 'rewrite') {
            $keywordHelp.text('Viết lại: chỉ dùng một từ khóa cho bài hiện tại.');
            $startButton.html('Bắt đầu phân tích <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>');
            if (!$keywords.val().trim() && $('#title').val()) {
                $keywords.val($('#title').val());
            }
            return;
        }
        $keywordHelp.text('Tạo mới: mỗi dòng sẽ tạo một Draft riêng.');
        $startButton.html('Bắt đầu phân tích <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>');
    }

    function updateStatus(text, addToLog) {
        $statusText.text(text);
        if (addToLog !== false) {
            const timestamp = new Date().toLocaleTimeString();
            $('<div>').text(timestamp + ' — ' + text).appendTo($log);
            $log.scrollTop($log[0].scrollHeight);
        }
    }

    function setActiveStep(step) {
        const activeIndex = stepOrder.indexOf(step);
        $('.azevent-stepper li').each(function(index) {
            $(this)
                .toggleClass('is-complete', activeIndex >= 0 && index < activeIndex)
                .toggleClass('is-active', index === activeIndex);
        });
    }

    function getStepFromRequest(step) {
        if (step === 'start' || step === 'search_intent') {
            return 'intent';
        }
        if (step === 'image' || step === 'finalize') {
            return 'finish';
        }
        return step;
    }

    function setControlsDisabled(disabled) {
        $startButton.prop('disabled', disabled);
        $approveButton.prop('disabled', disabled);
        $regenerateButton.prop('disabled', disabled);
    }

    function resetStudio() {
        studioState = 'idle';
        isProcessing = false;
        keywordQueue = [];
        keywordIndex = 0;
        currentPostId = 0;
        currentContext = {};
        pendingNextStep = '';
        results = [];
        lastRequestStep = '';
        lastRequestContext = {};
        stopQueuePolling();
        $log.empty();
        $processingPanel.removeClass('is-error');
        $errorActions.prop('hidden', true);
        $('.azevent-stepper li').removeClass('is-active is-complete');
        setControlsDisabled(false);
        showView('setup');
    }

    function showError(message) {
        studioState = 'error';
        isProcessing = false;
        setControlsDisabled(false);
        showView('workflow');
        $processingPanel.addClass('is-error');
        $errorActions.prop('hidden', false);
        updateStatus(message || 'Không thể hoàn tất yêu cầu.');
    }

    function buildPreviewDocument(content) {
        return '<!doctype html><html><head><meta charset="utf-8">' +
            '<style>body{box-sizing:border-box;max-width:960px;margin:0 auto;padding:38px 48px;font:16px/1.75 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1e293b}h2,h3,h4{color:#0f172a;line-height:1.35}h2{font-size:26px;margin-top:32px}h3{font-size:20px;margin-top:24px}p{margin:0 0 16px}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #dbe4f3;padding:9px;text-align:left}img{max-width:100%;height:auto}a{color:#4f46e5}@media(max-width:700px){body{padding:24px 20px;font-size:15px}}</style>' +
            '</head><body>' + (content || '') + '</body></html>';
    }

    function showReview(context, nextStep) {
        studioState = 'review';
        isProcessing = false;
        currentContext = context || {};
        pendingNextStep = nextStep || 'image';
        setControlsDisabled(false);
        setActiveStep('review');
        showView('review');

        const seo = currentContext.seo || {};
        $('#azevent-review-title').text(seo.title || keywordQueue[keywordIndex]);
        $('#azevent-review-meta').text(seo.meta || '');
        $reviewFrame.attr('srcdoc', buildPreviewDocument(currentContext.content || ''));

        const shouldCreateImage = mode === 'create' || pendingNextStep === 'image';
        $regenerateImage.prop('checked', shouldCreateImage);
        $regenerateImage.prop('disabled', mode === 'create');
        $approveButton.html(
            shouldCreateImage
                ? 'Duyệt, tạo ảnh và lưu Draft <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>'
                : 'Duyệt và lưu Draft <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>'
        );
    }

    function updateEditor(data) {
        if (data.title) {
            $('#title').val(data.title).trigger('change');
        }
        if (data.content) {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                tinyMCE.get('content').setContent(data.content);
            } else {
                $('#content').val(data.content);
            }
        }
    }

    function showComplete(lastResponse) {
        studioState = 'complete';
        isProcessing = false;
        setControlsDisabled(false);
        $('.azevent-stepper li').removeClass('is-active').addClass('is-complete');

        if (mode === 'rewrite') {
            updateEditor(lastResponse.data || {});
        }

        const count = results.length;
        const singlePostId = count === 1 ? results[0].postId : 0;
        const message = mode === 'rewrite'
            ? 'Bài viết đã được viết lại và lưu dưới dạng Draft.'
            : 'Đã tạo ' + count + ' bài Draft thành công.';

        $('#azevent-complete-message').text(message);
        $('#azevent-complete-link')
            .attr('href', singlePostId > 0
                ? azevent_seo.admin_url + 'post.php?post=' + singlePostId + '&action=edit'
                : azevent_seo.admin_url + 'edit.php?post_type=post')
            .text(singlePostId > 0 ? 'Mở bài Draft' : 'Xem danh sách Draft');
        showView('complete');
    }

    function finishKeyword(response) {
        results.push({
            keyword: keywordQueue[keywordIndex],
            postId: currentPostId
        });

        if (keywordIndex + 1 < keywordQueue.length) {
            keywordIndex += 1;
            currentPostId = 0;
            currentContext = {};
            pendingNextStep = '';
            updateStatus('Chuyển sang từ khóa ' + (keywordIndex + 1) + '/' + keywordQueue.length + ': ' + keywordQueue[keywordIndex]);
            runStep('start', {});
            return;
        }

        showComplete(response);
    }

    function runStep(step, context) {
        lastRequestStep = step;
        lastRequestContext = context || {};
        isProcessing = true;
        studioState = 'processing';
        setControlsDisabled(true);
        showView('workflow');
        $processingPanel.removeClass('is-error');
        $errorActions.prop('hidden', true);
        setActiveStep(getStepFromRequest(step));

        const stepMessages = {
            start: 'Đang phân tích Search Intent...',
            search_intent: 'Đang phân tích Search Intent...',
            outline: 'Đang xây dựng Outline...',
            content: 'Đang viết nội dung hoàn chỉnh...',
            seo: 'Đang tối ưu SEO Metadata...',
            image: 'Đang tạo ảnh đại diện và lưu Draft...',
            finalize: 'Đang lưu nội dung vào Draft...'
        };
        updateStatus(stepMessages[step] || 'Đang xử lý...');

        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            data: {
                action: 'azevent_generate_content',
                nonce: azevent_seo.nonce,
                keyword: keywordQueue[keywordIndex],
                language: language,
                post_id: currentPostId,
                mode: mode,
                regenerate_image: mode === 'create' ? '1' : '0',
                step: step,
                context: JSON.stringify(context || {})
            }
        }).done(function(response) {
            if (!response.success) {
                const errorPostId = parseInt(response.data && response.data.post_id ? response.data.post_id : 0, 10) || 0;
                if (errorPostId > 0) {
                    currentPostId = errorPostId;
                }
                if (response.data && response.data.context) {
                    lastRequestContext = response.data.context;
                }
                showError(response.data && response.data.message ? response.data.message : 'Không thể xử lý từ khóa.');
                return;
            }

            const responseContext = response.data.context || context || {};
            const responsePostId = parseInt(response.data.post_id || responseContext.post_id || currentPostId, 10) || 0;
            if (responsePostId > 0) {
                currentPostId = responsePostId;
            }

            if (response.data.status === 'completed') {
                finishKeyword(response);
                return;
            }

            updateStatus(response.data.message || 'Đã hoàn tất bước hiện tại.');
            if (response.data.next_step === 'image' || response.data.next_step === 'finalize') {
                showReview(responseContext, response.data.next_step);
                return;
            }

            runStep(response.data.next_step, responseContext);
        }).fail(function(xhr) {
            const responseMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'Mất kết nối khi xử lý từ khóa ' + keywordQueue[keywordIndex] + '.';
            showError(responseMessage);
        });
    }

    function stopQueuePolling() {
        if (queuePollTimer) {
            window.clearInterval(queuePollTimer);
            queuePollTimer = null;
        }
    }

    function startQueuePolling() {
        stopQueuePolling();
        queuePollTimer = window.setInterval(function() {
            if ($modal.attr('aria-hidden') === 'false' && !$queueView.prop('hidden')) {
                loadQueue(true);
            }
        }, 5000);
    }

    function showQueueNotice(message, isError) {
        if (!message) {
            $queueNotice.prop('hidden', true).removeClass('is-error').text('');
            return;
        }
        $queueNotice.prop('hidden', false).toggleClass('is-error', !!isError).text(message);
    }

    function renderQueue(data) {
        const jobs = data.jobs || [];
        const counts = data.counts || {};
        const statusLabels = {
            pending: 'Đang chờ',
            processing: 'Đang chạy',
            completed: 'Hoàn tất',
            failed: 'Lỗi'
        };
        const stepLabels = {
            start: 'Search Intent',
            outline: 'Outline',
            content: 'Content',
            seo: 'SEO Metadata',
            image: 'Tạo ảnh',
            finalize: 'Lưu Draft',
            completed: 'Đã hoàn tất'
        };

        $('#azevent-count-pending').text(counts.pending || 0);
        $('#azevent-count-processing').text(counts.processing || 0);
        $('#azevent-count-completed').text(counts.completed || 0);
        $('#azevent-count-failed').text(counts.failed || 0);
        $queueRows.empty();
        $queueEmpty.prop('hidden', jobs.length > 0);

        jobs.forEach(function(job) {
            const $row = $('<tr>');
            const $keywordCell = $('<td>');
            $('<span class="azevent-queue-keyword">').text(job.keyword).appendTo($keywordCell);
            if (job.error) {
                $('<span class="azevent-job-error">').text(job.error).appendTo($keywordCell);
            }

            const $status = $('<span class="azevent-status-pill">')
                .addClass('is-' + job.status)
                .text(statusLabels[job.status] || job.status);
            const $actionCell = $('<td>');

            if (job.status === 'completed' && job.post_url) {
                $('<a class="button azevent-job-action">')
                    .attr('href', job.post_url)
                    .text('Mở Draft')
                    .appendTo($actionCell);
            } else if (job.status === 'failed') {
                $('<button type="button" class="button azevent-job-action azevent-retry-job">')
                    .attr('data-job-id', job.id)
                    .text('Thử lại')
                    .appendTo($actionCell);
            } else {
                $('<span>').text('—').appendTo($actionCell);
            }

            $row.append(
                $keywordCell,
                $('<td>').append($status),
                $('<td>').text(stepLabels[job.step] || job.step),
                $('<td>').text(job.updated_at || job.created_at || ''),
                $actionCell
            );
            $queueRows.append($row);
        });
    }

    function loadQueue(silent) {
        if (queueLoading) {
            return;
        }
        queueLoading = true;

        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            data: {
                action: 'azevent_get_background_jobs',
                nonce: azevent_seo.nonce
            }
        }).done(function(response) {
            if (!response.success) {
                showQueueNotice(response.data && response.data.message ? response.data.message : 'Không thể tải hàng đợi.', true);
                return;
            }
            renderQueue(response.data || {});
            if (!silent) {
                showQueueNotice('', false);
            }
        }).fail(function() {
            if (!silent) {
                showQueueNotice('Không thể kết nối để tải trạng thái hàng đợi.', true);
            }
        }).always(function() {
            queueLoading = false;
        });
    }

    function showQueue(message) {
        studioState = 'queue';
        isProcessing = false;
        showView('queue');
        showQueueNotice(message || '', false);
        loadQueue(!!message);
        startQueuePolling();
    }

    function enqueueBackgroundJobs() {
        isProcessing = true;
        setControlsDisabled(true);
        $startButton.text('Đang thêm vào hàng đợi...');

        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            data: {
                action: 'azevent_enqueue_background_jobs',
                nonce: azevent_seo.nonce,
                keywords: keywordQueue
            }
        }).done(function(response) {
            isProcessing = false;
            setControlsDisabled(false);
            syncModeHelp();
            if (!response.success) {
                $keywordHelp.text(response.data && response.data.message ? response.data.message : 'Không thể thêm Job.').css('color', '#b91c1c');
                return;
            }
            $keywords.val('');
            showQueue(response.data.message || 'Đã thêm Job vào hàng đợi.');
        }).fail(function() {
            isProcessing = false;
            setControlsDisabled(false);
            syncModeHelp();
            $keywordHelp.text('Mất kết nối khi thêm Job vào hàng đợi.').css('color', '#b91c1c');
        });
    }

    $openButton.on('click', openModal);

    $openQueueButton.on('click', function() {
        openModal();
        showQueue();
    });

    $('[data-azevent-close]').on('click', closeModal);

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && $modal.attr('aria-hidden') === 'false') {
            closeModal();
        }
    });

    $('input[name="azevent_mode"]').on('change', syncModeHelp);
    syncModeHelp();

    $startButton.on('click', function() {
        keywordQueue = getKeywords();
        mode = getMode();
        language = azevent_seo.default_language || 'Vietnamese';

        if (!keywordQueue.length) {
            $keywords.trigger('focus');
            $keywordHelp.text('Vui lòng nhập ít nhất một từ khóa.').css('color', '#b91c1c');
            return;
        }

        if (mode === 'rewrite' && keywordQueue.length > 1) {
            $keywords.trigger('focus');
            $keywordHelp.text('Chế độ viết lại chỉ nhận một từ khóa.').css('color', '#b91c1c');
            return;
        }

        if (mode === 'background') {
            $keywordHelp.css('color', '');
            enqueueBackgroundJobs();
            return;
        }

        if (mode === 'rewrite' && (parseInt(azevent_seo.post_id, 10) || 0) <= 0) {
            $keywords.trigger('focus');
            $keywordHelp.text('Cần lưu bài viết trước khi sử dụng chế độ viết lại.').css('color', '#b91c1c');
            return;
        }

        $keywordHelp.css('color', '');
        keywordIndex = 0;
        results = [];
        currentPostId = mode === 'rewrite' ? (parseInt(azevent_seo.post_id, 10) || 0) : 0;
        currentContext = {};
        $log.empty();
        runStep('start', {});
    });

    $regenerateButton.on('click', function() {
        if (!currentContext || !currentContext.outline) {
            showError('Không tìm thấy Outline để tạo lại nội dung.');
            return;
        }
        runStep('content', currentContext);
    });

    $regenerateImage.on('change', function() {
        $approveButton.html(
            $(this).is(':checked')
                ? 'Duyệt, tạo ảnh và lưu Draft <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>'
                : 'Duyệt và lưu Draft <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>'
        );
    });

    $approveButton.on('click', function() {
        if (!currentContext || !currentContext.seo) {
            showError('Không tìm thấy dữ liệu SEO để hoàn tất bài viết.');
            return;
        }
        pendingNextStep = $regenerateImage.is(':checked') ? 'image' : 'finalize';
        runStep(pendingNextStep, currentContext);
    });

    $restartButton.on('click', function() {
        resetStudio();
        syncModeHelp();
    });

    $retryButton.on('click', function() {
        if (!lastRequestStep) {
            resetStudio();
            return;
        }
        runStep(lastRequestStep, lastRequestContext);
    });

    $('#azevent-refresh-queue').on('click', function() {
        loadQueue(false);
    });

    $('#azevent-add-queue-jobs').on('click', function() {
        stopQueuePolling();
        $('input[name="azevent_mode"][value="background"]').prop('checked', true).trigger('change');
        showView('setup');
        $keywords.trigger('focus');
    });

    $queueRows.on('click', '.azevent-retry-job', function() {
        const $button = $(this);
        const jobId = parseInt($button.attr('data-job-id'), 10) || 0;
        if (!jobId) {
            return;
        }

        $button.prop('disabled', true).text('Đang thử lại...');
        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            data: {
                action: 'azevent_retry_background_job',
                nonce: azevent_seo.nonce,
                job_id: jobId
            }
        }).done(function(response) {
            showQueueNotice(
                response.data && response.data.message ? response.data.message : 'Đã cập nhật Job.',
                !response.success
            );
            loadQueue(true);
        }).fail(function() {
            showQueueNotice('Không thể thử lại Job.', true);
        }).always(function() {
            $button.prop('disabled', false).text('Thử lại');
        });
    });
});
