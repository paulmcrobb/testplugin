<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use DateTimeImmutable;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Renewal reminders + partnership tone.
 * Schedules emails relative to end date when an advert is activated.
 */
final class Renewals {

    public const GROUP = 'solas-portal';
    public const PUBLIC_ACTION = 'solas_adverts_public_renew';

    public static function register(): void {
        add_action('init', [self::class, 'handlePublicRenew']);
        add_action('solas_adverts_renewals_send', [self::class, 'send'], 10, 1);
    }

    public static function schedule( $advertId): void {
        $advertId = (int) $advertId;
        if ($advertId <= 0) return;

        $endIso = (string) get_post_meta($advertId, 'solas_end_date', true);
        if ($endIso === '') return;

        try {
            $end = new DateTimeImmutable($endIso, wp_timezone());
        } catch (Throwable $t) {
            return;
        }

        // schedule offsets in days before end
        $offsets = [
            14,
            7,
            1,
        ];

        foreach ($offsets as $daysBefore) {
            $sendAt = $end->modify('-' . (int)$daysBefore . ' days');
            if (!$sendAt) continue;
            $ts = $sendAt->getTimestamp();
            if ($ts <= time()) continue;

            $args = [
                'advert_id' => $advertId,
                'days_before' => (int) $daysBefore,
            ];

            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions('solas_adverts_renewals_send', $args, self::GROUP);
            }
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action($ts, 'solas_adverts_renewals_send', $args, self::GROUP);
            } else {
                wp_schedule_single_event($ts, 'solas_adverts_renewals_send', [$args]);
            }
        }
    }

    public static function recipientEmail( $advertId): string {
        $email = (string) get_post_meta($advertId, 'solas_contact_email', true);
        $email = sanitize_email($email);
        if ($email !== '') return $email;

        $authorId = (int) get_post_field('post_author', $advertId);
        if ($authorId > 0) {
            $u = get_user_by('id', $authorId);
            if ($u && $u->user_email) return (string) $u->user_email;
        }
        return (string) get_option('admin_email');
    }

    public static function formUrlBase(): string {
        // Allow override via option
        $base = (string) get_option('solas_adverts_renewals_form_url', '');
        $base = trim($base);
        if ($base !== '') return $base;
        return home_url('/advertise/');
    }

    public static function makeToken( $advertId, $daysBefore): string {
        $secret = (string) wp_salt('auth');
        return hash_hmac('sha256', $advertId . '|' . $daysBefore, $secret);
    }

    public static function verifyToken( $advertId, $daysBefore, $token): bool {
        $token = sanitize_text_field($token);
        return hash_equals(self::makeToken($advertId, $daysBefore), $token);
    }

    public static function link( $advertId, $daysBefore): string {
        $token = self::makeToken($advertId, $daysBefore);
        return add_query_arg([
            'solas_renew' => 1,
            'advert_id' => $advertId,
            'd' => $daysBefore,
            't' => $token,
        ], self::formUrlBase());
    }

    /** @return array{subject:string,body:string,headers:array<int,string>} */
    public static function buildEmail( $advertId, $daysBefore): array {
        $title = get_the_title($advertId);
        $endIso = (string) get_post_meta($advertId, 'solas_end_date', true);

        $link = self::link($advertId, $daysBefore);

        $subject = sprintf('Your SOLAS advert "%s" is due to end soon', $title);

        $bodyLines = [
            'Hi,',
            '',
            sprintf('Just a quick note that your SOLAS advert "%s" is due to end soon.', $title),
        ];
        if ($endIso !== '') {
            $bodyLines[] = sprintf('End date: %s', $endIso);
        }
        $bodyLines[] = '';
        $bodyLines[] = 'If you’d like to renew, you can use the link below:';
        $bodyLines[] = $link;
        $bodyLines[] = '';
        $bodyLines[] = 'Thanks,';
        $bodyLines[] = 'SOLAS';

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return [
            'subject' => $subject,
            'body' => implode("\n", $bodyLines),
            'headers' => $headers,
        ];
    }

    /** @param mixed $maybeArgs */
    public static function handlePublicRenew(): void {
        if (!isset($_GET['solas_renew'])) return;

        $advertId = isset($_GET['advert_id']) ? (int) $_GET['advert_id'] : 0;
        $daysBefore = isset($_GET['d']) ? (int) $_GET['d'] : 0;
        $token = isset($_GET['t']) ? (string) $_GET['t'] : '';

        if ($advertId <= 0 || $daysBefore <= 0 || $token === '') return;
        if (!self::verifyToken($advertId, $daysBefore, $token)) return;

        // Redirect to renew form with prefill parameters (GF dynamic population can use these)
        $target = add_query_arg([
            'renew_advert_id' => $advertId,
        ], self::formUrlBase());

        wp_safe_redirect($target);
        exit;
    }

    /** @param array<string,mixed>|int $args */
    public static function send($args): void {
        $advertId = 0;
        $daysBefore = 0;

        if (is_array($args)) {
            $advertId = (int)($args['advert_id'] ?? 0);
            $daysBefore = (int)($args['days_before'] ?? 0);
        } elseif (is_numeric($args)) {
            $advertId = (int) $args;
            $daysBefore = 7;
        }

        if ($advertId <= 0) return;
        if ($daysBefore <= 0) $daysBefore = 7;

        $to = self::recipientEmail($advertId);
        $mail = self::buildEmail($advertId, $daysBefore);

        wp_mail($to, $mail['subject'], $mail['body'], $mail['headers']);
    }
}
