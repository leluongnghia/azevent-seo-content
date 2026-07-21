(function () {
    'use strict';

    const config = window.azevent_workflow_lab || {};
    const stepOrder = ['research', 'brief', 'content', 'seo', 'quality', 'finalize'];
    const stepCopy = {
        research: ['Bước 1 · Research', 'Research & Search Intent', 'Kiểm tra đối tượng, intent, câu hỏi, entities và content gap trước khi lập dàn ý.'],
        brief: ['Bước 2 · Strategy', 'Content Brief & Outline', 'Dàn ý đã dùng Research và các bài Published thật để đề xuất internal link.'],
        content: ['Bước 3 · Writing', 'Nội dung bài viết', 'Đọc bản xem trước hoặc chỉnh HTML trước khi tạo SEO metadata.'],
        seo: ['Bước 4 · Metadata', 'SEO Metadata', 'Kiểm tra title, slug, meta, focus keyword và prompt ảnh.'],
        quality: ['Bước 5 · Validation', 'Internal Links & Quality Gate', 'Lab chỉ giữ internal link hợp lệ và kiểm tra lỗi trước khi cho phép lưu Draft.'],
        finalize: ['Bước 6 · Delivery', 'Ảnh đại diện & Draft', 'Chọn tạo ảnh hoặc lưu Draft không ảnh. Nội dung chỉ được ghi vào bài ở bước này.']
    };
    const processingCopy = {
        research: 'Đang phân tích Research & Search Intent...',
        brief: 'Đang xây dựng Content Brief & Outline...',
        content: 'Đang viết nội dung HTML...',
        seo: 'Đang tạo SEO metadata...',
        quality: 'Đang chèn internal link và chạy Quality Gate...',
        finalize: 'Đang tạo ảnh và lưu Draft...'
    };

    let postId = parseInt(config.post_id, 10) || 0;
    let context = null;
    let viewStep = '';
    let busy = false;
    let logPollTimer = null;
    let elapsedTimer = null;
    let processingStartedAt = 0;
    let activeStep = '';
    let awaitingQueueConfirmation = false;

    const elements = {
        notice: document.getElementById('azlab-notice'),
        setup: document.getElementById('azlab-setup'),
        workflow: document.getElementById('azlab-workflow'),
        start: document.getElementById('azlab-start'),
        keyword: document.getElementById('azlab-keyword'),
        secondary: document.getElementById('azlab-secondary'),
        audience: document.getElementById('azlab-audience'),
        competitors: document.getElementById('azlab-competitors'),
        generateImage: document.getElementById('azlab-generate-image'),
        processing: document.getElementById('azlab-processing'),
        processingTitle: document.getElementById('azlab-processing-title'),
        processingElapsed: document.getElementById('azlab-processing-elapsed'),
        review: document.getElementById('azlab-review'),
        kicker: document.getElementById('azlab-step-kicker'),
        title: document.getElementById('azlab-step-title'),
        description: document.getElementById('azlab-step-description'),
        textEditor: document.getElementById('azlab-text-editor'),
        textResult: document.getElementById('azlab-text-result'),
        serpSources: document.getElementById('azlab-serp-sources'),
        serpMeta: document.getElementById('azlab-serp-meta'),
        serpList: document.getElementById('azlab-serp-list'),
        contentEditor: document.getElementById('azlab-content-editor'),
        contentPreview: document.getElementById('azlab-content-preview'),
        contentHtml: document.getElementById('azlab-content-html'),
        seoEditor: document.getElementById('azlab-seo-editor'),
        seoTitle: document.getElementById('azlab-seo-title'),
        seoSlug: document.getElementById('azlab-seo-slug'),
        seoMeta: document.getElementById('azlab-seo-meta'),
        seoFocus: document.getElementById('azlab-seo-focus'),
        seoImage: document.getElementById('azlab-seo-image'),
        qualityResult: document.getElementById('azlab-quality-result'),
        qualityScore: document.getElementById('azlab-quality-score'),
        qualityState: document.getElementById('azlab-quality-state'),
        qualityCoverage: document.getElementById('azlab-quality-coverage'),
        criticalList: document.getElementById('azlab-critical-list'),
        warningList: document.getElementById('azlab-warning-list'),
        linkList: document.getElementById('azlab-link-list'),
        qualityPreview: document.getElementById('azlab-quality-preview'),
        finalResult: document.getElementById('azlab-final-result'),
        finalConfirmation: document.getElementById('azlab-final-confirmation'),
        editPost: document.getElementById('azlab-edit-post'),
        reviewActions: document.getElementById('azlab-review-actions'),
        finalActions: document.getElementById('azlab-final-actions'),
        back: document.getElementById('azlab-back'),
        rerun: document.getElementById('azlab-rerun'),
        next: document.getElementById('azlab-next'),
        backQuality: document.getElementById('azlab-back-quality'),
        saveNoImage: document.getElementById('azlab-save-no-image'),
        finalize: document.getElementById('azlab-finalize'),
        logPanel: document.getElementById('azlab-log-panel'),
        logOutput: document.getElementById('azlab-log-output'),
        copyLog: document.getElementById('azlab-copy-log'),
        metricsPanel: document.getElementById('azlab-metrics-panel'),
        metricsNote: document.getElementById('azlab-metrics-note'),
        totalTokens: document.getElementById('azlab-total-tokens'),
        inputTokens: document.getElementById('azlab-input-tokens'),
        outputTokens: document.getElementById('azlab-output-tokens'),
        totalDuration: document.getElementById('azlab-total-duration'),
        metricsBody: document.getElementById('azlab-metrics-body')
    };

    function request(action, data) {
        const body = new URLSearchParams(Object.assign({ action: action, nonce: config.nonce }, data || {}));
        return fetch(config.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (response) {
            if (!response.success) {
                const error = new Error(response.data && response.data.message ? response.data.message : 'Không thể hoàn tất yêu cầu.');
                error.payload = response.data || {};
                throw error;
            }
            return response.data;
        });
    }

    function setNotice(message, success) {
        elements.notice.hidden = !message;
        elements.notice.className = 'azlab-notice' + (success ? ' is-success' : '');
        elements.notice.textContent = message || '';
    }

    function setBusy(value, step) {
        busy = value;
        [elements.start, elements.back, elements.rerun, elements.next, elements.backQuality, elements.saveNoImage, elements.finalize].forEach(function (button) {
            if (button) {
                button.disabled = value;
            }
        });
        elements.processing.hidden = !value;
        elements.review.hidden = value;
        elements.workflow.setAttribute('aria-busy', value ? 'true' : 'false');
        if (value) {
            elements.processingTitle.textContent = processingCopy[step] || 'AI đang xử lý...';
            updateStepper(step);
            startElapsedTimer();
            startLogPolling();
        } else {
            stopElapsedTimer();
            stopLogPolling();
            processingStartedAt = 0;
            elements.processingElapsed.textContent = 'Đã chạy 0 giây';
        }
    }

    function startElapsedTimer() {
        stopElapsedTimer();
        const queuedAt = context && context.pending_job ? parseInt(context.pending_job.queued_at, 10) || 0 : 0;
        processingStartedAt = queuedAt > 0 ? queuedAt * 1000 : Date.now();
        updateElapsed();
        elapsedTimer = window.setInterval(updateElapsed, 1000);
    }

    function stopElapsedTimer() {
        if (elapsedTimer) {
            window.clearInterval(elapsedTimer);
            elapsedTimer = null;
        }
    }

    function updateElapsed() {
        if (!elements.processingElapsed || !processingStartedAt) {
            return;
        }
        const seconds = Math.max(0, Math.floor((Date.now() - processingStartedAt) / 1000));
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;
        elements.processingElapsed.textContent = minutes > 0
            ? 'Đã chạy ' + minutes + ' phút ' + remainder + ' giây'
            : 'Đã chạy ' + seconds + ' giây';
    }

    function updateContentSplitProgress(sessionContext) {
        if (!elements.processingTitle || !sessionContext || sessionContext.current_step !== 'content') {
            return;
        }
        const split = sessionContext.content_split || {};
        const sections = Array.isArray(split.sections) ? split.sections : [];
        const index = parseInt(split.current_index, 10) || 0;
        if (!split.enabled || split.completed || !sections.length || !sections[index]) {
            return;
        }
        const title = sections[index].title ? ': ' + sections[index].title : '';
        elements.processingTitle.textContent = 'Đang viết H2 ' + (index + 1) + '/' + sections.length + title;
    }

    function startLogPolling() {
        stopLogPolling();
        if (!postId) {
            return;
        }
        refreshLogs();
        logPollTimer = window.setInterval(refreshLogs, 5000);
    }

    function stopLogPolling() {
        if (logPollTimer) {
            window.clearInterval(logPollTimer);
            logPollTimer = null;
        }
    }

    function refreshLogs() {
        if (!postId) {
            return;
        }
        request('azevent_lab_get_session', { post_id: postId }).then(function (data) {
            if (!data.context) {
                return;
            }
            renderLogs(data.context.logs || []);
            renderMetrics(data.context.metrics || {});
            if (!busy || awaitingQueueConfirmation || !activeStep) {
                return;
            }
            if (data.context.status === 'queued' || data.context.status === 'processing') {
                context = data.context;
                updateContentSplitProgress(context);
                return;
            }

            context = data.context;
            setBusy(false, activeStep);
            const finishedStep = activeStep;
            activeStep = '';
            if (context.status === 'completed') {
                renderReview('finalize');
                setNotice('SEO Workflow Lab đã lưu Draft thành công.', true);
                return;
            }
            if (context.status === 'failed') {
                const fallbackStep = context.last_completed_step && context.last_completed_step !== 'setup'
                    ? context.last_completed_step
                    : finishedStep;
                renderReview(fallbackStep);
                setNotice(context.error || 'Job nền không thể hoàn tất.', false);
                return;
            }
            renderReview(context.last_completed_step || finishedStep);
            setNotice('Đã hoàn thành bước ' + (stepCopy[finishedStep] ? stepCopy[finishedStep][1] : finishedStep) + '. Hãy kiểm tra kết quả trước khi tiếp tục.', true);
        }).catch(function () {});
    }

    function renderLogs(logs) {
        const entries = Array.isArray(logs) ? logs : [];
        elements.logPanel.hidden = !postId;
        elements.logOutput.textContent = entries.length ? entries.map(function (entry) {
            const timestamp = parseInt(entry.timestamp, 10) || 0;
            const time = timestamp ? new Date(timestamp * 1000).toLocaleString() : 'Không rõ thời gian';
            const step = String(entry.step || 'system').toUpperCase();
            const level = String(entry.level || 'info').toUpperCase();
            return '[' + time + '] [' + step + '] [' + level + '] ' + String(entry.message || '');
        }).join('\n') : 'Chưa có log cho phiên này.';
        elements.logOutput.scrollTop = elements.logOutput.scrollHeight;
    }

    function renderMetrics(metrics) {
        const steps = metrics && metrics.steps ? metrics.steps : {};
        const names = {
            research: 'Research',
            brief: 'Brief & Outline',
            content: 'Content',
            seo: 'SEO',
            quality: 'Links & QA',
            finalize: 'Ảnh & Draft'
        };
        let input = 0;
        let output = 0;
        let total = 0;
        let duration = 0;
        let hasEstimated = false;
        elements.metricsBody.innerHTML = '';

        stepOrder.forEach(function (step) {
            const item = steps[step];
            if (!item) {
                return;
            }
            input += parseInt(item.input_tokens, 10) || 0;
            output += parseInt(item.output_tokens, 10) || 0;
            total += parseInt(item.total_tokens, 10) || 0;
            duration += parseFloat(item.step_duration_seconds) || 0;
            hasEstimated = hasEstimated || (parseInt(item.estimated_runs, 10) || 0) > 0;

            const row = document.createElement('tr');
            [
                names[step] || step,
                item.model || (step === 'finalize' ? 'Không dùng model text' : '—'),
                formatNumber(item.runs || 0),
                formatNumber(item.input_tokens || 0),
                formatNumber(item.output_tokens || 0),
                formatNumber(item.total_tokens || 0) + ((parseInt(item.estimated_runs, 10) || 0) > 0 ? ' *' : ''),
                formatDuration(item.ai_duration_seconds || 0) + ' / ' + formatDuration(item.step_duration_seconds || 0)
            ].forEach(function (value) {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            elements.metricsBody.appendChild(row);
        });

        elements.metricsPanel.hidden = !postId;
        elements.inputTokens.textContent = formatNumber(input);
        elements.outputTokens.textContent = formatNumber(output);
        elements.totalTokens.textContent = formatNumber(total) + (hasEstimated ? ' *' : '');
        elements.totalDuration.textContent = formatDuration(duration);
        elements.metricsNote.textContent = hasEstimated
            ? '* Có token ước tính do API không trả usage; số liệu cộng dồn cả các lần chạy lại.'
            : 'Token do API báo cáo; số liệu cộng dồn cả các lần chạy lại.';
    }

    function formatNumber(value) {
        return (parseInt(value, 10) || 0).toLocaleString();
    }

    function formatDuration(value) {
        const seconds = Math.max(0, Math.round(parseFloat(value) || 0));
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;
        return minutes > 0 ? minutes + 'p ' + remainder + 's' : seconds + 's';
    }

    function copyLogs() {
        const value = buildMetricsText() + '\n\n' + (elements.logOutput.textContent || '');
        const copied = function () {
            setNotice('Đã sao chép log phiên. Bạn có thể dán trực tiếp để gửi kiểm tra.', true);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(copied).catch(function () {
                fallbackCopy(value, copied);
            });
            return;
        }
        fallbackCopy(value, copied);
    }

    function buildMetricsText() {
        return [
            'TOKEN & TIME REPORT',
            'Total: ' + elements.totalTokens.textContent,
            'Input: ' + elements.inputTokens.textContent,
            'Output: ' + elements.outputTokens.textContent,
            'Duration: ' + elements.totalDuration.textContent,
            elements.metricsNote.textContent || ''
        ].join('\n');
    }

    function fallbackCopy(value, callback) {
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        callback();
    }

    function buildPreviewDocument(content) {
        return '<!doctype html><html><head><meta charset="utf-8"><style>' +
            'body{box-sizing:border-box;max-width:960px;margin:0 auto;padding:36px 44px;font:16px/1.75 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1e293b}' +
            'h2,h3,h4{color:#0f172a;line-height:1.35}h2{font-size:26px;margin-top:32px}h3{font-size:20px;margin-top:24px}p{margin:0 0 16px}' +
            'table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #dbe4f3;padding:9px;text-align:left}a{color:#4f46e5}ul,ol{padding-left:24px}' +
            '</style></head><body>' + (content || '') + '</body></html>';
    }

    function setIframeContent(iframe, content) {
        iframe.srcdoc = buildPreviewDocument(content);
    }

    function updateStepper(step) {
        const activeIndex = stepOrder.indexOf(step);
        document.querySelectorAll('.azlab-stepper li').forEach(function (item, index) {
            item.classList.toggle('is-active', index === activeIndex);
            item.classList.toggle('is-complete', index < activeIndex || (context && context.status === 'completed'));
        });
    }

    function hideResults() {
        [elements.textEditor, elements.contentEditor, elements.seoEditor, elements.qualityResult, elements.finalResult, elements.finalConfirmation].forEach(function (item) {
            item.hidden = true;
        });
    }

    function fillList(list, items, emptyText, renderer) {
        list.innerHTML = '';
        const values = Array.isArray(items) ? items : [];
        if (!values.length) {
            const item = document.createElement('li');
            item.textContent = emptyText;
            list.appendChild(item);
            return;
        }
        values.forEach(function (value) {
            const item = document.createElement('li');
            item.textContent = renderer ? renderer(value) : String(value);
            list.appendChild(item);
        });
    }

    function renderSerpSources() {
        const snapshot = context && context.serp_snapshot ? context.serp_snapshot : null;
        const results = snapshot && Array.isArray(snapshot.organic_results) ? snapshot.organic_results : [];
        elements.serpSources.hidden = !results.length;
        elements.serpList.innerHTML = '';
        if (!results.length) {
            return;
        }
        const fetchedAt = parseInt(snapshot.fetched_at, 10) || 0;
        elements.serpMeta.textContent = (snapshot.provider || 'SERP') + ' · Đã lưu theo phiên' + (snapshot.location ? ' · ' + snapshot.location : '') + (fetchedAt ? ' · ' + new Date(fetchedAt * 1000).toLocaleString() : '');
        results.forEach(function (result) {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = result.url || '#';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = (result.position ? '#' + result.position + ' ' : '') + (result.title || result.domain || result.url || 'Kết quả SERP');
            const domain = document.createElement('small');
            const structure = result.page_structure && result.page_structure.status === 'success'
                ? ' · đã đọc cấu trúc trang'
                : '';
            domain.textContent = (result.domain || '') + structure;
            item.appendChild(link);
            item.appendChild(domain);
            elements.serpList.appendChild(item);
        });
    }

    function captureCurrentEdits() {
        if (!context || !context.results) {
            return;
        }
        if (viewStep === 'research' || viewStep === 'brief') {
            context.results[viewStep] = elements.textResult.value;
        } else if (viewStep === 'content') {
            context.results.content = elements.contentHtml.value;
        } else if (viewStep === 'seo') {
            context.results.seo = Object.assign({}, context.results.seo || {}, {
                title: elements.seoTitle.value,
                slug: elements.seoSlug.value,
                meta: elements.seoMeta.value,
                focus_keyword: elements.seoFocus.value,
                image_prompt: elements.seoImage.value
            });
        }
    }

    function buildEdits() {
        captureCurrentEdits();
        return {
            research: context.results.research || '',
            brief: context.results.brief || '',
            content: context.results.content || '',
            seo: context.results.seo || {}
        };
    }

    function renderReview(step) {
        if (!context) {
            return;
        }
        captureCurrentEdits();
        viewStep = step;
        hideResults();
        elements.review.hidden = false;
        elements.processing.hidden = true;
        elements.reviewActions.hidden = false;
        elements.finalActions.hidden = true;
        const copy = stepCopy[step] || ['', step, ''];
        elements.kicker.textContent = copy[0];
        elements.title.textContent = copy[1];
        elements.description.textContent = copy[2];
        updateStepper(step);
        renderLogs(context.logs || []);
        renderMetrics(context.metrics || {});

        if (step === 'research' || step === 'brief') {
            elements.textEditor.hidden = false;
            elements.textResult.value = context.results[step] || '';
            if (step === 'research') {
                renderSerpSources();
            } else {
                elements.serpSources.hidden = true;
            }
        } else if (step === 'content') {
            elements.contentEditor.hidden = false;
            elements.contentHtml.value = context.results.content || '';
            setIframeContent(elements.contentPreview, context.results.content || '');
            showContentTab('preview');
        } else if (step === 'seo') {
            const seo = context.results.seo || {};
            elements.seoEditor.hidden = false;
            elements.seoTitle.value = seo.title || '';
            elements.seoSlug.value = seo.slug || '';
            elements.seoMeta.value = seo.meta || '';
            elements.seoFocus.value = seo.focus_keyword || '';
            elements.seoImage.value = seo.image_prompt || '';
        } else if (step === 'quality') {
            const quality = context.results.quality || {};
            const coverage = quality.coverage || {};
            elements.qualityResult.hidden = false;
            elements.qualityScore.textContent = parseInt(quality.score, 10) || 0;
            elements.qualityState.textContent = quality.passed ? 'Đạt Quality Gate' : 'Cần kiểm tra trước khi lưu';
            elements.qualityCoverage.textContent = [coverage.intent, coverage.entities, coverage.questions].filter(Boolean).join(' · ');
            fillList(elements.criticalList, quality.critical_issues, 'Không phát hiện lỗi quan trọng.');
            fillList(elements.warningList, quality.warnings, 'Không có cảnh báo.');
            fillList(elements.linkList, quality.internal_links, 'Chưa chèn internal link phù hợp.', function (link) {
                return (link.title || link.url || '') + (link.url ? ' — ' + link.url : '');
            });
            setIframeContent(elements.qualityPreview, context.results.content || '');
        } else if (step === 'finalize') {
            if (context.status === 'completed') {
                elements.finalResult.hidden = false;
                elements.reviewActions.hidden = true;
                elements.finalActions.hidden = true;
                elements.editPost.href = config.edit_url_base + postId;
                return;
            }
            elements.finalActions.hidden = false;
            elements.reviewActions.hidden = true;
            elements.finalConfirmation.hidden = false;
        }

        const index = stepOrder.indexOf(step);
        const furthest = stepOrder.indexOf(context.last_completed_step || 'research');
        elements.back.disabled = index <= 0;
        elements.rerun.hidden = step === 'finalize';
        elements.next.hidden = step === 'quality' || step === 'finalize';
        elements.next.textContent = index < furthest ? 'Xem bước tiếp theo' : 'Tiếp tục';
    }

    function showContentTab(tab) {
        document.querySelectorAll('[data-content-tab]').forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.contentTab === tab);
        });
        elements.contentPreview.hidden = tab !== 'preview';
        elements.contentHtml.hidden = tab !== 'html';
        if (tab === 'preview') {
            setIframeContent(elements.contentPreview, elements.contentHtml.value);
        }
    }

    function processStep(step, skipImage) {
        if (busy) {
            return;
        }
        setNotice('', false);
        activeStep = step;
        awaitingQueueConfirmation = true;
        setBusy(true, step);
        request('azevent_lab_process_step', {
            post_id: postId,
            step: step,
            edits: JSON.stringify(buildEdits()),
            skip_image: skipImage ? '1' : '0'
        }).then(function (data) {
            context = data.context;
            awaitingQueueConfirmation = false;
            renderLogs(context.logs || []);
            renderMetrics(context.metrics || {});
            setNotice('Đã đưa bước vào job nền. Bạn có thể đóng tab và mở lại phiên sau.', true);
            refreshLogs();
        }).catch(function (error) {
            awaitingQueueConfirmation = false;
            activeStep = '';
            if (error.payload && error.payload.context) {
                context = error.payload.context;
            }
            setBusy(false, step);
            renderLogs(context && context.logs ? context.logs : []);
            renderMetrics(context && context.metrics ? context.metrics : {});
            const fallbackStep = context && context.last_completed_step && context.last_completed_step !== 'setup'
                ? context.last_completed_step
                : step;
            if (context && context.results && context.results[fallbackStep]) {
                renderReview(fallbackStep);
            }
            setNotice(error.message, false);
        });
    }

    function startSession() {
        const keyword = elements.keyword.value.trim();
        if (!keyword) {
            setNotice('Vui lòng nhập từ khóa chính.', false);
            elements.keyword.focus();
            return;
        }
        if (busy) {
            return;
        }
        elements.start.disabled = true;
        setNotice('', false);
        request('azevent_lab_create_session', {
            keyword: keyword,
            secondary_keywords: elements.secondary.value,
            audience: elements.audience.value,
            competitor_notes: elements.competitors.value,
            generate_image: elements.generateImage.checked ? '1' : '0'
        }).then(function (data) {
            postId = parseInt(data.post_id, 10) || 0;
            context = data.context;
            renderLogs(context.logs || []);
            renderMetrics(context.metrics || {});
            elements.setup.hidden = true;
            elements.workflow.hidden = false;
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('azevent_lab_post', String(postId));
                window.history.replaceState({}, '', url.toString());
            }
            processStep('research', false);
        }).catch(function (error) {
            elements.start.disabled = false;
            setNotice(error.message, false);
        });
    }

    function loadSession() {
        if (!postId) {
            return;
        }
        elements.setup.hidden = true;
        elements.workflow.hidden = false;
        awaitingQueueConfirmation = true;
        setBusy(true, 'research');
        request('azevent_lab_get_session', { post_id: postId }).then(function (data) {
            context = data.context;
            awaitingQueueConfirmation = false;
            renderLogs(context.logs || []);
            renderMetrics(context.metrics || {});
            if (context.status === 'queued' || context.status === 'processing') {
                activeStep = context.current_step || (context.pending_job && context.pending_job.step) || 'research';
                setBusy(true, activeStep);
                updateContentSplitProgress(context);
                setNotice('Job nền vẫn đang chạy. Bạn có thể đóng tab; checkpoint và log vẫn được lưu.', true);
                return;
            }
            setBusy(false, context.current_step || 'research');
            if (context.status === 'completed') {
                renderReview('finalize');
                return;
            }
            const completed = context.last_completed_step || 'setup';
            if (completed === 'setup') {
                if (context.status === 'failed') {
                    renderReview(context.current_step || 'research');
                    setNotice(context.error || 'Research chưa hoàn thành. Bạn có thể chạy lại bước này.', false);
                    return;
                }
                setNotice('Phiên đã tạo nhưng chưa có Research. Bắt đầu chạy lại bước Research.', false);
                processStep('research', false);
                return;
            }
            renderReview(completed);
            if (context.status === 'failed' && context.error) {
                setNotice(context.error, false);
            }
        }).catch(function (error) {
            awaitingQueueConfirmation = false;
            activeStep = '';
            setBusy(false, 'research');
            setNotice(error.message, false);
            elements.setup.hidden = false;
            elements.workflow.hidden = true;
        });
    }

    elements.start.addEventListener('click', startSession);
    elements.back.addEventListener('click', function () {
        const index = stepOrder.indexOf(viewStep);
        if (index > 0) {
            renderReview(stepOrder[index - 1]);
        }
    });
    elements.next.addEventListener('click', function () {
        captureCurrentEdits();
        const index = stepOrder.indexOf(viewStep);
        const furthest = stepOrder.indexOf(context.last_completed_step || 'research');
        if (index < furthest) {
            renderReview(stepOrder[index + 1]);
            return;
        }
        const nextStep = context.next_step;
        if (nextStep === 'finalize') {
            renderReview('finalize');
        } else if (nextStep) {
            processStep(nextStep, false);
        }
    });
    elements.rerun.addEventListener('click', function () {
        if (viewStep && viewStep !== 'finalize') {
            processStep(viewStep, false);
        }
    });
    elements.backQuality.addEventListener('click', function () {
        renderReview('quality');
    });
    elements.saveNoImage.addEventListener('click', function () {
        processStep('finalize', true);
    });
    elements.finalize.addEventListener('click', function () {
        processStep('finalize', false);
    });
    elements.copyLog.addEventListener('click', copyLogs);
    document.querySelectorAll('.azlab-delete-session').forEach(function (button) {
        button.addEventListener('click', function () {
            const sessionId = parseInt(button.dataset.sessionId, 10) || 0;
            if (!sessionId || !window.confirm('Xoá dữ liệu phiên Workflow Lab này? Bài Draft liên quan vẫn được giữ trong Posts.')) {
                return;
            }
            button.disabled = true;
            request('azevent_lab_delete_session', { post_id: sessionId }).then(function (data) {
                if (sessionId === postId) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('azevent_lab_post');
                    window.location.assign(url.toString());
                    return;
                }
                const item = button.closest('.azlab-session-item');
                if (item) {
                    item.remove();
                }
                const sessionList = document.querySelector('.azlab-session-list');
                if (sessionList && !sessionList.querySelector('.azlab-session-item')) {
                    sessionList.innerHTML = '<div class="azlab-empty"><span class="dashicons dashicons-media-document"></span><p>Chưa có phiên SEO Workflow Lab.</p></div>';
                }
                setNotice(data.message || 'Đã xoá phiên.', true);
            }).catch(function (error) {
                button.disabled = false;
                setNotice(error.message, false);
            });
        });
    });
    document.querySelectorAll('[data-content-tab]').forEach(function (button) {
        button.addEventListener('click', function () {
            showContentTab(button.dataset.contentTab);
        });
    });

    loadSession();
}());
