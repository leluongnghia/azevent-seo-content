jQuery(document).ready(function($) {
    const $btn = $('#azevent-generate-btn');
    const $progress = $('#azevent-progress');
    const $statusText = $('#azevent-status-text');
    const $log = $('#azevent-log');
    const $mode = $('#azevent-mode');
    const $regenerateImage = $('#azevent-regenerate-image');
    const $keywords = $('#azevent-keywords');

    function updateStatus(text, showLog = true) {
        $statusText.text(text);
        if (showLog) {
            $log.show().append('<div>' + new Date().toLocaleTimeString() + ': ' + text + '</div>');
            $log.scrollTop($log[0].scrollHeight);
        }
    }

    function syncModeDefaults() {
        $regenerateImage.prop('checked', $mode.val() === 'create');
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

    function resetButton() {
        $btn.prop('disabled', false).text('Bắt đầu tạo bài viết');
        $progress.hide();
    }

    function showError(message) {
        alert('Lỗi: ' + message);
        resetButton();
    }

    $mode.on('change', syncModeDefaults);
    syncModeDefaults();

    $btn.on('click', function() {
        const keywords = getKeywords();
        const mode = $mode.val() || 'create';
        const language = $('#azevent-language').val();
        const originalPostId = parseInt(azevent_seo.post_id, 10) || 0;
        const regenerateImage = $regenerateImage.is(':checked') ? '1' : '0';

        if (!keywords.length) {
            alert('Vui lòng nhập ít nhất một từ khóa, mỗi dòng một từ khóa.');
            return;
        }

        if (mode === 'rewrite' && originalPostId <= 0) {
            alert('Vui lòng lưu bài viết trước khi dùng chế độ viết lại.');
            return;
        }

        if (mode === 'rewrite' && keywords.length > 1) {
            alert('Chế độ viết lại chỉ nhận một từ khóa cho bài hiện tại.');
            return;
        }

        if (!confirm('Bắt đầu xử lý ' + keywords.length + ' từ khóa? Quá trình có thể mất vài phút.')) {
            return;
        }

        $btn.prop('disabled', true).text('Đang xử lý...');
        $progress.show();
        $log.empty().show();

        function finishBatch(results, lastResponse) {
            resetButton();

            if (mode === 'rewrite') {
                updateEditor(lastResponse.data);
                alert('Đã viết lại bài và lưu thành bản nháp.');
                location.reload();
                return;
            }

            if (results.length === 1 && results[0].postId > 0) {
                alert('Đã tạo 1 Draft từ khóa thành công.');
                window.location.href = 'post.php?post=' + results[0].postId + '&action=edit';
                return;
            }

            alert('Đã tạo ' + results.length + ' Draft thành công.');
            window.location.href = 'edit.php?post_type=post';
        }

        function runKeyword(index, results) {
            const keyword = keywords[index];
            let currentPostId = mode === 'rewrite' ? originalPostId : 0;

            updateStatus('Đang xử lý từ khóa ' + (index + 1) + '/' + keywords.length + ': ' + keyword);

            function runStep(step, context) {
                $.ajax({
                    url: azevent_seo.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'azevent_generate_content',
                        nonce: azevent_seo.nonce,
                        keyword: keyword,
                        language: language,
                        post_id: currentPostId,
                        mode: mode,
                        regenerate_image: regenerateImage,
                        step: step || 'start',
                        context: JSON.stringify(context || {})
                    },
                    success: function(response) {
                        if (!response.success) {
                            showError(response.data && response.data.message ? response.data.message : 'Không thể xử lý từ khóa.');
                            return;
                        }

                        const responseContext = response.data.context || {};
                        const responsePostId = parseInt(response.data.post_id || responseContext.post_id || currentPostId, 10) || 0;
                        if (responsePostId > 0) {
                            currentPostId = responsePostId;
                        }

                        if (response.data.status === 'completed') {
                            results.push({ keyword: keyword, postId: currentPostId });
                            if (index + 1 < keywords.length) {
                                runKeyword(index + 1, results);
                            } else {
                                finishBatch(results, response);
                            }
                            return;
                        }

                        updateStatus(response.data.message || 'Đang xử lý...');
                        runStep(response.data.next_step, responseContext);
                    },
                    error: function() {
                        showError('Đã xảy ra lỗi kết nối khi xử lý: ' + keyword);
                    }
                });
            }

            runStep('start', {});
        }

        runKeyword(0, []);
    });
});
