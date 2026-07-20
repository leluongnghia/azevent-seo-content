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
        review: document.getElementById('azlab-review'),
        kicker: document.getElementById('azlab-step-kicker'),
        title: document.getElementById('azlab-step-title'),
        description: document.getElementById('azlab-step-description'),
        textEditor: document.getElementById('azlab-text-editor'),
        textResult: document.getElementById('azlab-text-result'),
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
        finalize: document.getElementById('azlab-finalize')
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
        if (value) {
            elements.processingTitle.textContent = processingCopy[step] || 'AI đang xử lý...';
        }
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

        if (step === 'research' || step === 'brief') {
            elements.textEditor.hidden = false;
            elements.textResult.value = context.results[step] || '';
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
        setBusy(true, step);
        request('azevent_lab_process_step', {
            post_id: postId,
            step: step,
            edits: JSON.stringify(buildEdits()),
            skip_image: skipImage ? '1' : '0'
        }).then(function (data) {
            context = data.context;
            setBusy(false, step);
            if (context.status === 'completed') {
                renderReview('finalize');
                setNotice('SEO Workflow Lab đã lưu Draft thành công.', true);
            } else {
                renderReview(step);
            }
        }).catch(function (error) {
            if (error.payload && error.payload.context) {
                context = error.payload.context;
            }
            setBusy(false, step);
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
        setBusy(true, 'research');
        request('azevent_lab_get_session', { post_id: postId }).then(function (data) {
            context = data.context;
            setBusy(false, context.current_step || 'research');
            if (context.status === 'completed') {
                renderReview('finalize');
                return;
            }
            const completed = context.last_completed_step || 'setup';
            if (completed === 'setup') {
                setNotice('Phiên đã tạo nhưng chưa có Research. Bắt đầu chạy lại bước Research.', false);
                processStep('research', false);
                return;
            }
            renderReview(completed);
            if (context.status === 'processing') {
                setNotice('Request trước có thể đã bị ngắt. Checkpoint trước bước đang chạy vẫn còn; hãy bấm chạy lại khi chắc chắn request cũ đã dừng.', false);
            } else if (context.status === 'failed' && context.error) {
                setNotice(context.error, false);
            }
        }).catch(function (error) {
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
    document.querySelectorAll('[data-content-tab]').forEach(function (button) {
        button.addEventListener('click', function () {
            showContentTab(button.dataset.contentTab);
        });
    });

    loadSession();
}());
