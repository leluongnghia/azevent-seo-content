<?php

define('ABSPATH', __DIR__ . '/');
define('AZEVENT_SEO_PATH', dirname(__DIR__) . '/');
define('AZEVENT_SEO_VERSION', 'test');

$azevent_test_insert_calls = 0;
$azevent_test_post_updates = array();
$azevent_test_meta = array();

class WP_Error
{
    private $code;
    private $message;

    public function __construct($code, $message)
    {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_message()
    {
        return $this->message;
    }
}

function is_wp_error($value)
{
    return $value instanceof WP_Error;
}

function sanitize_text_field($value)
{
    return trim(strip_tags((string) $value));
}

function sanitize_textarea_field($value)
{
    return trim((string) $value);
}

function sanitize_key($value)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function sanitize_title($value)
{
    return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $value)), '-');
}

function absint($value)
{
    return abs((int) $value);
}

function get_option($name, $default = false)
{
    return $default;
}

function current_user_can($capability, $post_id = 0)
{
    return true;
}

function get_post($post_id)
{
    if ((int) $post_id !== 42) {
        return null;
    }
    return (object) array(
        'ID' => 42,
        'post_type' => 'post',
        'post_title' => 'Bài cũ',
        'post_content' => '<p>Nội dung gốc cần giữ.</p>',
        'post_excerpt' => 'Mô tả cũ',
        'post_name' => 'slug-bai-cu',
        'post_status' => 'publish',
    );
}

function wp_insert_post($data, $return_error = false)
{
    global $azevent_test_insert_calls;
    $azevent_test_insert_calls++;
    return 99;
}

function update_post_meta($post_id, $key, $value)
{
    global $azevent_test_meta;
    $azevent_test_meta[$post_id][$key] = $value;
    return true;
}

function get_post_thumbnail_id($post_id)
{
    return 0;
}

function has_post_thumbnail($post_id)
{
    return false;
}

function wp_update_post($data, $return_error = false)
{
    global $azevent_test_post_updates;
    $azevent_test_post_updates[] = $data;
    return $data['ID'];
}

function azevent_rewrite_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

require AZEVENT_SEO_PATH . 'includes/class-azevent-workflow-lab-pipeline.php';

$pipeline = new AzEvent_Workflow_Lab_Pipeline();
$created = $pipeline->create_session(array(
    'mode' => 'rewrite',
    'existing_post_id' => 42,
    'keyword' => 'từ khóa mới',
    'secondary_keywords' => "phụ một\nphụ hai",
    'generate_image' => false,
), 7);

azevent_rewrite_assert(!is_wp_error($created), 'Tạo được phiên rewrite trên bài hiện có.');
azevent_rewrite_assert($azevent_test_insert_calls === 0, 'Rewrite không tạo thêm WordPress post.');
azevent_rewrite_assert(($created['context']['input']['mode'] ?? '') === 'rewrite', 'Checkpoint lưu đúng chế độ rewrite.');
azevent_rewrite_assert(($created['context']['existing_post']['slug'] ?? '') === 'slug-bai-cu', 'Checkpoint giữ snapshot slug gốc.');
azevent_rewrite_assert(($created['context']['existing_post']['content'] ?? '') === '<p>Nội dung gốc cần giữ.</p>', 'Checkpoint giữ nội dung gốc để AI đối chiếu.');

$context = $created['context'];
$context['results'] = array(
    'content' => '<p>Nội dung đã viết lại.</p>',
    'seo' => array(
        'title' => 'Tiêu đề mới',
        'slug' => 'slug-ai-de-xuat',
        'meta' => 'Meta mới',
        'focus_keyword' => 'từ khóa mới',
        'image_prompt' => 'Ảnh',
    ),
    'quality' => array('passed' => true),
);
$finalize = new ReflectionMethod($pipeline, 'run_finalize');
if (PHP_VERSION_ID < 80100) {
    $finalize->setAccessible(true);
}
$result = $finalize->invokeArgs($pipeline, array(42, &$context, true));

azevent_rewrite_assert(!is_wp_error($result), 'Finalize rewrite thành công khi bỏ qua ảnh mới.');
$last_update = end($azevent_test_post_updates);
azevent_rewrite_assert(!array_key_exists('post_name', $last_update), 'Finalize rewrite không ghi đè slug gốc.');
azevent_rewrite_assert(($last_update['post_status'] ?? '') === 'draft', 'Bài viết lại được chuyển về Draft để duyệt.');
azevent_rewrite_assert(($last_update['post_content'] ?? '') === '<p>Nội dung đã viết lại.</p>', 'Nội dung chỉ được ghi ở finalize.');

fwrite(STDOUT, "All Workflow Lab rewrite regression checks passed.\n");
