<?php
/**
 * Plugin Name: Yabby Quiz
 * Description: Plugin for handling Weekly Quiz - Yabby Casino Edition.
 * Version: 1.0.0
 * Author: Ivan
 * Requires PHP: 8.0
 * Requires at least: 6.3
 */

if (!defined('ABSPATH')) exit;

/*Generate random quiz ID */
function yabby_quiz_generate_id(): string {
    return 'quiz_' . wp_generate_password(8, false);
}

/*Get all quizzes - sa cachingom */
function yabby_quiz_get_all(): array {
    $cached = get_transient('yabby_quiz_all_cache');
    if ($cached !== false) {
        return $cached;
    }
    
    $quizzes = get_option('yabby_quiz_all', []);
    set_transient('yabby_quiz_all_cache', $quizzes, HOUR_IN_SECONDS);
    return $quizzes;
}

/*Save all quizzes */
function yabby_quiz_save_all(array $quizzes) {
    delete_transient('yabby_quiz_all_cache');
    update_option('yabby_quiz_all', $quizzes, false);
}

/*Get quiz by ID */
function yabby_quiz_get_by_id($id): ?array {
    $all = yabby_quiz_get_all();
    return $all[$id] ?? null;
}

/*Save or update single quiz */
function yabby_quiz_save_quiz($id, array $data) {
    $all = get_option('yabby_quiz_all', []);
    $all[$id] = $data;
    yabby_quiz_save_all($all);
}

/*Delete quiz */
function yabby_quiz_delete_quiz($id) {
    $all = get_option('yabby_quiz_all', []);
    unset($all[$id]);
    yabby_quiz_save_all($all);
}

/*Admin Menu Setup*/
add_action('admin_menu', function (){
    add_menu_page(
        'Yabby Quiz',
        'Yabby Quiz',
        'manage_options',
        'yabby-quiz',
        'yabby_quiz_admin_page',
        'dashicons-welcome-learn-more',
        65
    );
});

/*Admin Page */
function yabby_quiz_admin_page() {
    if (!current_user_can('manage_options')) return;

    $action = $_GET['action'] ?? 'list';
    $edit_id = $_GET['quiz_id'] ?? null;

    if (isset($_POST['yabby_quiz_submit'])) {
        check_admin_referer('yabby_quiz_save');

        $quiz_id = sanitize_text_field($_POST['quiz_id'] ?? '');
        $ctaUrl  = esc_url_raw($_POST['yabbyq_cta'] ?? '');
        $q_text  = sanitize_text_field($_POST['yabbyq_q_text'] ?? '');
        $opt1    = sanitize_text_field($_POST['yabbyq_opt1'] ?? '');
        $opt2    = sanitize_text_field($_POST['yabbyq_opt2'] ?? '');
        $opt3    = sanitize_text_field($_POST['yabbyq_opt3'] ?? '');
        $opt4    = sanitize_text_field($_POST['yabbyq_opt4'] ?? '');
        $options = array_values(array_filter([$opt1, $opt2, $opt3, $opt4], fn($x) => $x !== ''));
        $correct = sanitize_text_field($_POST['yabbyq_correct'] ?? '');
        $reward  = sanitize_text_field($_POST['yabbyq_reward'] ?? '');
        $status  = isset($_POST['yabbyq_status']) ? 1 : 0;

        $errors = [];
        if ($status === 1) {
            if ($correct === '' || !in_array($correct, $options, true)) {
                $errors[] = 'Correct answer must match one of the options when the quiz is active.';
            }
            if ($reward === '') {
                $errors[] = 'Reward code is required when the quiz is active.';
            }
            if ($q_text === '') {
                $errors[] = 'Question text is required when the quiz is active.';
            }
            if (count($options) < 2) {
                $errors[] = 'At least 2 answer options are required when the quiz is active.';
            }
        }

        if ($errors) {
            foreach ($errors as $e) add_settings_error('yabby_quiz', 'yabbyq_err', $e, 'error');
        } else {
            if (empty($quiz_id)) {
                $quiz_id = yabby_quiz_generate_id();
            }

            $quizData = [
                'id'       => $quiz_id,
                'status'   => $status,
                'ctaUrl'   => $ctaUrl,
                'question' => [
                    'text'    => $q_text,
                    'options' => $options,
                    'correct' => $correct,
                    'reward'  => $reward,
                ],
                '_meta' => [
                    'updated_by' => wp_get_current_user()->user_login,
                    'updated_at' => current_time('mysql'),
                ],
            ];

            yabby_quiz_save_quiz($quiz_id, $quizData);
            
            $msg = empty($edit_id) ? 'Quiz created successfully!' : 'Quiz updated successfully!';
            add_settings_error('yabby_quiz', 'yabbyq_saved', $msg, 'updated');
            
            wp_redirect(admin_url('admin.php?page=yabby-quiz&saved=1'));
            exit;
        }
    }

    if ($action === 'delete' && $edit_id) {
        check_admin_referer('yabby_quiz_delete_' . $edit_id);
        yabby_quiz_delete_quiz($edit_id);
        add_settings_error('yabby_quiz', 'yabbyq_deleted', 'Quiz deleted successfully!', 'updated');
        wp_redirect(admin_url('admin.php?page=yabby-quiz&deleted=1'));
        exit;
    }

    if ($action === 'edit' || $action === 'new') {
        yabby_quiz_render_edit_form($edit_id);
    } else {
        yabby_quiz_render_list();
    }
}

function yabby_quiz_render_list() {
    $all_quizzes = yabby_quiz_get_all();
    settings_errors('yabby_quiz');
    ?>
<div class="wrap">
    <h1>Yabby Quiz — All Quizzes
        <a href="<?php echo admin_url('admin.php?page=yabby-quiz&action=new'); ?>" class="page-title-action">Add New Quiz</a>
    </h1>

    <?php if (empty($all_quizzes)): ?>
    <div class="notice notice-info">
        <p>No quizzes created yet. <a href="<?php echo admin_url('admin.php?page=yabby-quiz&action=new'); ?>">Create your first quiz!</a></p>
    </div>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Quiz ID</th>
                <th>Question</th>
                <th>Status</th>
                <th>Shortcode</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_quizzes as $id => $quiz): ?>
            <tr>
                <td><code><?php echo esc_html($id); ?></code></td>
                <td><?php echo esc_html(wp_trim_words($quiz['question']['text'] ?? 'N/A', 10)); ?></td>
                <td>
                    <?php if ($quiz['status'] === 1): ?>
                    <span style="color: #46b450; font-weight: 600;">● Active</span>
                    <?php else: ?>
                    <span style="color: #999;">○ Inactive</span>
                    <?php endif; ?>
                </td>
                <td><code>[yabby_quiz id="<?php echo esc_attr($id); ?>"]</code></td>
                <td><?php echo esc_html($quiz['_meta']['updated_at'] ?? 'N/A'); ?></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=yabby-quiz&action=edit&quiz_id=' . urlencode($id)); ?>">Edit</a>
                    |
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=yabby-quiz&action=delete&quiz_id=' . urlencode($id)), 'yabby_quiz_delete_' . $id); ?>"
                        onclick="return confirm('Are you sure you want to delete this quiz?');"
                        style="color: #b32d2e;">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
}

function yabby_quiz_render_edit_form($edit_id) {
    $is_new = empty($edit_id);
    $quiz = $is_new ? [] : yabby_quiz_get_by_id($edit_id);
    
    if (!$is_new && !$quiz) {
        echo '<div class="wrap"><h1>Quiz Not Found</h1><p><a href="' . admin_url('admin.php?page=yabby-quiz') . '">Back to list</a></p></div>';
        return;
    }

    $q = $quiz['question'] ?? [];
    $opts = array_pad($q['options'] ?? [], 4, '');

    if (!empty($_POST)) {
        $opts = [
            sanitize_text_field($_POST['yabbyq_opt1'] ?? ''),
            sanitize_text_field($_POST['yabbyq_opt2'] ?? ''),
            sanitize_text_field($_POST['yabbyq_opt3'] ?? ''),
            sanitize_text_field($_POST['yabbyq_opt4'] ?? ''),
        ];
        if (isset($_POST['yabbyq_correct'])) {
            $q['correct'] = sanitize_text_field($_POST['yabbyq_correct']);
        }
        if (isset($_POST['yabbyq_q_text'])) {
            $q['text'] = sanitize_text_field($_POST['yabbyq_q_text']);
        }
        if (isset($_POST['yabbyq_cta'])) {
            $quiz['ctaUrl'] = esc_url_raw($_POST['yabbyq_cta']);
        }
        if (isset($_POST['yabbyq_reward'])) {
            $q['reward'] = sanitize_text_field($_POST['yabbyq_reward']);
        }
    }

    $id = $quiz['id'] ?? null;
    settings_errors('yabby_quiz');
    ?>

<div class="wrap">
    <h1><?php echo $is_new ? 'Create New Quiz' : 'Edit Quiz'; ?></h1>
    <p><a href="<?php echo admin_url('admin.php?page=yabby-quiz'); ?>">← Back to all quizzes</a></p>

    <?php if ($id): ?>
    <div class="notice notice-success" style="padding:12px 16px;margin-bottom:20px;">
        <strong>Quiz ID:</strong> <code><?php echo esc_html($id); ?></code><br>
        Use shortcode: <code>[yabby_quiz id="<?php echo esc_html($id); ?>"]</code>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('yabby_quiz_save'); ?>
        <input type="hidden" name="quiz_id" value="<?php echo esc_attr($id ?? ''); ?>">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <label>
                            <input type="checkbox" name="yabbyq_status" value="1"
                                <?php checked(1, $quiz['status'] ?? 0); ?>>
                            Active
                        </label>
                        <p class="description">Inactive quizzes will show "This quiz has ended" message.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">CTA URL</th>
                    <td>
                        <input type="url" name="yabbyq_cta" class="regular-text"
                            placeholder="https://www.yabbycasino.com/cashier"
                            value="<?php echo esc_attr($quiz['ctaUrl'] ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="yabbyq_q_text">Question</label></th>
                    <td>
                        <input type="text" id="yabbyq_q_text" name="yabbyq_q_text" class="regular-text"
                            placeholder="Enter the weekly question here..."
                            value="<?php echo esc_attr($q['text'] ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Answers</th>
                    <td>
                        <div style="display:grid;grid-template-columns:1fr auto;gap:8px;max-width:780px;">
                            <?php for($i = 0; $i < 4; $i++): ?>
                            <input type="text" name="yabbyq_opt<?php echo $i+1; ?>"
                                class="regular-text yabbyq-answer-input" placeholder="Answer <?php echo $i+1; ?>"
                                value="<?php echo esc_attr($opts[$i]); ?>" data-index="<?php echo $i; ?>">

                            <label style="display:flex;align-items:center;gap:6px;white-space:nowrap;">
                                <input type="radio" name="yabbyq_correct" value="<?php echo esc_attr($opts[$i]); ?>"
                                    class="yabbyq-correct-radio" data-index="<?php echo $i; ?>"
                                    <?php checked($opts[$i], $q['correct'] ?? ''); ?>
                                    <?php if(empty($opts[$i])) echo 'disabled'; ?>>
                                <span style="font-size:13px;color:#646970;">Correct</span>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <p class="description">Enter at least 2 answers and mark which one is correct.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="yabbyq_reward">Reward code</label></th>
                    <td>
                        <input type="text" id="yabbyq_reward" name="yabbyq_reward" class="regular-text"
                            placeholder="EXAMPLE100" value="<?php echo esc_attr($q['reward'] ?? ''); ?>">
                        <p class="description">The code shown to users who answer correctly.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <button type="submit" name="yabby_quiz_submit" class="button button-primary">
                <?php echo $is_new ? 'Create Quiz' : 'Update Quiz'; ?>
            </button>
        </p>
    </form>

    <script>
    (function() {
        'use strict';
        var inputs = document.querySelectorAll('.yabbyq-answer-input');
        var radios = document.querySelectorAll('.yabbyq-correct-radio');

        inputs.forEach(function(input) {
            input.addEventListener('input', function() {
                var index = input.getAttribute('data-index');
                var radio = document.querySelector('.yabbyq-correct-radio[data-index="' + index + '"]');
                var value = input.value.trim();
                radio.value = value;
                radio.disabled = value === '';
                if (value === '' && radio.checked) {
                    radio.checked = false;
                }
            });
        });
    })();
    </script>
</div>
<?php
}

/*Enqueue assets*/
add_action('wp_enqueue_scripts', function(){
    $base = plugin_dir_url(__FILE__);
    wp_register_style('yabby-quiz-css', $base . 'assets/css/quiz.css', [], '1.0.0');
    wp_register_script('yabby-quiz-js', $base . 'assets/js/quiz.js', [], '1.0.0', true);
});

/*Legacy shortcode support for old [quiz] format*/
add_shortcode('quiz', function($atts){
    $atts = shortcode_atts(['status' => '0'], $atts, 'quiz');
    
    wp_enqueue_style('yabby-quiz-css');
    return '
    <div class="yabbyq-container yabbyq-ended">
        <div class="yabbyq-content">
            <span class="yabbyq-badge">Quiz Closed</span>
            <p class="yabbyq-ended-title">This quiz has ended.</p>
            <p class="yabbyq-ended-subtitle">Check back next week for a new quiz!</p>
        </div>
    </div>
    ';
});

/*Shortcode render*/
add_shortcode('yabby_quiz', function($atts){
    $atts = shortcode_atts(['id' => null], $atts, 'yabby_quiz');
    $quiz_id = $atts['id'];

    if (!$quiz_id) {
        wp_enqueue_style('yabby-quiz-css');
        return '
        <div class="yabbyq-container yabbyq-ended">
            <div class="yabbyq-content">
                <span class="yabbyq-badge">Quiz Closed</span>
                <p class="yabbyq-ended-title">No quiz available.</p>
                <p class="yabbyq-ended-subtitle">Check back later for a new quiz!</p>
            </div>
        </div>
        ';
    }

    $quiz = yabby_quiz_get_by_id($quiz_id);

    if (!$quiz || $quiz['status'] !== 1) {
        wp_enqueue_style('yabby-quiz-css');
        return '
        <div class="yabbyq-container yabbyq-ended">
            <div class="yabbyq-content">
                <span class="yabbyq-badge">Quiz Closed</span>
                <p class="yabbyq-ended-title">This quiz has ended.</p>
                <p class="yabbyq-ended-subtitle">Check back next week for a new quiz!</p>
            </div>
        </div>
        ';
    }

    wp_enqueue_style('yabby-quiz-css');
    wp_enqueue_script('yabby-quiz-js');

    $uid = wp_unique_id('yabbyq_');
    wp_add_inline_script(
        'yabby-quiz-js',
        'window.YABBY_QUIZ = window.YABBY_QUIZ || []; window.YABBY_QUIZ.push(' . wp_json_encode([
            'uid'      => $uid,
            'ctaUrl'   => $quiz['ctaUrl'],
            'question' => $quiz['question']
        ]) . ');',
        'before'
    );

    ob_start(); ?>
<div id="<?php echo esc_attr($uid); ?>" class="yabbyq-container">
    <div class="yabbyq-content">
        <p class="yabbyq-question"></p>
        <div class="yabbyq-options"></div>
        <p class="yabbyq-result" aria-live="polite" style="display:none;"></p>
        <div class="yabbyq-reward" role="status" style="display:none;">
            <strong>Your Reward Code:</strong>
            <span class="yabbyq-reward-code"></span>
        </div>
        <div class="yabbyq-cta" style="display:none;">
            <a class="yabbyq-cta-link" target="_blank" rel="noopener">Claim your reward</a>
        </div>
    </div>
</div>
<?php
    return ob_get_clean();
});