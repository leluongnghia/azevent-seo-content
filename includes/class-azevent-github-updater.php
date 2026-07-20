<?php

/**
 * GitHub Updater for Private Repository
 *
 * Enables automatic updates from a private GitHub repository
 * 
 * @package    AI_Product_Review_Generator
 * @subpackage AI_Product_Review_Generator/includes
 */

class AzEvent_GitHub_Updater
{
    /**
     * GitHub username
     */
    private $username;

    /**
     * GitHub repository name
     */
    private $repository;

    /**
     * GitHub personal access token
     */
    private $access_token;

    /**
     * Plugin basename (folder/file.php)
     */
    private $basename;

    /**
     * Plugin slug (folder name)
     */
    private $slug;

    /**
     * Plugin data
     */
    private $plugin_data;

    /**
     * GitHub API result
     */
    private $github_response;

    /**
     * Last GitHub API error for the settings page and manual checks.
     */
    private $github_error = '';

    /**
     * Initialize the updater
     *
     * @param string $plugin_file Full path to the main plugin file
     * @param string $github_username GitHub username
     * @param string $github_repo GitHub repository name
     * @param string $access_token GitHub personal access token (optional for public repos)
     */
    public function __construct($plugin_file, $github_username, $github_repo, $access_token = '')
    {
        $saved_username = trim((string) get_option('azevent_github_username', ''));
        $saved_repository = trim((string) get_option('azevent_github_repo', ''));
        $this->username = $saved_username !== '' ? $saved_username : $github_username;
        $this->repository = $saved_repository !== '' ? $saved_repository : $github_repo;
        $this->access_token = get_option('azevent_github_token', $access_token);
        $this->basename = plugin_basename($plugin_file);
        $this->slug = dirname($this->basename);

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 4);

        // Fix "files could not be copied" permission error during update
        add_filter('filesystem_method', array($this, 'force_direct_fs_method'), 10, 3);
        add_filter('upgrader_pre_install', array($this, 'pre_install_fix_permissions'), 10, 2);

        // Inject auth header for download package
        add_filter('http_request_args', array($this, 'add_auth_header'), 10, 2);

        // Add settings page for GitHub token
        add_action('admin_menu', array($this, 'add_settings_page'), 99);
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX: Force check update (clear cache)
        add_action('wp_ajax_azevent_force_update_check', array($this, 'handle_force_check_ajax'));

        // AJAX: Fix permissions (for manual upload)
        add_action('wp_ajax_azevent_fix_plugin_permissions', array($this, 'handle_fix_permissions_ajax'));
    }

    /**
     * Force WordPress to use direct filesystem method during plugin upgrades.
     * Prevents the FTP credentials prompt and "files could not be copied" error
     * in Local WP and similar environments where PHP runs as the web user.
     */
    public function force_direct_fs_method($method, $args, $context)
    {
        // Only override for our plugin's directory or the tmp upgrade dir
        if (
            (is_string($context) && strpos($context, $this->slug) !== false) ||
            (is_string($context) && strpos($context, 'upgrade') !== false)
        ) {
            return 'direct';
        }
        return $method;
    }

    /**
     * Fix directory permissions before WordPress moves upgraded files.
     * Handles both auto-update (hook_extra has 'plugin') and manual ZIP upload.
     */
    public function pre_install_fix_permissions($response, $hook_extra)
    {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $this->slug;

        // Case 1: Auto-update — hook_extra has 'plugin' key
        $is_our_plugin = isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->basename;

        // Case 2: Manual upload — hook_extra may be empty or have 'action'=>'upload-plugin'
        // WordPress doesn't know which plugin is being uploaded yet, so we check the source.
        // We fix our plugin dir proactively when ANY plugin upload is detected.
        $is_upload = isset($hook_extra['action']) && $hook_extra['action'] === 'upload-plugin';
        $is_general_update = empty($hook_extra) || $is_upload;

        if (!$is_our_plugin && !$is_general_update) {
            return $response;
        }

        if (is_dir($plugin_dir)) {
            $this->make_writable_recursive($plugin_dir);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AzEvent Updater: Fixed permissions on ' . $plugin_dir);
            }
        }

        return $response;
    }

    /**
     * AJAX: Fix plugin directory permissions on demand.
     * User clicks "Fix Permissions" before doing a manual upload.
     */
    public function handle_fix_permissions_ajax()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('azevent_fix_permissions', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $this->slug;

        if (!is_dir($plugin_dir)) {
            wp_send_json_error(array('message' => 'Plugin directory not found: ' . $plugin_dir));
        }

        $this->make_writable_recursive($plugin_dir);

        // Also remove locked log files that block copy
        $log_file = $plugin_dir . '/azevent-debug.log';
        if (file_exists($log_file)) {
            @chmod($log_file, 0644);
        }

        wp_send_json_success(array(
            'message' => '✅ Đã fix permissions cho thư mục plugin. Bây giờ bạn có thể upload ZIP bình thường.',
            'path'    => $plugin_dir,
        ));
    }

    /**
     * Recursively chmod directories to 0755 and files to 0644.
     */
    private function make_writable_recursive($path)
    {
        if (!is_dir($path)) {
            @chmod($path, 0644);
            return;
        }

        @chmod($path, 0755);

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $this->make_writable_recursive($path . '/' . $item);
        }
    }

    /**
     * Add Authorization header to download request
     */
    public function add_auth_header($args, $url)
    {
        if (empty($this->access_token)) {
            return $args;
        }

        // Check if this is a request to GitHub
        if (strpos($url, 'github.com') !== false || strpos($url, 'api.github.com') !== false) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        // Fix PCLZIP_ERR_BAD_FORMAT: Force binary download for API asset URLs
        if (strpos($url, 'api.github.com') !== false && strpos($url, '/releases/assets/') !== false) {
            $args['headers']['Accept'] = 'application/octet-stream';
        }

        return $args;
    }

    /**
     * Get plugin data
     */
    private function get_plugin_data()
    {
        if (empty($this->plugin_data)) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $this->plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->basename);
        }
        return $this->plugin_data;
    }

    /**
     * Get information from GitHub API
     */
    private function get_repository_info()
    {
        $this->github_error = '';
        $saved_username = trim((string) get_option('azevent_github_username', ''));
        $saved_repository = trim((string) get_option('azevent_github_repo', ''));
        if ($saved_username !== '') {
            $this->username = $saved_username;
        }
        if ($saved_repository !== '') {
            $this->repository = $saved_repository;
        }

        if (!empty($this->github_response)) {
            return $this->github_response;
        }

        // Try getting cached response from transient
        $transient_key = 'azevent_github_latest_release_' . md5($this->username . $this->repository);
        $failure_key = $transient_key . '_failed';
        $cached_response = get_transient($transient_key);

        if ($cached_response !== false) {
            $this->github_response = $cached_response;
            return $this->github_response;
        }

        // Do not block multiple admin requests on the same GitHub/DNS outage.
        // A successful request clears this short negative cache immediately.
        if (get_transient($failure_key)) {
            return false;
        }

        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->username,
            $this->repository
        );

        $args = array(
            'timeout' => max(1, (int) apply_filters('azevent_github_api_timeout', 8)),
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            )
        );

        // Add authorization header if access token is provided
        if (!empty($this->access_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        $response = wp_remote_get($request_uri, $args);

        if (is_wp_error($response)) {
            $this->github_error = 'Không thể kết nối tới GitHub: ' . $response->get_error_message();
            set_transient($failure_key, 1, 15 * MINUTE_IN_SECONDS);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response));

        if ($response_code !== 200) {
            $api_message = is_object($response_body) && !empty($response_body->message)
                ? (string) $response_body->message
                : 'GitHub không trả về bản Release hợp lệ.';

            if ($response_code === 404) {
                $api_message = 'Không tìm thấy repository hoặc repository chưa có Release được publish.';
            } elseif ($response_code === 401) {
                $api_message = 'Token không hợp lệ hoặc đã hết hạn.';
            } elseif ($response_code === 403) {
                $api_message = 'GitHub từ chối request hoặc đã vượt giới hạn API.';
            }

            $this->github_error = sprintf(
                'GitHub API %d (%s/%s): %s',
                $response_code,
                $this->username,
                $this->repository,
                $api_message
            );
            set_transient($failure_key, 1, 15 * MINUTE_IN_SECONDS);
            return false;
        }

        $this->github_response = $response_body;

        // Save successfully received response to transient for 12 hours
        if ($this->github_response) {
            set_transient($transient_key, $this->github_response, 12 * HOUR_IN_SECONDS);
            delete_transient($failure_key);
        } else {
            set_transient($failure_key, 1, 15 * MINUTE_IN_SECONDS);
        }

        return $this->github_response;
    }

    /**
     * Check for updates
     */
    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get token from options (in case it was updated after initialization)
        $saved_token = get_option('azevent_github_token', '');
        if (!empty($saved_token) && empty($this->access_token)) {
            $this->access_token = $saved_token;
        }

        $repo_info = $this->get_repository_info();

        if ($repo_info === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AzEvent GitHub Updater: Failed to get repository info. Token: ' . (empty($this->access_token) ? 'EMPTY' : 'SET'));
            }
            return $transient;
        }

        $plugin_data = $this->get_plugin_data();
        $current_version = $plugin_data['Version'];

        // Remove 'v' prefix from tag name if present
        $latest_version = ltrim($repo_info->tag_name, 'v');

        // Compare versions
        if (version_compare($current_version, $latest_version, '<')) {
            $plugin_info = array(
                'slug' => $this->slug,
                'plugin' => $this->basename,
                'new_version' => $latest_version,
                'url' => $repo_info->html_url,
                'package' => $this->get_download_url($repo_info),
                'tested' => get_bloginfo('version'),
            );

            $transient->response[$this->basename] = (object) $plugin_info;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("AzEvent GitHub Updater: Update injected! New version: {$latest_version}");
            }
        }

        return $transient;
    }

    /**
     * Get download URL for the release.
     *
     * For PRIVATE repos: use the asset's `url` (api.github.com API URL) with
     * Authorization + Accept: application/octet-stream headers.
     * For PUBLIC repos: `browser_download_url` is fine.
     *
     * Falls back to zipball_url when no asset is attached.
     */
    private function get_download_url($repo_info)
    {
        // Check if the release has assets (ZIPs uploaded by our deployment script)
        if (!empty($repo_info->assets) && is_array($repo_info->assets)) {
            foreach ($repo_info->assets as $asset) {
                // Match our ZIP naming convention: plugin-slug-vX.Y.Z.zip
                if (
                    isset($asset->name) &&
                    strpos($asset->name, $this->slug) !== false &&
                    substr($asset->name, -4) === '.zip'
                ) {
                    // For private repos (has token): use the API URL so auth headers work.
                    // For public repos: browser_download_url is fine.
                    if (!empty($this->access_token) && isset($asset->url)) {
                        return $asset->url; // e.g. https://api.github.com/repos/.../releases/assets/12345
                    }

                    if (isset($asset->browser_download_url)) {
                        return $asset->browser_download_url;
                    }
                }
            }
        }

        // Fallback: use zipball_url (may result in wrong folder name, but
        // upgrader_source_selection will attempt to rename it)
        return $repo_info->zipball_url;
    }

    /**
     * Show plugin information
     */
    public function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $repo_info = $this->get_repository_info();

        if ($repo_info === false) {
            return false;
        }

        $plugin_data = $this->get_plugin_data();

        $plugin_info = array(
            'name' => $plugin_data['Name'],
            'slug' => $this->slug,
            'version' => ltrim($repo_info->tag_name, 'v'),
            'author' => $plugin_data['Author'],
            'homepage' => $plugin_data['PluginURI'],
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'downloaded' => 0,
            'last_updated' => $repo_info->published_at,
            'sections' => array(
                'description' => $plugin_data['Description'],
                'changelog' => $this->parse_changelog($repo_info->body),
            ),
            'download_link' => $this->get_download_url($repo_info),
        );

        return (object) $plugin_info;
    }

    /**
     * Parse changelog from release notes
     */
    private function parse_changelog($body)
    {
        if (empty($body)) {
            return 'No changelog available.';
        }

        // Convert markdown to HTML (basic conversion)
        $changelog = wpautop($body);

        return $changelog;
    }

    /**
     * Rename the extracted folder before it's moved to the plugins directory
     * 
     * This fixes the issue where GitHub zipballs create a folder like 'repo-name-commit-hash'
     * which causes the plugin to be deactivated due to a path mismatch.
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = null)
    {
        global $wp_filesystem;

        // Check if $hook_extra specifies and matches our plugin basename
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] !== $this->basename) {
            return $source; // Not our plugin, leave it alone
        }

        // Also check if our main plugin file exists in this source package
        // This handles cases where $hook_extra['plugin'] might not be set properly yet
        $plugin_file_name = basename($this->basename);
        if (!file_exists(trailingslashit($source) . $plugin_file_name)) {
            return $source; // Not our plugin file inside, skip
        }

        $source_dir_name = basename(untrailingslashit($source));
        $desired_name = $this->slug;

        // If the extracted folder name does not match the desired slug
        if ($source_dir_name !== $desired_name) {
            $new_source = trailingslashit($remote_source) . $desired_name;

            // Rename the folder within the 'upgrade' directory before it gets moved to 'plugins'
            if ($wp_filesystem->move($source, $new_source, true)) {
                return trailingslashit($new_source);
            }
        }

        return $source;
    }

    /**
     * Handle AJAX: Force clear WordPress update cache and re-check GitHub
     */
    public function handle_force_check_ajax()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('azevent_force_update', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // 1. Clear WordPress update transient cache
        delete_site_transient('update_plugins');

        // 2. Clear our cached GitHub response
        $this->github_response = null;

        // Clear new custom transient
        $transient_key = 'azevent_github_latest_release_' . md5($this->username . $this->repository);
        delete_transient($transient_key);
        delete_transient($transient_key . '_failed');

        // 3. Update token from DB in case it was just saved
        $saved_token = get_option('azevent_github_token', '');
        if (!empty($saved_token)) {
            $this->access_token = $saved_token;
        }

        // 4. Fetch latest release directly from GitHub API
        $repo_info = $this->get_repository_info();

        if ($repo_info === false) {
            wp_send_json_error(array(
                'message' => '❌ ' . ($this->github_error ?: 'Không lấy được thông tin Release từ GitHub.'),
                'token_set' => !empty($this->access_token) ? 'Có' : 'Chưa có',
                'repository' => $this->username . '/' . $this->repository,
            ));
        }

        $plugin_data  = $this->get_plugin_data();
        $current_ver  = $plugin_data['Version'];
        $latest_ver   = ltrim($repo_info->tag_name, 'v');
        $has_update   = version_compare($current_ver, $latest_ver, '<');

        // 5. Force WordPress to re-populate the transient right now
        wp_update_plugins();

        wp_send_json_success(array(
            'current_version' => $current_ver,
            'latest_version'  => $latest_ver,
            'has_update'      => $has_update,
            'release_url'     => $repo_info->html_url,
            'published_at'    => $repo_info->published_at,
            'message'         => $has_update
                ? "🎉 Có bản cập nhật mới: v{$latest_ver}! Vào Plugins → Update now."
                : "✅ Plugin đang là bản mới nhất (v{$current_ver})",
        ));
    }

    /**
     * Add settings page for GitHub token
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'azevent-seo-settings',
            'GitHub Updates',
            'GitHub Updates',
            'manage_options',
            'azevent-github-updates',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('azevent_github_settings', 'azevent_github_token');
        register_setting('azevent_github_settings', 'azevent_github_username');
        register_setting('azevent_github_settings', 'azevent_github_repo');
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
?>
        <div class="wrap">
            <h1>GitHub Auto-Update Settings</h1>
            <p>Nhập repository GitHub chứa bản Release của plugin. Repo public không cần Personal Access Token.</p>

            <form method="post" action="options.php">
                <?php settings_fields('azevent_github_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="azevent_github_username">GitHub Username</label>
                        </th>
                        <td>
                            <input type="text" id="azevent_github_username" name="azevent_github_username"
                                value="<?php echo esc_attr(get_option('azevent_github_username', $this->username)); ?>"
                                class="regular-text" />
                            <p class="description">Your GitHub username or organization name</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="azevent_github_repo">Repository Name</label>
                        </th>
                        <td>
                            <input type="text" id="azevent_github_repo" name="azevent_github_repo"
                                value="<?php echo esc_attr(get_option('azevent_github_repo', $this->repository)); ?>"
                                class="regular-text" />
                            <p class="description">The name of your GitHub repository</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="azevent_github_token">Personal Access Token</label>
                        </th>
                        <td>
                            <input type="password" id="azevent_github_token" name="azevent_github_token"
                                value="<?php echo esc_attr(get_option('azevent_github_token', '')); ?>" class="regular-text" />
                            <p class="description">
                                Không cần với repository public. Bắt buộc nếu repository private.
                                <a href="https://github.com/settings/tokens/new" target="_blank">Create a token</a>
                                với quyền đọc repository phù hợp.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr>

            <h2>Current Status</h2>
            <table class="widefat">
                <tr>
                    <th style="width: 200px;">Current Version</th>
                    <td>
                        <strong><?php echo esc_html($this->get_plugin_data()['Version']); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th>Repository URL</th>
                    <td>
                        <a href="https://github.com/<?php echo esc_attr($this->username); ?>/<?php echo esc_attr($this->repository); ?>"
                            target="_blank">
                            https://github.com/<?php echo esc_attr($this->username); ?>/<?php echo esc_attr($this->repository); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th>GitHub Token</th>
                    <td>
                        <?php
                        $token = get_option('azevent_github_token', '');
                        if (!empty($token)) {
                            echo '<span style="color:green;">✅ Đã cấu hình (' . substr($token, 0, 8) . '...)</span>';
                        } else {
                            echo '<span style="color:#996800;">⚪ Chưa có token — bình thường với repository public</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Force Check Update</th>
                    <td>
                        <button type="button" id="azevent-force-check-btn" class="button button-primary">
                            🔄 Kiểm tra ngay (Clear Cache)
                        </button>
                        <span id="azevent-check-spinner" style="display:none; margin-left:8px;">
                            <span class="spinner is-active" style="float:none; vertical-align:middle;"></span>
                            Đang kiểm tra GitHub...
                        </span>
                        <div id="azevent-check-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
                <tr style="background:#fff8e1;">
                    <th>⚠️ Fix Permissions</th>
                    <td>
                        <button type="button" id="azevent-fix-perms-btn" class="button button-secondary" style="border-color:#f59e0b; color:#92400e;">
                            🔧 Fix Permissions Now
                        </button>
                        <span id="azevent-fix-perms-spinner" style="display:none; margin-left:8px;">
                            <span class="spinner is-active" style="float:none; vertical-align:middle;"></span>
                            Đang fix...
                        </span>
                        <div id="azevent-fix-perms-result" style="margin-top: 8px;"></div>
                        <p class="description" style="margin-top:6px;">
                            Nhấn nút này <strong>trước khi upload ZIP thủ công</strong> nếu gặp lỗi "The update cannot be installed because some files could not be copied."<br>
                            Nút này sẽ tự động fix quyền truy cập cho toàn bộ thư mục plugin.
                        </p>
                    </td>
                </tr>
            </table>

            <script type="text/javascript">
                document.getElementById('azevent-force-check-btn').addEventListener('click', function() {
                    var btn = this;
                    var spinner = document.getElementById('azevent-check-spinner');
                    var result = document.getElementById('azevent-check-result');

                    btn.disabled = true;
                    spinner.style.display = 'inline-block';
                    result.innerHTML = '';

                    var data = new FormData();
                    data.append('action', 'azevent_force_update_check');
                    data.append('nonce', '<?php echo wp_create_nonce('azevent_force_update'); ?>');

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: data,
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(resp) {
                            spinner.style.display = 'none';
                            btn.disabled = false;

                            if (resp.success) {
                                var d = resp.data;
                                var color = d.has_update ? '#d63638' : '#00a32a';
                                var html = '<div style="padding:10px; background:#f6f7f7; border-left: 4px solid ' + color + '; margin-top:5px;">';
                                html += '<strong>' + d.message + '</strong><br>';
                                html += 'Version hiện tại: <code>' + d.current_version + '</code> | ';
                                html += 'Version GitHub: <code>' + d.latest_version + '</code><br>';
                                html += 'Phát hành lúc: ' + d.published_at + '<br>';
                                if (d.has_update) {
                                    html += '<a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary" style="margin-top:6px;">→ Vào Plugins để Update</a>';
                                }
                                html += '</div>';
                                result.innerHTML = html;
                            } else {
                                result.innerHTML = '<div style="padding:10px; background:#fef0f0; border-left:4px solid #d63638;">' +
                                resp.data.message + '<br>Repository: <code>' + (resp.data.repository || 'N/A') + '</code>' +
                                '<br>Token status: ' + (resp.data.token_set || 'N/A') + '</div>';
                            }
                        })
                        .catch(function(err) {
                            spinner.style.display = 'none';
                            btn.disabled = false;
                            result.innerHTML = '<div style="color:red;">Lỗi kết nối: ' + err + '</div>';
                        });
                });

                // Fix Permissions button
                document.getElementById('azevent-fix-perms-btn').addEventListener('click', function() {
                    var btn = this;
                    var spinner = document.getElementById('azevent-fix-perms-spinner');
                    var result = document.getElementById('azevent-fix-perms-result');

                    btn.disabled = true;
                    spinner.style.display = 'inline-block';
                    result.innerHTML = '';

                    var data = new FormData();
                    data.append('action', 'azevent_fix_plugin_permissions');
                    data.append('nonce', '<?php echo wp_create_nonce('azevent_fix_permissions'); ?>');

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: data,
                            credentials: 'same-origin'
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(resp) {
                            spinner.style.display = 'none';
                            btn.disabled = false;
                            if (resp.success) {
                                result.innerHTML = '<div style="padding:10px; background:#f0fdf4; border-left:4px solid #16a34a;">' +
                                    '<strong>' + resp.data.message + '</strong><br>' +
                                    '<small>Path: ' + resp.data.path + '</small><br><br>' +
                                    '<strong>→ Bây giờ hãy upload ZIP plugin!</strong>' +
                                    '</div>';
                            } else {
                                result.innerHTML = '<div style="padding:10px; background:#fef0f0; border-left:4px solid #d63638;">' + resp.data.message + '</div>';
                            }
                        })
                        .catch(function(err) {
                            spinner.style.display = 'none';
                            btn.disabled = false;
                            result.innerHTML = '<div style="color:red;">Lỗi: ' + err + '</div>';
                        });
                });
            </script>

            <hr>

            <h2>How to Use</h2>
            <ol>
                <li><strong>Create a GitHub Personal Access Token:</strong>
                    <ul>
                        <li>Go to <a href="https://github.com/settings/tokens/new" target="_blank">GitHub Token Settings</a>
                        </li>
                        <li>Click "Generate new token (classic)"</li>
                        <li>Give it a name like "WordPress Plugin Updates"</li>
                        <li>Select the <code>repo</code> scope (full control of private repositories)</li>
                        <li>Click "Generate token" and copy the token</li>
                    </ul>
                </li>
                <li><strong>Configure Settings:</strong>
                    <ul>
                        <li>Enter your GitHub username/organization</li>
                        <li>Enter your repository name</li>
                        <li>Paste your Personal Access Token</li>
                        <li>Click "Save Settings"</li>
                    </ul>
                </li>
                <li><strong>Create Releases:</strong>
                    <ul>
                        <li>Go to your GitHub repository</li>
                        <li>Click "Releases" → "Create a new release"</li>
                        <li>Tag version: <code>v1.0.0</code> (must start with 'v')</li>
                        <li>Add release notes (will be shown as changelog)</li>
                        <li>Publish release</li>
                    </ul>
                </li>
                <li><strong>Update Plugin:</strong>
                    <ul>
                        <li>WordPress will automatically check for updates</li>
                        <li>You'll see update notifications in the Plugins page</li>
                        <li>Click "Update now" to install the latest version</li>
                    </ul>
                </li>
            </ol>

            <div class="notice notice-info inline">
                <p><strong>Note:</strong> Your repository can remain private. The Personal Access Token allows WordPress to
                    access your private releases securely.</p>
            </div>
        </div>
<?php
    }
}
