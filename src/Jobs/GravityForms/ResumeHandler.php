<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\GravityForms;

defined('ABSPATH') || exit;

final class ResumeHandler {

    public static function register(): void {
        add_action('gform_after_submission_' . SOLAS_GF_RESUME_FORM_ID, [self::class, 'afterSubmission'], 10, 2);
    }

    public static function afterSubmission($entry, $form): void {
        if (!is_user_logged_in()) return;

    $user_id  = (int) get_current_user_id();
    $entry_id = (int) rgar($entry, 'id');

    $resume_id = (int) rgar($entry, (string) SOLAS_GF_RESUME_ID);
    $is_edit   = $resume_id > 0;

    $title = sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_RESUME_TITLE));
    if ($title === '') $title = 'Resume – ' . wp_date('Y-m-d H:i');

    // Create or update post
    if ($is_edit) {
        if (!solas_resume_user_owns_resume($resume_id, $user_id)) return;

        wp_update_post([
            'ID'         => $resume_id,
            'post_title' => $title,
        ]);
    } else {
        $resume_id = (int) wp_insert_post([
            'post_type'   => 'solas_resume',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'post_title'  => $title,
        ], true);

        if (!$resume_id || is_wp_error($resume_id)) return;

        update_post_meta($resume_id, 'solas_created_at', wp_date('c'));
    }

    // Text/meta
    $name    = sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_RESUME_NAME));
    $email   = sanitize_email((string) rgar($entry, (string) SOLAS_GF_RESUME_EMAIL));
    $role    = sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_RESUME_ROLE));
    $content = wp_kses_post((string) rgar($entry, (string) SOLAS_GF_RESUME_CONTENT));

    $website  = esc_url_raw((string) rgar($entry, (string) SOLAS_GF_RESUME_WEBSITE));
    $linkedin = esc_url_raw((string) rgar($entry, (string) SOLAS_GF_RESUME_LINKEDIN));
    $facebook = esc_url_raw((string) rgar($entry, (string) SOLAS_GF_RESUME_FACEBOOK));
    $x        = esc_url_raw((string) rgar($entry, (string) SOLAS_GF_RESUME_X));
    $tiktok   = esc_url_raw((string) rgar($entry, (string) SOLAS_GF_RESUME_TIKTOK));
    $video    = esc_url_raw((string) rgar($entry, (string) SOLAS_GF_RESUME_VIDEO));

    update_post_meta($resume_id, 'solas_resume_name', $name);
    update_post_meta($resume_id, 'solas_resume_email', $email);
    update_post_meta($resume_id, 'solas_resume_role', $role);
    update_post_meta($resume_id, 'solas_resume_content', $content);

    update_post_meta($resume_id, 'solas_resume_website', $website);
    update_post_meta($resume_id, 'solas_resume_linkedin', $linkedin);
    update_post_meta($resume_id, 'solas_resume_facebook', $facebook);
    update_post_meta($resume_id, 'solas_resume_x', $x);
    update_post_meta($resume_id, 'solas_resume_tiktok', $tiktok);
    update_post_meta($resume_id, 'solas_resume_video_url', $video);

    // Files: only overwrite if a new one was uploaded
    $photo_new = solas_gf_file_to_url((string) rgar($entry, (string) SOLAS_GF_RESUME_PHOTO));
    $cv_new    = solas_gf_file_to_url((string) rgar($entry, (string) SOLAS_GF_RESUME_CV));

    if ($photo_new) update_post_meta($resume_id, 'solas_resume_photo_url', esc_url_raw($photo_new));
    if ($cv_new)    update_post_meta($resume_id, 'solas_resume_file_url', esc_url_raw($cv_new));

    // Education array (up to 3)
    $education = [];

    $e1 = [
        'institution'   => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_INST1)),
        'certification' => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_CERT1)),
        'start'         => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_START1)),
        'end'           => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_END1)),
        'notes'         => sanitize_textarea_field((string) rgar($entry, (string) SOLAS_GF_EDU_NOTES1)),
    ];
    if (array_filter($e1)) $education[] = $e1;

    $e2 = [
        'institution'   => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_INST2)),
        'certification' => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_CERT2)),
        'start'         => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_START2)),
        'end'           => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_END2)),
        'notes'         => sanitize_textarea_field((string) rgar($entry, (string) SOLAS_GF_EDU_NOTES2)),
    ];
    if (array_filter($e2)) $education[] = $e2;

    $e3 = [
        'institution'   => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_INST3)),
        'certification' => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_CERT3)),
        'start'         => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_START3)),
        'end'           => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EDU_END3)),
        'notes'         => sanitize_textarea_field((string) rgar($entry, (string) SOLAS_GF_EDU_NOTES3)),
    ];
    if (array_filter($e3)) $education[] = $e3;

    update_post_meta($resume_id, 'solas_resume_education', wp_json_encode($education));

    // Experience array (up to 3)
    $experience = [];

    $x1 = [
        'employer' => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_EMP1)),
        'role'     => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_ROLE1)),
        'start'    => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_START1)),
        'end'      => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_END1)),
        'notes'    => sanitize_textarea_field((string) rgar($entry, (string) SOLAS_GF_EXP_NOTES1)),
    ];
    if (array_filter($x1)) $experience[] = $x1;

    $x2 = [
        'employer' => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_EMP2)),
        'role'     => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_ROLE2)),
        'start'    => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_START2)),
        'end'      => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_END2)),
        'notes'    => sanitize_textarea_field((string) rgar($entry, (string) SOLAS_GF_EXP_NOTES2)),
    ];
    if (array_filter($x2)) $experience[] = $x2;

    $x3 = [
        'employer' => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_EMP3)),
        'role'     => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_ROLE3)),
        'start'    => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_START3)),
        'end'      => sanitize_text_field((string) rgar($entry, (string) SOLAS_GF_EXP_END3)),
        'notes'    => sanitize_textarea_field((string) rgar($entry, (string) SOLAS_GF_EXP_NOTES3)),
    ];
    if (array_filter($x3)) $experience[] = $x3;

    update_post_meta($resume_id, 'solas_resume_experience', wp_json_encode($experience));

    // Audit
    update_post_meta($resume_id, 'solas_entry_id', $entry_id);
    update_post_meta($resume_id, 'solas_updated_at', wp_date('c'));
    update_post_meta($resume_id, 'solas_source', 'gravity_forms');
    }
}
