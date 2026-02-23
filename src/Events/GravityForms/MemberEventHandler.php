<?php
declare(strict_types=1);

namespace Solas\Portal\Events\GravityForms;

use Solas\Portal\Events\Gate;

defined('ABSPATH') || exit;

final class MemberEventHandler {

    public static function register(): void {
        add_filter('gform_validation', [self::class, 'validate']);
        add_action('gform_after_submission', [self::class, 'afterSubmission'], 10, 2);
    }

    public static function validate($validationResult) {
        $form = $validationResult['form'] ?? null;
        if (!$form || (int) ($form['id'] ?? 0) !== (int) (defined('SOLAS_GF_MEMBER_EVENT_FORM_ID') ? SOLAS_GF_MEMBER_EVENT_FORM_ID : 3)) {
            return $validationResult;
        }

        if (!is_user_logged_in()) {
            $validationResult['is_valid'] = false;
            if (function_exists('solas_events_attach_gf_error')) {
                solas_events_attach_gf_error($form, 1, 'You must be logged in to submit an event.');
            }
            $validationResult['form'] = $form;
            return $validationResult;
        }

        $userId = get_current_user_id();

        if (!Gate::userHasAllowedRole($userId)) {
            $validationResult['is_valid'] = false;
            if (function_exists('solas_events_attach_gf_error')) {
                solas_events_attach_gf_error($form, 1, 'Only SOLAS members can submit events.');
            }
            $validationResult['form'] = $form;
            return $validationResult;
        }

        if (!Gate::userHasActiveWcMembership($userId)) {
            $validationResult['is_valid'] = false;
            if (function_exists('solas_events_attach_gf_error')) {
                solas_events_attach_gf_error($form, 1, 'Your membership is not currently active, so you cannot submit events.');
            }
            $validationResult['form'] = $form;
            return $validationResult;
        }

        $validationResult['form'] = $form;
        return $validationResult;
    }

    public static function afterSubmission($entry, $form): void {
        $formId = (int) ($form['id'] ?? 0);
        if ($formId !== (int) (defined('SOLAS_GF_MEMBER_EVENT_FORM_ID') ? SOLAS_GF_MEMBER_EVENT_FORM_ID : 3)) return;

        $userId = get_current_user_id();
        if (
            !$userId ||
            !Gate::userHasAllowedRole($userId) ||
            !Gate::userHasActiveWcMembership($userId)
        ) {
            return;
        }

        if (!function_exists('solas_events_extract_entry_data') || !function_exists('solas_events_save_event_meta') || !function_exists('solas_events_set_event_types')) {
            return;
        }

        $data = solas_events_extract_entry_data($entry, $form);

        $postId = wp_insert_post([
            'post_type'    => defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event',
            'post_status'  => 'publish',
            'post_title'   => wp_strip_all_tags((string) ($data['title'] ?? '')),
            'post_content' => (string) ($data['description'] ?? ''),
            'post_author'  => $userId,
        ]);

        if (is_wp_error($postId) || !$postId) return;

        solas_events_save_event_meta((int) $postId, $data, [
            'member_submitted' => 1,
            'commercial'       => 0,
            'source'           => 'member',
            'published_by'     => $userId,
        ]);

        solas_events_set_event_types((int) $postId, $data['event_types'] ?? []);

        update_post_meta((int) $postId, 'solas_entry_id', rgar($entry, 'id'));
        update_post_meta((int) $postId, 'solas_created_at', wp_date('c'));
        update_post_meta((int) $postId, 'solas_source', 'gravity_forms');
    }
}
