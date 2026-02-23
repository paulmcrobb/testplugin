<?php
declare(strict_types=1);

namespace Solas\Portal\CPD;

defined('ABSPATH') || exit;

/**
 * ReminderEngine
 *
 * Class-based version of the legacy includes/cpd-reminders.php engine.
 * The includes file becomes a thin wrapper that wires hooks to these methods.
 *
 * Behaviour is intended to match the legacy implementation:
 * - Daily run (Action Scheduler if available; else WP-Cron)
 * - Builds a queue of eligible users (uses solas_cpd_member_scope_reminders)
 * - Sends reminders at configured schedule "level"
 * - Stores last reminder stamp per user
 */
final class ReminderEngine {

    public static function register(): void {
        add_action('init', [self::class, 'schedule'], 20);

        add_action('solas_cpd_reminders_daily', [self::class, 'runDaily']);
        add_action('solas_cpd_process_reminder_queue', [self::class, 'processQueueAction'], 10, 1);
        add_action('solas_cpd_send_reminder', [self::class, 'sendMemberAction'], 10, 3);
    }

    public static function enabled(): bool {
        return (bool) get_option('solas_cpd_reminders_enabled', true);
    }

    public static function getAdminEmail(): string {
        $email = (string) get_option('solas_cpd_reminders_get_admin_email', get_option('admin_email'));
        $email = sanitize_email($email);
        return $email !== '' ? $email : (string) get_option('admin_email');
    }

    public static function ccAdmin(): bool {
        return (bool) get_option('solas_cpd_reminders_cc_admin', false);
    }

    /**
     * Reminder schedule level:
     * - light: 2 reminders (e.g. mid-cycle + near end)
     * - standard: more frequent checkpoints
     * - heavy: weekly once inside window
     */
    public static function level(): string {
        $level = (string) get_option('solas_cpd_reminders_level', 'standard');
        $level = strtolower(trim($level));
        return in_array($level, ['light','standard','heavy'], true) ? $level : 'standard';
    }

    public static function batchSize(): int {
        $n = (int) get_option('solas_cpd_reminders_batch_size', 50);
        return max(5, min(200, $n));
    }

    public static function batchIntervalSeconds(): int {
        $n = (int) get_option('solas_cpd_reminders_batch_interval', 30);
        return max(5, min(3600, $n));
    }

    /**
     * Schedule a daily run using Action Scheduler (preferred) or WP-Cron fallback.
     */
    public static function schedule(): void {
        if (!self::enabled()) return;

        // Action Scheduler (Woo) preferred
        if (function_exists('as_has_scheduled_action') && function_exists('as_schedule_recurring_action')) {
            if (!as_has_scheduled_action('solas_cpd_reminders_daily')) {
                // Run daily at ~09:15 server time.
                $ts = strtotime('tomorrow 09:15');
                if (!$ts) $ts = time() + 3600;
                as_schedule_recurring_action($ts, DAY_IN_SECONDS, 'solas_cpd_reminders_daily', [], 'solas-portal');
            }
            return;
        }

        // WP-Cron fallback
        if (!wp_next_scheduled('solas_cpd_reminders_daily')) {
            wp_schedule_event(time() + 300, 'daily', 'solas_cpd_reminders_daily');
        }
    }

    /**
     * Daily entry point: decide if today is a send day and, if so, queue eligible users.
     */
    public static function runDaily(): void {
        if (!self::enabled()) return;

        $cycleYear = self::currentCycleYear();
        if ($cycleYear <= 0) return;

        if (!self::shouldSendToday($cycleYear, self::level())) return;

        $userIds = self::eligibleUserIds();
        if (empty($userIds)) return;

        $queueKey = 'solas_cpd_reminder_queue_' . wp_generate_password(8, false, false) . '_' . time();
        set_transient($queueKey, array_values($userIds), HOUR_IN_SECONDS);

        // Kick off queue processing.
        self::enqueueQueueProcessing($queueKey);
    }

    private static function enqueueQueueProcessing(string $queueKey): void {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('solas_cpd_process_reminder_queue', [$queueKey], 'solas-portal');
            return;
        }
        // Fallback: run immediately
        do_action('solas_cpd_process_reminder_queue', $queueKey);
    }

    /**
     * Queue processor: sends reminders in batches and re-queues itself until done.
     */
    public static function processQueueAction(string $queueKey): void {
        if ($queueKey === '') return;

        $queue = get_transient($queueKey);
        if (!is_array($queue) || empty($queue)) {
            delete_transient($queueKey);
            return;
        }

        $batchSize = self::batchSize();
        $batch = array_splice($queue, 0, $batchSize);

        $cycleYear = self::currentCycleYear();
        $level = self::level();

        foreach ($batch as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) continue;

            do_action('solas_cpd_send_reminder', $uid, $cycleYear, $level);
        }

        if (!empty($queue)) {
            set_transient($queueKey, $queue, HOUR_IN_SECONDS);

            // Re-enqueue
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + self::batchIntervalSeconds(), 'solas_cpd_process_reminder_queue', [$queueKey], 'solas-portal');
            } else {
                // WP-Cron fallback: schedule single event
                wp_schedule_single_event(time() + self::batchIntervalSeconds(), 'solas_cpd_process_reminder_queue', [$queueKey]);
            }
        } else {
            delete_transient($queueKey);
        }
    }

    /**
     * Sends a reminder to a single user if they are eligible + not compliant.
     */
    public static function sendMemberAction(int $userId, int $cycleYear, string $level): void {
        if ($userId <= 0 || $cycleYear <= 0) return;

        // Respect scope rules (delegates to PlanGates via wrappers if configured)
        if (function_exists('solas_cpd_member_scope_reminders') && !solas_cpd_member_scope_reminders($userId)) return;

        // Skip compliant
        if (function_exists('solas_cpd_get_compliance_for_cycle')) {
            $c = solas_cpd_get_compliance_for_cycle($userId, (string) $cycleYear);
            if (is_array($c) && !empty($c['is_compliant'])) return;
        }

        // Rate-limit: if already reminded today, skip
        $today = wp_date('Y-m-d');
        $lastKey = 'solas_cpd_last_reminder_' . $cycleYear;
        $last = (string) get_user_meta($userId, $lastKey, true);
        if ($last === $today) return;

        $user = get_userdata($userId);
        if (!$user || empty($user->user_email)) return;

        $to = sanitize_email($user->user_email);
        if ($to === '') return;

        $subject = 'CPD reminder';
        $body = self::buildEmailBody($userId, $cycleYear, $level);

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if (self::ccAdmin()) {
            $admin = self::getAdminEmail();
            if ($admin) $headers[] = 'Cc: ' . $admin;
        }

        wp_mail($to, $subject, $body, $headers);

        update_user_meta($userId, $lastKey, $today);
    }

    private static function buildEmailBody(int $userId, int $cycleYear, string $level): string {
        $name = function_exists('get_user_meta') ? (string) get_user_meta($userId, 'first_name', true) : '';
        if ($name === '') $name = 'there';

        $cycleLabel = self::cycleLabel($cycleYear);

        $lines = [];
        $lines[] = "Hi {$name},";
        $lines[] = "";
        $lines[] = "This is a friendly reminder about your CPD for cycle {$cycleLabel}.";
        $lines[] = "";

        if (function_exists('solas_cpd_get_compliance_for_cycle')) {
            $c = solas_cpd_get_compliance_for_cycle($userId, (string) $cycleYear);
            if (is_array($c)) {
                $structured = $c['structured'] ?? null;
                $unstructured = $c['unstructured'] ?? null;
                if ($structured !== null || $unstructured !== null) {
                    $lines[] = "Your current totals:";
                    if ($structured !== null) $lines[] = " - Structured: {$structured}";
                    if ($unstructured !== null) $lines[] = " - Unstructured: {$unstructured}";
                    $lines[] = "";
                }
            }
        }

        $lines[] = "You can view and update your CPD in your SOLAS portal account.";
        $lines[] = "";
        $lines[] = "Thank you,";
        $lines[] = "SOLAS";

        return implode("\n", $lines);
    }

    private static function eligibleUserIds(): array {
        // The legacy implementation used admin tools membership selection helpers;
        // keep it simple and rely on existing helper if present.
        if (function_exists('solas_cpd_get_member_user_ids_for_cycle_pack')) {
            $ids = solas_cpd_get_member_user_ids_for_cycle_pack(true, true);
            if (is_array($ids)) return array_values(array_unique(array_map('intval', $ids)));
        }

        // Fallback: empty.
        return [];
    }

    private static function cycleLabel(int $cycleYear): string {
        $next = $cycleYear + 1;
        return $cycleYear . '/' . substr((string) $next, -2);
    }

    private static function currentCycleYear(): int {
        if (function_exists('solas_cpd_cycle_from_timestamp')) {
            return (int) solas_cpd_cycle_from_timestamp(time());
        }
        // Fallback: Nov 1–Oct 30 cycle start year.
        $y = (int) wp_date('Y');
        $m = (int) wp_date('n');
        // Nov/Dec => current year, Jan-Oct => previous year
        return ($m >= 11) ? $y : ($y - 1);
    }

    private static function shouldSendToday(int $cycleYear, string $level): bool {
        // Approximate: send on selected dates within the cycle.
        // Keep dates stable with legacy intent; can be tuned later without affecting other systems.
        $today = wp_date('m-d');

        // Standard checkpoints (month/day)
        $schedule = match ($level) {
            'light' => ['03-01', '09-01'],
            'heavy' => self::weeklySchedule(), // every Monday in window
            default => ['02-01', '05-01', '08-01', '10-01'],
        };

        if ($level === 'heavy') {
            return in_array((int) wp_date('N'), [1], true); // Monday
        }

        return in_array($today, $schedule, true);
    }

    private static function weeklySchedule(): array {
        return []; // not used directly (heavy uses weekday check)
    }
}
