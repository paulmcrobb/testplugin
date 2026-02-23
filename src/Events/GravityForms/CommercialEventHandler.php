<?php
declare(strict_types=1);

namespace Solas\Portal\Events\GravityForms;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class CommercialEventHandler {

    public static function register(): void {
        add_filter('gform_validation', [self::class, 'validate']);
        $formId = (int) (defined('SOLAS_GF_COMMERCIAL_EVENT_FORM_ID') ? SOLAS_GF_COMMERCIAL_EVENT_FORM_ID : 9);
        add_action('gform_pre_submission_filter_' . $formId, [self::class, 'preSubmission']);
        add_action('gform_after_submission_' . $formId, [self::class, 'afterSubmissionLogo'], 20, 2);
        add_action('gform_after_submission', [self::class, 'afterSubmission'], 10, 2);
    }

    public static function validate($validationResult) {
        $form = $validationResult['form'] ?? null;
        if (!$form || (int) ($form['id'] ?? 0) !== (int) (defined('SOLAS_GF_COMMERCIAL_EVENT_FORM_ID') ? SOLAS_GF_COMMERCIAL_EVENT_FORM_ID : 9)) {
            return $validationResult;
        }

        if (!is_user_logged_in()) {
            $validationResult['is_valid'] = false;
            if (function_exists('solas_events_attach_gf_error')) {
                solas_events_attach_gf_error($form, 1, 'You must be logged in to submit a commercial event.');
            }
            $validationResult['form'] = $form;
            return $validationResult;
        }

        $validationResult['form'] = $form;
        return $validationResult;
    }

    public static function preSubmission($form) {
        $eventPostFieldId = (int) (defined('SOLAS_GF_COMM_FIELD_EVENT_POST_ID') ? SOLAS_GF_COMM_FIELD_EVENT_POST_ID : 29);
        $submissionTypeFieldId = (int) (defined('SOLAS_GF_COMM_FIELD_SUBMISSION_TYPE') ? SOLAS_GF_COMM_FIELD_SUBMISSION_TYPE : 30);
        $checkoutStartedFieldId = (int) (defined('SOLAS_GF_COMM_FIELD_CHECKOUT_STARTED') ? SOLAS_GF_COMM_FIELD_CHECKOUT_STARTED : 32);
        $createdByFieldId = (int) (defined('SOLAS_GF_COMM_FIELD_CREATED_BY') ? SOLAS_GF_COMM_FIELD_CREATED_BY : 33);

        $existingPostId = isset($_POST['input_' . $eventPostFieldId]) ? (int) $_POST['input_' . $eventPostFieldId] : 0;

        if ($existingPostId > 0 && get_post_type($existingPostId) === (defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event')) {

            if (empty($_POST['input_' . $submissionTypeFieldId])) {
                $_POST['input_' . $submissionTypeFieldId] = 'commercial';
            }
            if (empty($_POST['input_' . $createdByFieldId]) && is_user_logged_in()) {
                $_POST['input_' . $createdByFieldId] = (string) get_current_user_id();
            }
            if (!isset($_POST['input_' . $checkoutStartedFieldId]) || $_POST['input_' . $checkoutStartedFieldId] === '') {
                $_POST['input_' . $checkoutStartedFieldId] = '0';
            }
            return $form;
        }

        $userId = is_user_logged_in() ? get_current_user_id() : 0;

        $postId = wp_insert_post([
            'post_type'    => defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event',
            'post_status'  => 'draft',
            'post_title'   => 'Commercial Event (Pending Payment)',
            'post_content' => '',
            'post_author'  => $userId ?: 0,
        ]);

        if (!is_wp_error($postId) && $postId) {
            $_POST['input_' . $eventPostFieldId] = (string) $postId;
            $_POST['input_' . $submissionTypeFieldId] = 'commercial';
            $_POST['input_' . $checkoutStartedFieldId] = '0';
            $_POST['input_' . $createdByFieldId] = $userId ? (string) $userId : ($_POST['input_' . $createdByFieldId] ?? '');

            update_post_meta((int) $postId, 'solas_event_source', 'commercial');
            update_post_meta((int) $postId, 'solas_event_commercial', 1);
            update_post_meta((int) $postId, 'solas_event_member_submitted', 0);
            update_post_meta((int) $postId, 'solas_event_featured_selected', 0);
            update_post_meta((int) $postId, 'is_sticky', 0);
        }

        return $form;
    }

    public static function afterSubmissionLogo($entry, $form): void {
        $postId = (int) rgar($entry, (string) (defined('SOLAS_GF_COMM_FIELD_EVENT_POST_ID') ? SOLAS_GF_COMM_FIELD_EVENT_POST_ID : 29));
        if (!$postId || get_post_type($postId) !== (defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event')) return;

        $logoFieldId = (int) (defined('SOLAS_GF_COMM_FIELD_LOGO') ? SOLAS_GF_COMM_FIELD_LOGO : 34);
        $fileUrl = rgar($entry, (string) $logoFieldId);
        if (empty($fileUrl)) return;

        if (has_post_thumbnail($postId)) return;

        $url = '';
        if (is_string($fileUrl) && $fileUrl !== '') {
            $maybe = json_decode($fileUrl, true);
            if (is_array($maybe) && !empty($maybe)) {
                $url = (string) $maybe[0];
            } else {
                $url = (string) $fileUrl;
            }
        } elseif (is_array($fileUrl) && !empty($fileUrl)) {
            $url = (string) $fileUrl[0];
        }

        $url = trim($url);
        if ($url === '') return;

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attId = media_sideload_image($url, $postId, null, 'id');
        if (!is_wp_error($attId) && $attId) {
            set_post_thumbnail($postId, (int) $attId);
            update_post_meta($postId, 'solas_event_logo_attachment_id', (int) $attId);
        }
    }

    public static function afterSubmission($entry, $form): void {
        $formId = (int) ($form['id'] ?? 0);
        if ($formId !== (int) (defined('SOLAS_GF_COMMERCIAL_EVENT_FORM_ID') ? SOLAS_GF_COMMERCIAL_EVENT_FORM_ID : 9)) return;

        if ((int) (defined('SOLAS_COMMERCIAL_EVENT_PRODUCT_ID') ? SOLAS_COMMERCIAL_EVENT_PRODUCT_ID : 0) <= 0) return;

        $postId = (int) rgar($entry, (string) (defined('SOLAS_GF_COMM_FIELD_EVENT_POST_ID') ? SOLAS_GF_COMM_FIELD_EVENT_POST_ID : 29));
        if ($postId <= 0 || get_post_type($postId) !== (defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event')) return;

        $checkoutStartedFieldId = (int) (defined('SOLAS_GF_COMM_FIELD_CHECKOUT_STARTED') ? SOLAS_GF_COMM_FIELD_CHECKOUT_STARTED : 32);
        $checkoutStarted = (string) rgar($entry, (string) $checkoutStartedFieldId);
        if ($checkoutStarted === '1') return;

        if (!function_exists('solas_events_extract_entry_data') || !function_exists('solas_events_save_event_meta') || !function_exists('solas_events_set_event_types') || !function_exists('solas_events_add_commercial_listing_to_cart') || !function_exists('solas_events_redirect_to_checkout')) {
            return;
        }

        $userId = get_current_user_id();
        $data = solas_events_extract_entry_data($entry, $form);

        $featuredFieldId = (int) (defined('SOLAS_GF_COMM_FIELD_FEATURED') ? SOLAS_GF_COMM_FIELD_FEATURED : 36);
        $featuredRaw = rgar($entry, (string) $featuredFieldId);
        $featuredSelected = !empty($featuredRaw) && $featuredRaw !== '0';

        wp_update_post([
            'ID'           => $postId,
            'post_title'   => wp_strip_all_tags((string) ($data['title'] ?? '')),
            'post_content' => (string) ($data['description'] ?? ''),
            'post_status'  => 'draft',
            'post_author'  => $userId ?: (int) get_post_field('post_author', $postId),
        ]);

        solas_events_save_event_meta((int) $postId, $data, [
            'member_submitted' => 0,
            'commercial'       => 1,
            'source'           => 'commercial',
            'published_by'     => 0,
        ]);

        solas_events_set_event_types((int) $postId, $data['event_types'] ?? []);
        update_post_meta((int) $postId, 'solas_event_format', $data['event_format'] ?? []);
        update_post_meta((int) $postId, 'solas_event_featured_selected', $featuredSelected ? 1 : 0);
        update_post_meta((int) $postId, 'is_sticky', 0);

        update_post_meta((int) $postId, 'solas_entry_id', rgar($entry, 'id'));
        update_post_meta((int) $postId, 'solas_created_at', wp_date('c'));
        update_post_meta((int) $postId, 'solas_source', 'gravity_forms');

        $added = solas_events_add_commercial_listing_to_cart((int) $postId, (bool) $featuredSelected);

        if ($added && class_exists('GFAPI')) {
            GFAPI::update_entry_field((int) rgar($entry, 'id'), $checkoutStartedFieldId, '1');
        }

        solas_events_redirect_to_checkout();
    }
}
