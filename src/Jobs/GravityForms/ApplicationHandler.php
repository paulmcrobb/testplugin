<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\GravityForms;

use Solas\Portal\Jobs\Files;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * Gravity Forms Job Application handler.
 *
 * Form + field IDs are defined as constants in includes/jobs-gf-application.php
 * (kept procedural for activation-safe loading).
 */
final class ApplicationHandler {

    public static function register(): void {
        if (!defined('SOLAS_GF_JOB_APPLICATION_FORM_ID')) {
            return;
        }

        add_action('gform_enqueue_scripts_' . SOLAS_GF_JOB_APPLICATION_FORM_ID, [self::class, 'enqueueScripts'], 10, 2);
        add_action('gform_pre_submission_' . SOLAS_GF_JOB_APPLICATION_FORM_ID, [self::class, 'preSubmission'], 10, 1);
        add_action('gform_after_submission_' . SOLAS_GF_JOB_APPLICATION_FORM_ID, [self::class, 'afterSubmission'], 10, 2);
    }

    /**
     * Enforce "single choice" behaviour on a checkbox field (saved CV selection).
     */
    public static function enqueueScripts($form, $is_ajax): void {
        if (is_admin()) {
            return;
        }
        if (!defined('SOLAS_GF_APP_CV_STORED')) {
            return;
        }

        $formId  = (int) rgar($form, 'id');
        $fieldId = (int) SOLAS_GF_APP_CV_STORED;

        $js = <<<JS
(function(){
  function initSolasSingleChoiceCheckbox(){
    var field = document.querySelector('#field_{$formId}_{$fieldId}');
    if(!field) return;
    var boxes = field.querySelectorAll('input[type="checkbox"]');
    if(!boxes || !boxes.length) return;
    boxes.forEach(function(cb){
      cb.addEventListener('change', function(){
        if(!cb.checked) return;
        boxes.forEach(function(other){ if(other !== cb) other.checked = false; });
      });
    });
  }
  document.addEventListener('DOMContentLoaded', initSolasSingleChoiceCheckbox);
  document.addEventListener('gform_post_render', function(e, formId){
    if(parseInt(formId,10) === {$formId}) initSolasSingleChoiceCheckbox();
  });
})();
JS;

        // Gravity Forms front-end loads jQuery; inline is fine.
        wp_add_inline_script('jquery', $js);
    }

    /**
     * Create the application CPT early and stash the ID into POST to prevent duplicates.
     */
    public static function preSubmission($form): void {
        if (!is_user_logged_in()) {
            return;
        }
        // Prevent duplicates if GF retries submission.
        if (!empty($_POST['solas_job_app_post_id'])) {
            return;
        }
        if (!defined('SOLAS_GF_APP_JOB_ID')) {
            return;
        }

        $jobId = (int) rgpost('input_' . SOLAS_GF_APP_JOB_ID);
        if ($jobId <= 0 || get_post_type($jobId) !== 'solas_job') {
            return;
        }

        $userId = (int) get_current_user_id();
        $jobTitle = get_the_title($jobId);
        $title = 'Application – ' . ($jobTitle ?: ('Job #' . $jobId)) . ' – ' . wp_date('Y-m-d H:i');

        $appId = wp_insert_post([
            'post_type'   => 'solas_job_app',
            'post_status' => 'publish',
            'post_author' => $userId,
            'post_title'  => $title,
        ], true);

        if (is_wp_error($appId) || !$appId) {
            error_log('SOLAS JOB APP: wp_insert_post failed in preSubmission');
            return;
        }

        update_post_meta((int) $appId, 'solas_job_id', $jobId);
        update_post_meta((int) $appId, 'solas_candidate_user_id', $userId);
        update_post_meta((int) $appId, 'solas_applied_at', wp_date('c'));
        update_post_meta((int) $appId, 'solas_application_status', 'submitted');

        $_POST['solas_job_app_post_id'] = (string) ((int) $appId);
    }

    /**
     * Map entry to post meta, increment job application count, and send emails.
     */
    public static function afterSubmission($entry, $form): void {
        $entryId = (int) rgar($entry, 'id');
        if ($entryId <= 0) {
            return;
        }

        $appId = isset($_POST['solas_job_app_post_id']) ? (int) $_POST['solas_job_app_post_id'] : 0;
        if ($appId <= 0 || get_post_type($appId) !== 'solas_job_app') {
            error_log('SOLAS JOB APP: missing application post id for entry ' . $entryId);
            return;
        }

        $jobId = 0;
        if (defined('SOLAS_GF_APP_JOB_ID')) {
            $jobId = (int) rgar($entry, (string) SOLAS_GF_APP_JOB_ID);
        }
        if ($jobId <= 0) {
            $jobId = (int) get_post_meta($appId, 'solas_job_id', true);
        }

        $userId = (int) get_current_user_id();

        $email = defined('SOLAS_GF_APP_EMAIL') ? sanitize_email((string) rgar($entry, (string) SOLAS_GF_APP_EMAIL)) : '';
        $coverText = defined('SOLAS_GF_APP_COVER_TEXT') ? (string) rgar($entry, (string) SOLAS_GF_APP_COVER_TEXT) : '';
        $additional = defined('SOLAS_GF_APP_ADDITIONAL') ? (string) rgar($entry, (string) SOLAS_GF_APP_ADDITIONAL) : '';

        // Saved CV selection (checkbox) – treat as single choice.
        $resumeId = 0;
        if (defined('SOLAS_GF_APP_CV_STORED')) {
            $selected = Files::checkboxSelectedValues(SOLAS_GF_APP_CV_STORED);
            if (!empty($selected[0]) && ctype_digit((string) $selected[0])) {
                $resumeId = (int) $selected[0];
            }
        }

        $cvUrl = '';
        if ($resumeId > 0 && get_post_type($resumeId) === 'solas_resume') {
            $resumePost = get_post($resumeId);
            if ($resumePost && (int) $resumePost->post_author === $userId) {
                $cvUrl = (string) get_post_meta($resumeId, 'solas_resume_file_url', true);
            }
        }

        // Upload CV fallback.
        if ($cvUrl === '' && defined('SOLAS_GF_APP_CV_UPLOAD')) {
            $cvUrl = Files::normalizeGfFileValueToUrl(rgar($entry, (string) SOLAS_GF_APP_CV_UPLOAD));
        }

        $coverUrl = '';
        if (defined('SOLAS_GF_APP_COVER_UPLOAD')) {
            $coverUrl = Files::normalizeGfFileValueToUrl(rgar($entry, (string) SOLAS_GF_APP_COVER_UPLOAD));
        }

        $consent = '';
        if (defined('SOLAS_GF_APP_CONSENT')) {
            $consent = sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_APP_CONSENT));
        }

        // Persist
        update_post_meta($appId, 'solas_entry_id', $entryId);
        update_post_meta($appId, 'solas_job_id', $jobId);
        update_post_meta($appId, 'solas_candidate_user_id', $userId);
        update_post_meta($appId, 'solas_applicant_email', $email);
        update_post_meta($appId, 'solas_cover_text', wp_kses_post($coverText));
        update_post_meta($appId, 'solas_additional', wp_kses_post($additional));
        update_post_meta($appId, 'solas_consent', $consent);
        update_post_meta($appId, 'solas_cv_url', esc_url_raw($cvUrl));
        update_post_meta($appId, 'solas_cover_url', esc_url_raw($coverUrl));
        if ($resumeId > 0) {
            update_post_meta($appId, 'solas_resume_id', $resumeId);
        }
        update_post_meta($appId, 'solas_updated_at', wp_date('c'));
        update_post_meta($appId, 'solas_source', 'gravity_forms');

        // Increment job applications counter.
        if ($jobId > 0 && get_post_type($jobId) === 'solas_job') {
            $apps = (int) get_post_meta($jobId, 'solas_applications', true);
            update_post_meta($jobId, 'solas_applications', $apps + 1);
        }

        // Emails (send only once)
        $emailed = get_post_meta($appId, '_solas_application_emails_sent', true) === 'yes';
        if ($emailed) {
            return;
        }

        $jobTitle = $jobId > 0 ? get_the_title($jobId) : 'Job application';

        // Candidate confirmation
        if ($email && function_exists('solas_mail')) {
            $subj = 'Application received: ' . $jobTitle;
            $body = '<p>Thanks — we’ve received your application for <strong>' . esc_html($jobTitle) . '</strong>.</p>';
            $body .= '<p>You can view your applications in your account area.</p>';
            solas_mail($email, $subj, $body);
        }

        // Employer notification
        $employerEmail = '';
        $employerUserId = $jobId > 0 ? (int) get_post_field('post_author', $jobId) : 0;
        if ($employerUserId > 0) {
            $u = get_user_by('id', $employerUserId);
            if ($u) {
                $employerEmail = (string) $u->user_email;
            }
        }
        if ($employerEmail && function_exists('solas_mail')) {
            $subj = 'New application: ' . $jobTitle;
            $body = '<p>A new application has been submitted for <strong>' . esc_html($jobTitle) . '</strong>.</p>';
            $body .= '<p>View applications in your Employer Dashboard.</p>';
            solas_mail($employerEmail, $subj, $body);
        }

        update_post_meta($appId, '_solas_application_emails_sent', 'yes');
    }
}
