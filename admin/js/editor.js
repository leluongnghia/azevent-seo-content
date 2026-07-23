jQuery(function($) {
    const $modal = $('#azevent-studio-modal');
    const $openButton = $('#azevent-open-studio');
    const $setupView = $('#azevent-setup-view');
    const $workflowView = $('#azevent-workflow-view');
    const $intentReviewView = $('#azevent-intent-review-view');
    const $outlineReviewView = $('#azevent-outline-review-view');
    const $contentReviewView = $('#azevent-content-review-view');
    const $reviewView = $('#azevent-review-view');
    const $completeView = $('#azevent-complete-view');
    const $queueView = $('#azevent-queue-view');
    const $startButton = $('#azevent-start-btn');
    const $continueOutlineButton = $('#azevent-continue-outline-btn');
    const $rerunIntentButton = $('#azevent-rerun-intent-btn');
    const $outlineResult = $('#azevent-outline-result-text');
    const $continueContentButton = $('#azevent-continue-content-btn');
    const $rerunOutlineButton = $('#azevent-rerun-outline-btn');
    const $continueSeoButton = $('#azevent-continue-seo-btn');
    const $regenerateSeoButton = $('#azevent-regenerate-seo-btn');
    const $openQueueButton = $('#azevent-open-queue');
    const $approveButton = $('#azevent-approve-btn');
    const $regenerateButton = $('#azevent-regenerate-content-btn');
    const $restartButton = $('#azevent-restart-btn');
    const $retryButton = $('#azevent-retry-btn');
    const $errorBackButton = $('#azevent-error-back-btn');
    const $errorActions = $('#azevent-error-actions');
    const $workflowStepper = $('#azevent-workflow-stepper');
    const $processingPanel = $('#azevent-processing-panel');
    const $statusText = $('#azevent-status-text');
    const $log = $('#azevent-log');
    const $keywords = $('#azevent-keywords');
    const $keywordHelp = $('#azevent-keyword-help');
    const $optimizeAiOverviewGeo = $('#azevent-optimize-ai-overview-geo');
    const $regenerateImage = $('#azevent-regenerate-image');
    const $contentReviewFrame = $('#azevent-content-review-frame');
    const $intentResult = $('#azevent-intent-result-text');
    const $resumeCard = $('#azevent-resume-card');
    const $resumeKeyword = $('#azevent-resume-keyword');
    const $resumeStep = $('#azevent-resume-step');
    const $resumeTime = $('#azevent-resume-time');
    const $resumeButton = $('#azevent-resume-session');
    const $discardSessionButton = $('#azevent-discard-session');
    const $queueRows = $('#azevent-queue-rows');
    const $queueEmpty = $('#azevent-queue-empty');
    const $queueNotice = $('#azevent-queue-notice');
    const $sectionImagesResult = $('#azevent-section-images-result');
    const stepOrder = ['intent', 'outline', 'content', 'seo', 'finish'];

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
    let stepRecoveryTimer = null;
    let stepRecoveryStartedAt = 0;
    let activeStepRecoveryId = '';
    let sessionId = '';
    let savedCheckpoint = null;
    let checkpointLoading = false;
    let checkpointEstablished = false;
    let requestedResumeJobId = parseInt(azevent_seo.resume_job_id, 10) || 0;

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

    function getEditorContent() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
            return tinyMCE.get('content').getContent() || '';
        }
        return $('#content').val() || '';
    }

    function hasReadableContent(content) {
        const parsed = new DOMParser().parseFromString(content || '', 'text/html');
        return (parsed.body.textContent || '').trim() !== '';
    }

    function showView(view) {
        const workflowViews = ['workflow', 'intent-review', 'outline-review', 'content-review', 'review'];
        $setupView.prop('hidden', view !== 'setup');
        $workflowView.prop('hidden', view !== 'workflow');
        $intentReviewView.prop('hidden', view !== 'intent-review');
        $outlineReviewView.prop('hidden', view !== 'outline-review');
        $contentReviewView.prop('hidden', view !== 'content-review');
        $reviewView.prop('hidden', view !== 'review');
        $completeView.prop('hidden', view !== 'complete');
        $queueView.prop('hidden', view !== 'queue');
        $workflowStepper.prop('hidden', workflowViews.indexOf(view) === -1);
    }

    function getCheckpointStepLabel(step) {
        const labels = {
            start: 'Search Intent',
            search_intent: 'Search Intent',
            outline: 'Outline',
            content: 'Content',
            seo: 'SEO Metadata',
            section_images: 'Ảnh minh họa H2',
            image: 'Tạo ảnh',
            finalize: 'Lưu Draft'
        };
        return labels[step] || step || 'Chưa xác định';
    }

    function renderBrowserCheckpoint(checkpoint) {
        savedCheckpoint = checkpoint && checkpoint.session_id ? checkpoint : null;
        if (!savedCheckpoint) {
            $resumeCard.prop('hidden', true);
            return;
        }

        const activeStep = savedCheckpoint.status === 'paused'
            ? savedCheckpoint.next_step
            : savedCheckpoint.current_step;
        const statusLabel = savedCheckpoint.status === 'processing'
            ? 'Đang xử lý tại ' + getCheckpointStepLabel(activeStep)
            : savedCheckpoint.status === 'failed'
                ? 'Đang lỗi tại ' + getCheckpointStepLabel(activeStep)
                : 'Sẵn sàng tiếp tục ' + getCheckpointStepLabel(activeStep);
        const updatedAt = parseInt(savedCheckpoint.updated_at, 10) || 0;

        $resumeKeyword.text(savedCheckpoint.keyword || 'Phiên nội dung chưa hoàn tất');
        $resumeStep.text(statusLabel);
        $resumeTime.text(updatedAt ? new Date(updatedAt * 1000).toLocaleString() : 'Vừa lưu');
        $resumeCard.prop('hidden', false);
    }

    function loadBrowserCheckpoint() {
        if (checkpointLoading || studioState !== 'idle') {
            return;
        }
        checkpointLoading = true;
        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            timeout: 20000,
            data: {
                action: 'azevent_get_browser_checkpoint',
                nonce: azevent_seo.nonce,
                job_id: requestedResumeJobId
            }
        }).done(function(response) {
            if (response.success) {
                const checkpoint = response.data ? response.data.checkpoint : null;
                renderBrowserCheckpoint(checkpoint);
                if (checkpoint && requestedResumeJobId > 0) {
                    requestedResumeJobId = 0;
                    resumeBrowserCheckpoint();
                }
            }
        }).always(function() {
            checkpointLoading = false;
        });
    }

    function openModal() {
        returnFocus = document.activeElement;
        if (studioState === 'complete') {
            resetStudio();
        }
        $modal.attr('aria-hidden', 'false');
        $('body').addClass('azevent-modal-open');
        loadBrowserCheckpoint();
        window.setTimeout(function() {
            $('#azevent-modal-close').trigger('focus');
        }, 20);
    }

    function closeModal() {
        if (isProcessing) {
            updateStatus('Đã thu gọn Content Studio. Quy trình và checkpoint vẫn tiếp tục chạy.', false);
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

    function restoreSplitHistory(context) {
        const splitState = context && context.content_split ? context.content_split : {};
        const history = splitState.history && typeof splitState.history === 'object' ? splitState.history : {};
        Object.keys(history).sort(function(a, b) { return parseInt(a, 10) - parseInt(b, 10); }).forEach(function(index) {
            const item = history[index] || {};
            const tokens = parseInt(item.input_tokens || 0, 10) + '/' + parseInt(item.output_tokens || 0, 10) + ' token';
            updateStatus(
                'Đã khôi phục H2 ' + (parseInt(index, 10) + 1) + ': ' + (item.title || 'Không có tiêu đề')
                + ' · ' + (item.provider || 'AI') + (item.model ? ' (' + item.model + ')' : '')
                + ' · ' + parseFloat(item.duration_seconds || 0).toFixed(1) + ' giây · ' + tokens
            );
        });
    }

    function setActiveStep(step) {
        const activeIndex = stepOrder.indexOf(step);
        $('.azevent-stepper li').each(function(index) {
            $(this)
                .toggleClass('is-complete', activeIndex >= 0 && index < activeIndex)
                .toggleClass('is-active', index === activeIndex);
        });
        updateStepNavigation();
    }

    function getAvailableReviewSteps() {
        return {
            intent: !!currentContext.search_intent,
            outline: !!currentContext.outline,
            content: !!currentContext.content,
            seo: !!currentContext.seo
        };
    }

    function updateStepNavigation() {
        const availableSteps = getAvailableReviewSteps();
        $('.azevent-stepper li').each(function() {
            const $step = $(this);
            const step = $step.attr('data-step');
            const isAvailable = !!availableSteps[step] && !isProcessing;
            $step
                .toggleClass('is-available', isAvailable)
                .toggleClass('has-result', !!availableSteps[step])
                .attr('aria-disabled', isAvailable ? 'false' : 'true')
                .attr('tabindex', isAvailable ? '0' : '-1')
                .attr('role', isAvailable ? 'button' : 'listitem');
        });
    }

    function syncCurrentReviewEdits() {
        if (studioState === 'intent-review' && !$intentResult.prop('readonly')) {
            currentContext.search_intent = $intentResult.val().trim();
        }
        if (studioState === 'outline-review' && !$outlineResult.prop('readonly')) {
            currentContext.outline = $outlineResult.val().trim();
        }
    }

    function getFinalizationStep() {
        if (['section_images', 'image', 'finalize'].indexOf(pendingNextStep) !== -1) {
            return pendingNextStep;
        }
        if (['section_images', 'image', 'finalize'].indexOf(lastRequestStep) !== -1) {
            return lastRequestStep;
        }
        return mode === 'create' ? 'image' : 'finalize';
    }

    function showSavedStep(step) {
        if (isProcessing) {
            return false;
        }
        syncCurrentReviewEdits();
        if (step === 'intent' && currentContext.search_intent) {
            showIntentReview(currentContext);
            return true;
        }
        if (step === 'outline' && currentContext.outline) {
            showOutlineReview(currentContext);
            return true;
        }
        if (step === 'content' && currentContext.content) {
            showContentReview(currentContext);
            return true;
        }
        if (step === 'seo' && currentContext.seo) {
            showReview(currentContext, getFinalizationStep());
            return true;
        }
        return false;
    }

    function getPreviousSavedStep(requestStep) {
        const activeStep = getStepFromRequest(requestStep);
        const activeIndex = stepOrder.indexOf(activeStep);
        const availableSteps = getAvailableReviewSteps();
        for (let index = activeIndex - 1; index >= 0; index -= 1) {
            if (availableSteps[stepOrder[index]]) {
                return stepOrder[index];
            }
        }
        for (let index = stepOrder.length - 1; index >= 0; index -= 1) {
            if (availableSteps[stepOrder[index]]) {
                return stepOrder[index];
            }
        }
        return '';
    }

    function updateErrorNavigation() {
        const previousStep = getPreviousSavedStep(lastRequestStep);
        const labels = {
            intent: 'Search Intent',
            outline: 'Outline',
            content: 'Content',
            seo: 'SEO'
        };
        $errorBackButton
            .prop('hidden', !previousStep)
            .attr('data-step', previousStep)
            .find('span:last')
            .text(previousStep ? 'Xem lại ' + labels[previousStep] : 'Xem lại bước trước');
        $retryButton.text('Thử lại bước ' + getCheckpointStepLabel(lastRequestStep));
    }

    function getStepFromRequest(step) {
        if (step === 'start' || step === 'search_intent') {
            return 'intent';
        }
        if (step === 'section_images' || step === 'image' || step === 'finalize') {
            return 'finish';
        }
        return step;
    }

    function setControlsDisabled(disabled) {
        $startButton.prop('disabled', disabled);
        $approveButton.prop('disabled', disabled);
        $regenerateButton.prop('disabled', disabled);
        $continueOutlineButton.prop('disabled', disabled);
        $rerunIntentButton.prop('disabled', disabled);
        $continueContentButton.prop('disabled', disabled);
        $rerunOutlineButton.prop('disabled', disabled);
        $continueSeoButton.prop('disabled', disabled);
        $regenerateSeoButton.prop('disabled', disabled);
        $errorBackButton.prop('disabled', disabled);
        updateStepNavigation();
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
        sessionId = '';
        savedCheckpoint = null;
        checkpointEstablished = false;
        $resumeCard.prop('hidden', true);
        stopStepRecovery();
        stopQueuePolling();
        $log.empty();
        $intentResult.val('');
        $outlineResult.val('');
        $processingPanel.removeClass('is-error');
        $errorActions.prop('hidden', true);
        $errorBackButton.prop('hidden', true).removeAttr('data-step');
        $retryButton.text('Thử lại bước này');
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
        updateErrorNavigation();
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
        setActiveStep('seo');
        showView('review');

        const seo = currentContext.seo || {};
        $('#azevent-review-title').text(seo.title || keywordQueue[keywordIndex]);
        $('#azevent-review-meta').text(seo.meta || '');
        $('#azevent-review-slug').text(seo.slug || '');
        $('#azevent-review-image-prompt').text(seo.image_prompt || '');
        $('#azevent-review-image-alt').text(seo.image_alt || '');

        const shouldCreateImage = mode === 'create' || pendingNextStep === 'image';
        $regenerateImage.prop('checked', shouldCreateImage);
        $regenerateImage.prop('disabled', mode === 'create');
        $approveButton.html(
            shouldCreateImage
                ? 'Duyệt, tạo ảnh và lưu Draft <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>'
                : 'Duyệt và lưu Draft <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>'
        );
    }

    function showIntentReview(context) {
        studioState = 'intent-review';
        isProcessing = false;
        currentContext = context || {};
        setControlsDisabled(false);
        setActiveStep('intent');
        $intentResult.val(currentContext.search_intent || '');
        $intentResult.prop('readonly', !!currentContext.outline);
        $continueOutlineButton.html(
            currentContext.outline
                ? 'Xem Outline đã tạo <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'
                : 'Tiếp tục tạo Outline <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'
        );
        showView('intent-review');
        window.setTimeout(function() {
            $intentResult.trigger('focus');
        }, 20);
    }

    function showOutlineReview(context) {
        studioState = 'outline-review';
        isProcessing = false;
        currentContext = context || {};
        setControlsDisabled(false);
        setActiveStep('outline');
        $outlineResult.val(currentContext.outline || '');
        $outlineResult.prop('readonly', !!currentContext.content);
        $continueContentButton.html(
            currentContext.content
                ? 'Xem Content đã tạo <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'
                : 'Tiếp tục tạo Content <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'
        );
        showView('outline-review');
    }

    function showContentReview(context) {
        studioState = 'content-review';
        isProcessing = false;
        currentContext = context || {};
        setControlsDisabled(false);
        setActiveStep('content');
        $contentReviewFrame.attr('srcdoc', buildPreviewDocument(currentContext.content || ''));
        $continueSeoButton.html(
            currentContext.seo
                ? 'Xem SEO đã tạo <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'
                : 'Tiếp tục tạo SEO <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'
        );
        showView('content-review');
    }

    function clearContextAfter(step) {
        const downstream = {
            intent: ['search_intent', 'outline', 'content', 'seo', 'content_split', 'section_images'],
            outline: ['outline', 'content', 'seo', 'content_split', 'section_images'],
            content: ['content', 'seo', 'section_images'],
            seo: ['seo', 'section_images']
        };
        (downstream[step] || []).forEach(function(key) {
            delete currentContext[key];
        });
    }

    function createRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'azevent-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    function stopStepRecovery() {
        if (stepRecoveryTimer) {
            window.clearTimeout(stepRecoveryTimer);
            stepRecoveryTimer = null;
        }
        stepRecoveryStartedAt = 0;
        activeStepRecoveryId = '';
    }

    function handleStepResponse(response, step, context) {
        stopStepRecovery();
        checkpointEstablished = true;

        if (!response || !response.success) {
            const errorPostId = parseInt(response && response.data && response.data.post_id ? response.data.post_id : 0, 10) || 0;
            if (errorPostId > 0) {
                currentPostId = errorPostId;
            }
            if (response && response.data && response.data.context) {
                currentContext = $.extend(true, {}, currentContext, response.data.context);
                lastRequestContext = currentContext;
            }
            showError(response && response.data && response.data.message ? response.data.message : 'Không thể xử lý từ khóa.');
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
        const splitState = responseContext.content_split || {};
        if (response.data.next_step === 'content' && splitState.enabled && !splitState.completed) {
            runStep('content', responseContext);
            return;
        }
        if (response.data.next_step === 'section_images') {
            if (azevent_seo.auto_advance || step === 'section_images') {
                runStep('section_images', responseContext);
            } else {
                showReview(responseContext, 'section_images');
            }
            return;
        }
        if (!azevent_seo.auto_advance) {
            if (response.data.next_step === 'outline') {
                showIntentReview(responseContext);
                return;
            }
            if (response.data.next_step === 'content') {
                showOutlineReview(responseContext);
                return;
            }
            if (response.data.next_step === 'seo') {
                showContentReview(responseContext);
                return;
            }
        }
        if ((response.data.next_step === 'image' || response.data.next_step === 'finalize') && step === 'section_images') {
            runStep(response.data.next_step, responseContext);
            return;
        }
        if (response.data.next_step === 'image' || response.data.next_step === 'finalize') {
            if (azevent_seo.auto_advance) {
                runStep(response.data.next_step, responseContext);
                return;
            }
            showReview(responseContext, response.data.next_step);
            return;
        }

        runStep(response.data.next_step, responseContext);
    }

    function startStepRecovery(requestId, step, context) {
        stopStepRecovery();
        stepRecoveryStartedAt = Date.now();
        activeStepRecoveryId = requestId;
        isProcessing = true;
        studioState = 'processing';
        setControlsDisabled(true);
        showView('workflow');
        $processingPanel.removeClass('is-error');
        $errorActions.prop('hidden', true);
        updateStatus('Kết nối dài đã chuyển sang chế độ chờ nền. AI vẫn đang xử lý ' + getStepFromRequest(step) + '...');

        function pollStepStatus() {
            if (activeStepRecoveryId !== requestId) {
                return;
            }
            $.ajax({
                url: azevent_seo.ajax_url,
                type: 'POST',
                timeout: 20000,
                data: {
                    action: 'azevent_get_browser_step_status',
                    nonce: azevent_seo.nonce,
                    request_id: requestId
                }
            }).done(function(response) {
                if (activeStepRecoveryId !== requestId) {
                    return;
                }
                if (response.success && response.data && response.data.status === 'completed') {
                    handleStepResponse(response.data.payload, step, context);
                    return;
                }
                if (response.success && response.data && response.data.status === 'failed') {
                    handleStepResponse(response.data.payload, step, context);
                    return;
                }
                scheduleNextPoll();
            }).fail(function() {
                if (activeStepRecoveryId !== requestId) {
                    return;
                }
                scheduleNextPoll();
            });
        }

        function scheduleNextPoll() {
            if (activeStepRecoveryId !== requestId) {
                return;
            }
            if (Date.now() - stepRecoveryStartedAt >= 20 * 60 * 1000) {
                stopStepRecovery();
                showError('Server chưa trả kết quả sau 20 phút. Hãy kiểm tra timeout PHP/Nginx rồi thử lại bước này.');
                return;
            }
            stepRecoveryTimer = window.setTimeout(pollStepStatus, 4000);
        }

        pollStepStatus();
    }

    function resumeBrowserCheckpoint() {
        if (!savedCheckpoint) {
            return;
        }

        const checkpoint = savedCheckpoint;
        sessionId = checkpoint.session_id;
        checkpointEstablished = true;
        mode = checkpoint.mode === 'rewrite' ? 'rewrite' : 'create';
        language = checkpoint.language || azevent_seo.default_language || 'Vietnamese';
        keywordQueue = [checkpoint.keyword || 'Nội dung AI'];
        keywordIndex = 0;
        currentPostId = parseInt(checkpoint.post_id, 10) || 0;
        currentContext = checkpoint.context && typeof checkpoint.context === 'object' ? checkpoint.context : {};
        $optimizeAiOverviewGeo.prop('checked', !!currentContext.optimize_ai_overview_geo);
        lastRequestStep = checkpoint.current_step || checkpoint.next_step || 'start';
        lastRequestContext = currentContext;
        results = [];
        pendingNextStep = ['section_images', 'image', 'finalize'].indexOf(lastRequestStep) !== -1 ? lastRequestStep : '';

        $('input[name="azevent_mode"][value="' + mode + '"]').prop('checked', true).trigger('change');
        $keywords.val(keywordQueue[0]);
        $resumeCard.prop('hidden', true);
        $log.empty();
        updateStatus('Đã khôi phục checkpoint cho từ khóa ' + keywordQueue[0] + '.');
        restoreSplitHistory(currentContext);

        if (checkpoint.status === 'processing') {
            setActiveStep(getStepFromRequest(lastRequestStep));
            if (checkpoint.request_id) {
                startStepRecovery(checkpoint.request_id, lastRequestStep, currentContext);
            } else {
                showError('Phiên trước dừng khi đang xử lý ' + getCheckpointStepLabel(lastRequestStep) + '. Bấm Thử lại bước này để tiếp tục.');
            }
            return;
        }

        if (checkpoint.status === 'failed') {
            setActiveStep(getStepFromRequest(lastRequestStep));
            showError(checkpoint.error || 'Phiên trước bị dừng tại ' + getCheckpointStepLabel(lastRequestStep) + '.');
            return;
        }

        const nextStep = checkpoint.next_step || lastRequestStep;
        const splitState = currentContext.content_split || {};
        if (nextStep === 'content' && splitState.enabled && !splitState.completed) {
            runStep('content', currentContext);
            return;
        }
        if (nextStep === 'section_images') {
            if (azevent_seo.auto_advance || (currentContext.section_images && currentContext.section_images.items)) {
                runStep('section_images', currentContext);
            } else {
                showReview(currentContext, 'section_images');
            }
            return;
        }
        if (nextStep === 'outline' && currentContext.search_intent && !azevent_seo.auto_advance) {
            showIntentReview(currentContext);
            return;
        }
        if (nextStep === 'content' && currentContext.outline && !azevent_seo.auto_advance) {
            showOutlineReview(currentContext);
            return;
        }
        if (nextStep === 'seo' && currentContext.content && !azevent_seo.auto_advance) {
            showContentReview(currentContext);
            return;
        }
        if (nextStep === 'image' || nextStep === 'finalize') {
            if (azevent_seo.auto_advance) {
                runStep(nextStep, currentContext);
                return;
            }
            showReview(currentContext, nextStep);
            return;
        }
        if (!nextStep) {
            showError('Checkpoint không xác định được bước cần tiếp tục.');
            return;
        }
        runStep(nextStep, currentContext);
    }

    function discardBrowserCheckpoint() {
        if (!savedCheckpoint) {
            return;
        }
        const discardedSessionId = savedCheckpoint.session_id;
        $resumeButton.prop('disabled', true);
        $discardSessionButton.prop('disabled', true);
        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            timeout: 20000,
            data: {
                action: 'azevent_clear_browser_checkpoint',
                nonce: azevent_seo.nonce,
                session_id: discardedSessionId
            }
        }).done(function(response) {
            if (response.success) {
                savedCheckpoint = null;
                checkpointEstablished = false;
                $resumeCard.prop('hidden', true);
            }
        }).always(function() {
            $resumeButton.prop('disabled', false);
            $discardSessionButton.prop('disabled', false);
        });
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
        renderSectionImages(lastResponse && lastResponse.data ? lastResponse.data.section_images : null, currentPostId);
        showView('complete');
    }

    function renderSectionImages(state, postId) {
        $sectionImagesResult.empty().prop('hidden', true);
        const items = state && Array.isArray(state.items) ? state.items : [];
        if (!items.length) {
            return;
        }
        $('<h4>').text('Ảnh minh họa theo H2').appendTo($sectionImagesResult);
        const $grid = $('<div class="azevent-section-images-grid">').appendTo($sectionImagesResult);
        items.forEach(function(item) {
            const $card = $('<div class="azevent-section-image-card">').attr('data-section-key', item.key || '');
            const attachment = item.attachment || {};
            if (item.status === 'created' && attachment.url) {
                $('<img>').attr({ src: attachment.url, alt: item.alt || item.title || '' }).appendTo($card);
            } else {
                $('<div class="azevent-section-image-error">').text(item.error || 'Ảnh này đã được bỏ qua.').appendTo($card);
            }
            $('<strong>').text(item.title || 'H2').appendTo($card);
            $('<span class="azevent-section-image-status">')
                .addClass('is-' + (item.status || 'pending'))
                .text(item.status === 'created' ? 'Đã tạo' : (item.status === 'skipped' ? 'Đã bỏ qua' : 'Đang chờ'))
                .appendTo($card);
            $('<p class="azevent-section-image-prompt">')
                .text('Prompt: ' + (item.prompt_excerpt || item.prompt || 'Chưa có'))
                .appendTo($card);
            $('<small class="azevent-section-image-meta">')
                .text([
                    item.attachment_id || attachment.id ? 'Attachment #' + (item.attachment_id || attachment.id) : '',
                    item.position && item.position.label ? item.position.label : 'Sau đoạn mở đầu của H2',
                    item.attempts ? item.attempts + ' lần gọi' : ''
                ].filter(Boolean).join(' · '))
                .appendTo($card);
            $('<button type="button" class="button azevent-regenerate-section-image">')
                .attr({ 'data-post-id': postId, 'data-section-key': item.key || '' })
                .text(item.status === 'created' ? 'Tạo lại ảnh' : 'Thử tạo ảnh')
                .appendTo($card);
            $card.appendTo($grid);
        });
        $sectionImagesResult.prop('hidden', false);
    }

    $sectionImagesResult.on('click', '.azevent-regenerate-section-image', function() {
        const $button = $(this);
        if (!window.confirm('Tạo ảnh mới và thay đúng ảnh H2 này? Ảnh cũ vẫn được giữ trong Media Library.')) {
            return;
        }
        $button.prop('disabled', true).text('Đang tạo lại...');
        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            timeout: 1000000,
            data: {
                action: 'azevent_regenerate_section_image',
                nonce: azevent_seo.section_image_nonce,
                post_id: $button.data('post-id'),
                section_key: $button.data('section-key')
            }
        }).done(function(response) {
            if (!response.success) {
                showError(response.data && response.data.message ? response.data.message : 'Không thể tạo lại ảnh H2.');
                return;
            }
            renderSectionImages(response.data.state, parseInt($button.data('post-id'), 10) || currentPostId);
        }).fail(function(xhr) {
            window.alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Không thể tạo lại ảnh H2.');
        }).always(function() {
            $button.prop('disabled', false).text('Tạo lại ảnh');
        });
    });

    function finishKeyword(response) {
        results.push({
            keyword: keywordQueue[keywordIndex],
            postId: currentPostId
        });

        if (keywordIndex + 1 < keywordQueue.length) {
            const geoEnabledForQueue = !!currentContext.optimize_ai_overview_geo;
            keywordIndex += 1;
            currentPostId = 0;
            currentContext = {
                optimize_ai_overview_geo: geoEnabledForQueue
            };
            pendingNextStep = '';
            updateStatus('Chuyển sang từ khóa ' + (keywordIndex + 1) + '/' + keywordQueue.length + ': ' + keywordQueue[keywordIndex]);
            runStep('start', currentContext);
            return;
        }

        showComplete(response);
    }

    function runStep(step, context) {
        stopStepRecovery();
        lastRequestStep = step;
        lastRequestContext = context || {};
        isProcessing = true;
        studioState = 'processing';
        setControlsDisabled(true);
        showView('workflow');
        $processingPanel.removeClass('is-error');
        $errorActions.prop('hidden', true);
        setActiveStep(getStepFromRequest(step));

        const splitState = context && context.content_split ? context.content_split : {};
        const splitSections = Array.isArray(splitState.sections) ? splitState.sections : [];
        const splitIndex = parseInt(splitState.current_index || 0, 10);
        const splitTitle = splitSections[splitIndex] && splitSections[splitIndex].title ? ': ' + splitSections[splitIndex].title : '';
        const imageState = context && context.section_images ? context.section_images : {};
        const imageItems = Array.isArray(imageState.items) ? imageState.items : [];
        const imageIndex = parseInt(imageState.current_index || 0, 10);
        const imageTitle = imageItems[imageIndex] && imageItems[imageIndex].title ? ': ' + imageItems[imageIndex].title : '';
        const stepMessages = {
            start: 'Đang phân tích Search Intent...',
            search_intent: 'Đang phân tích Search Intent...',
            outline: 'Đang xây dựng Outline...',
            content: splitState.enabled && !splitState.completed
                ? 'Đang viết H2 ' + (splitIndex + 1) + '/' + splitSections.length + splitTitle + '...'
                : 'Đang viết nội dung hoàn chỉnh...',
            seo: 'Đang tối ưu SEO Metadata...',
            section_images: imageItems.length
                ? 'Đang tạo ảnh H2 ' + (imageIndex + 1) + '/' + imageItems.length + imageTitle + '...'
                : 'Đang lập kế hoạch ảnh minh họa theo H2...',
            image: 'Đang tạo ảnh đại diện và lưu Draft...',
            finalize: 'Đang lưu nội dung vào Draft...'
        };
        updateStatus(stepMessages[step] || 'Đang xử lý...');

        const requestId = createRequestId();
        const shouldReplaceCheckpoint = !checkpointEstablished;

        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            timeout: 110000,
            data: {
                action: 'azevent_generate_content',
                nonce: azevent_seo.nonce,
                request_id: requestId,
                session_id: sessionId,
                replace_checkpoint: shouldReplaceCheckpoint ? '1' : '0',
                keyword: keywordQueue[keywordIndex],
                language: language,
                post_id: currentPostId,
                mode: mode,
                regenerate_image: (mode === 'create' || (context && context.regenerate_image)) ? '1' : '0',
                step: step,
                context: JSON.stringify(context || {})
            }
        }).done(function(response) {
            handleStepResponse(response, step, context);
        }).fail(function(xhr, textStatus) {
            const responseMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'Mất kết nối khi xử lý từ khóa ' + keywordQueue[keywordIndex] + '.';
            const recoverable = textStatus === 'timeout' || xhr.status === 0 || xhr.status === 502 || xhr.status === 504;
            if (recoverable && !(xhr.responseJSON && xhr.responseJSON.data)) {
                startStepRecovery(requestId, step, context);
                return;
            }
            if (xhr.responseJSON && xhr.responseJSON.data) {
                checkpointEstablished = true;
                if (xhr.responseJSON.data.context && typeof xhr.responseJSON.data.context === 'object') {
                    currentContext = $.extend(true, {}, currentContext, xhr.responseJSON.data.context);
                    lastRequestContext = currentContext;
                }
            }
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
            paused: 'Chờ tiếp tục',
            completed: 'Hoàn tất',
            failed: 'Lỗi'
        };
        const stepLabels = {
            start: 'Search Intent',
            outline: 'Outline',
            content: 'Content',
            seo: 'SEO Metadata',
            section_images: 'Ảnh H2',
            image: 'Tạo ảnh',
            finalize: 'Lưu Draft',
            completed: 'Đã hoàn tất'
        };

        $('#azevent-count-pending').text(counts.pending || 0);
        $('#azevent-count-processing').text(counts.processing || 0);
        $('#azevent-count-paused').text(counts.paused || 0);
        $('#azevent-count-completed').text(counts.completed || 0);
        $('#azevent-count-failed').text(counts.failed || 0);
        $queueRows.empty();
        $queueEmpty.prop('hidden', jobs.length > 0);

        jobs.forEach(function(job) {
            const $row = $('<tr>');
            const $keywordCell = $('<td>');
            let destination = null;
            if (job.workflow_type === 'browser' && job.resume_url && job.status !== 'completed') {
                destination = {
                    url: job.resume_url,
                    label: job.status === 'processing' || job.auto_background ? 'Mở tiến trình' : 'Tiếp tục',
                    primary: true
                };
            } else if (job.status === 'completed' && job.post_url) {
                destination = {
                    url: job.post_url,
                    label: 'Mở Draft',
                    primary: false
                };
            }

            const $keyword = destination
                ? $('<a class="azevent-queue-keyword">')
                    .attr({
                        href: destination.url,
                        title: destination.label,
                        'aria-label': destination.label + ': ' + job.keyword
                    })
                : $('<span class="azevent-queue-keyword">');
            $keyword.text(job.keyword).appendTo($keywordCell);
            $('<span class="azevent-job-type">')
                .text(job.workflow_type === 'browser' ? 'Content Studio' : 'Tự động')
                .appendTo($keywordCell);
            if (job.error) {
                $('<span class="azevent-job-error">').text(job.error).appendTo($keywordCell);
            }

            const $status = $('<span class="azevent-status-pill">')
                .addClass('is-' + job.status)
                .text(job.auto_background && job.status === 'paused' ? 'Đang chạy nền' : (statusLabels[job.status] || job.status));
            const $actionCell = $('<td>');

            if (destination) {
                $('<a class="button azevent-job-action">')
                    .toggleClass('button-primary', destination.primary)
                    .attr('href', destination.url)
                    .text(destination.label)
                    .appendTo($actionCell);
            } else if (job.status === 'failed') {
                $('<button type="button" class="button azevent-job-action azevent-retry-job">')
                    .attr('data-job-id', job.id)
                    .text('Thử lại')
                    .appendTo($actionCell);
            } else {
                $('<span>').text('—').appendTo($actionCell);
            }
            if (job.status !== 'processing') {
                $('<button type="button" class="button azevent-job-action azevent-delete-job">')
                    .attr('data-job-id', job.id)
                    .text('Xóa')
                    .appendTo($actionCell);
            }

            $row.append(
                $keywordCell,
                $('<td>').append($status),
                $('<td>').text(job.section_progress || stepLabels[job.step] || job.step),
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
                keywords: keywordQueue,
                optimize_ai_overview_geo: $optimizeAiOverviewGeo.is(':checked') ? '1' : '0'
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

    $resumeButton.on('click', resumeBrowserCheckpoint);
    $discardSessionButton.on('click', discardBrowserCheckpoint);

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

        const initialContext = {
            optimize_ai_overview_geo: $optimizeAiOverviewGeo.is(':checked')
        };
        if (mode === 'rewrite') {
            const editorContent = getEditorContent();
            if (!hasReadableContent(editorContent)) {
                $keywords.trigger('focus');
                $keywordHelp.text('Bài đang mở chưa có nội dung. Hãy nhập hoặc dán nội dung vào Editor trước khi viết lại.').css('color', '#b91c1c');
                return;
            }
            initialContext.existing_post = {
                title: ($('#title').val() || keywordQueue[0]).trim(),
                content: editorContent
            };
        }

        $keywordHelp.css('color', '');
        keywordIndex = 0;
        results = [];
        sessionId = createRequestId();
        savedCheckpoint = null;
        checkpointEstablished = false;
        $resumeCard.prop('hidden', true);
        currentPostId = mode === 'rewrite' ? (parseInt(azevent_seo.post_id, 10) || 0) : 0;
        currentContext = initialContext;
        $log.empty();
        runStep('start', initialContext);
    });

    $regenerateButton.on('click', function() {
        if (!currentContext || !currentContext.outline) {
            showError('Không tìm thấy Outline để tạo lại nội dung.');
            return;
        }
        clearContextAfter('content');
        runStep('content', currentContext);
    });

    $continueOutlineButton.on('click', function() {
        if (currentContext.outline) {
            showOutlineReview(currentContext);
            return;
        }
        const reviewedIntent = $intentResult.val().trim();
        if (!reviewedIntent) {
            $intentResult.trigger('focus');
            return;
        }
        currentContext.search_intent = reviewedIntent;
        delete currentContext.outline;
        delete currentContext.content;
        delete currentContext.seo;
        runStep('outline', currentContext);
    });

    $rerunIntentButton.on('click', function() {
        clearContextAfter('intent');
        runStep('search_intent', currentContext);
    });

    $('#azevent-back-to-intent-btn').on('click', function() {
        showSavedStep('intent');
    });

    $rerunOutlineButton.on('click', function() {
        clearContextAfter('outline');
        runStep('outline', currentContext);
    });

    $continueContentButton.on('click', function() {
        if (currentContext.content) {
            showContentReview(currentContext);
            return;
        }
        const reviewedOutline = $outlineResult.val().trim();
        if (!reviewedOutline) {
            $outlineResult.trigger('focus');
            return;
        }
        currentContext.outline = reviewedOutline;
        delete currentContext.content;
        delete currentContext.seo;
        runStep('content', currentContext);
    });

    $('#azevent-back-to-outline-btn').on('click', function() {
        showSavedStep('outline');
    });

    $continueSeoButton.on('click', function() {
        if (currentContext.seo) {
            showReview(currentContext, pendingNextStep || 'image');
            return;
        }
        delete currentContext.seo;
        runStep('seo', currentContext);
    });

    $('#azevent-back-to-content-btn').on('click', function() {
        showSavedStep('content');
    });

    $regenerateSeoButton.on('click', function() {
        clearContextAfter('seo');
        runStep('seo', currentContext);
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
        currentContext.regenerate_image = $regenerateImage.is(':checked');
        pendingNextStep = pendingNextStep === 'section_images'
            ? 'section_images'
            : ($regenerateImage.is(':checked') ? 'image' : 'finalize');
        runStep(pendingNextStep, currentContext);
    });

    $restartButton.on('click', function() {
        resetStudio();
        syncModeHelp();
        loadBrowserCheckpoint();
    });

    $errorBackButton.on('click', function() {
        showSavedStep($(this).attr('data-step') || '');
    });

    $workflowStepper.on('click keydown', 'li.is-available', function(event) {
        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        event.preventDefault();
        showSavedStep($(this).attr('data-step') || '');
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

    $queueRows.on('click', '.azevent-delete-job', function() {
        const $button = $(this);
        const jobId = parseInt($button.attr('data-job-id'), 10) || 0;
        if (!jobId || !window.confirm('Xóa Job này khỏi Background Queue?')) {
            return;
        }
        $button.prop('disabled', true).text('Đang xóa...');
        $.ajax({
            url: azevent_seo.ajax_url,
            type: 'POST',
            data: {
                action: 'azevent_delete_background_job',
                nonce: azevent_seo.nonce,
                job_id: jobId
            }
        }).done(function(response) {
            showQueueNotice(response.data && response.data.message ? response.data.message : 'Đã cập nhật Job.', !response.success);
            loadQueue(true);
        }).fail(function() {
            showQueueNotice('Không thể xóa Job.', true);
            $button.prop('disabled', false).text('Xóa');
        });
    });

    if (azevent_seo.standalone) {
        openModal();
    } else if (requestedResumeJobId > 0) {
        openModal();
    }
});
