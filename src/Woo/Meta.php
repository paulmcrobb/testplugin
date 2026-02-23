<?php
declare(strict_types=1);

namespace Solas\Portal\Woo;

use WC_Order;
use WC_Order_Item_Product;

defined('ABSPATH') || exit;

/**
 * Canonical meta key registry + helpers.
 *
 * Goal: avoid "same concept stored 3 different ways" across modules.
 */
final class Meta {

    // User profile emails (secondary/third).
    public const USER_SECONDARY_EMAIL = 'solas_secondary_email';
    public const USER_THIRD_EMAIL     = 'solas_third_email';

    // Work address user meta prefix.
    public const WORK_PREFIX = 'work_';
    public const ADDITIONAL_PREFIX = 'additional_';

    // -----------------------------
    // Events (CPT: solas_event) meta keys
    // -----------------------------
    public const EVENT_START = 'solas_event_start'; // datetime string
    public const EVENT_END = 'solas_event_end';     // datetime string
    public const EVENT_TYPE = 'solas_event_type';
    public const EVENT_FORMAT = 'solas_event_format';
    public const EVENT_VENUE = 'solas_event_venue';
    public const EVENT_LOCATION = 'solas_event_location';
    public const EVENT_BOOKING_URL = 'solas_event_booking_url';
    public const EVENT_MEETING_LINK = 'solas_event_meeting_link';
    public const EVENT_COMMERCIALLY_LISTED = 'solas_event_commercial';
    public const EVENT_PAID_ORDER_ID = 'solas_event_paid_order_id';

    // -----------------------------
    // Jobs (CPT: solas_job) meta keys
    // -----------------------------
    public const JOB_STATUS = 'solas_job_status';
    public const JOB_VIEWS = 'solas_views';
    public const JOB_APPLY_CLICKS = 'solas_job_apply_click';
    public const JOB_APPLICATIONS = 'solas_applications';
    public const JOB_FILLED_AT = 'solas_job_filled_at';
    public const JOB_EXPIRES_AT = 'solas_job_expires_at';

    // -----------------------------
    // Adverts (CPT: solas_advert) meta keys
    // -----------------------------
    public const ADVERT_STATUS = 'solas_advert_status';
    public const ADVERT_SLOT = 'solas_advert_slot';
    public const ADVERT_START = 'solas_advert_start';
    public const ADVERT_END = 'solas_advert_end';
    public const ADVERT_DAYS = 'solas_advert_days';
    public const ADVERT_ORDER_ID = 'solas_wc_order_id';
    public const ADVERT_RESERVATION_TOKEN = 'solas_advert_reservation_token';

    // -----------------------------
    // Gift / beneficiary
    // -----------------------------

    /** Order meta: aggregated list of beneficiaries for reporting. */
    public const ORDER_GIFT_BENEFICIARIES = '_solas_gift_beneficiaries';

    /** Order item meta (human-readable): gift flag */
    public const ITEM_GIFT_FLAG = 'SOLAS - Item is gift';
    public const ITEM_BENEFICIARY_NAME = 'SOLAS - Beneficiary name';
    public const ITEM_BENEFICIARY_EMAIL = 'SOLAS - Beneficiary email';

    // -----------------------------
    // Renewal fine
    // -----------------------------
    public const ORDER_RENEWAL_FINE_APPLIED = '_solas_renewal_fine_applied';

    // -----------------------------
    // Helpers
    // -----------------------------

    /**
     * Read gift data from an order item.
     *
     * @return array{is_gift:bool,name:string,email:string}
     */
    public static function getItemGiftData(WC_Order_Item_Product $item): array {
        $isGift = (string) $item->get_meta(self::ITEM_GIFT_FLAG, true);
        $email  = (string) $item->get_meta(self::ITEM_BENEFICIARY_EMAIL, true);
        $name   = (string) $item->get_meta(self::ITEM_BENEFICIARY_NAME, true);

        return [
            'is_gift' => (strtolower($isGift) === 'yes'),
            'name'    => trim($name),
            'email'   => trim(strtolower($email)),
        ];
    }

    /**
     * Backfill aggregated order beneficiaries from gifted line items.
     * Only fills when empty, and never overwrites existing non-empty value.
     */
    public static function backfillOrderGiftBeneficiariesIfEmpty(WC_Order $order): void {
        $existing = $order->get_meta(self::ORDER_GIFT_BENEFICIARIES, true);
        if (is_array($existing) && !empty($existing)) {
            return;
        }

        $beneficiaries = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $gift = self::getItemGiftData($item);
            if (!$gift['is_gift'] || empty($gift['email'])) {
                continue;
            }
            $key = $gift['email'];
            $beneficiaries[$key] = [
                'email' => $gift['email'],
                'name'  => $gift['name'],
            ];
        }

        if (!empty($beneficiaries)) {
            $order->update_meta_data(self::ORDER_GIFT_BENEFICIARIES, array_values($beneficiaries));
            $order->save_meta_data();
        }
    }
    /**
     * Convert any meta value into a safe string (no arrays/objects).
     * - Scalars are string-cast.
     * - Arrays: returns the first scalar value (in iteration order), else empty.
     * - Objects/resources/null: empty.
     */
    public static function flattenToString($value, string $default = ''): string {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if (is_string($v) || is_int($v) || is_float($v)) {
                    return (string) $v;
                }
                if (is_bool($v)) {
                    return $v ? '1' : '0';
                }
            }
            return $default;
        }
        return $default;
    }

    public static function getUserMetaString(int $userId, string $key, string $default = ''): string {
        $value = get_user_meta($userId, $key, true);
        return self::flattenToString($value, $default);
    }

    public static function getPostMetaString(int $postId, string $key, string $default = ''): string {
        $value = get_post_meta($postId, $key, true);
        return self::flattenToString($value, $default);
    }

    public static function getOrderMetaString(WC_Order $order, string $key, string $default = ''): string {
        $value = $order->get_meta($key, true);
        return self::flattenToString($value, $default);
    }


}
