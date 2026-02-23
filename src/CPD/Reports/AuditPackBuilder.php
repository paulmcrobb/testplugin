<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Reports;

use Solas\Portal\CPD\Totals;
use Solas\Portal\Woo\PlanGates;

defined('ABSPATH') || exit;

/**
 * Builds the CPD Cycle Audit Pack folder under uploads.
 * Stage 45: extracted from includes/cpd-admin-tools.php
 */
final class AuditPackBuilder {

    public static function build(string $cycleLabel): string {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            wp_die('Uploads directory not available');
        }

        $cycleStartYear = self::cycleStartYearFromLabel($cycleLabel);
        if (!$cycleStartYear) {
            wp_die('Invalid cycle label');
        }

        $pack_id = $cycleLabel . '-' . gmdate('Ymd-His');

        $base_dir = trailingslashit($uploads['basedir']) . 'solas-exports/cpd-audit-pack/' . $pack_id . '/';
        $base_url = trailingslashit($uploads['baseurl']) . 'solas-exports/cpd-audit-pack/' . $pack_id . '/';

        wp_mkdir_p($base_dir);
        @file_put_contents($base_dir . 'index.html', '<!doctype html><title>Forbidden</title>');

        // Build files
        self::writeComplianceCsv($cycleLabel, $cycleStartYear, $base_dir . 'compliance.csv');
        self::writeEvidenceCsv($cycleLabel, $cycleStartYear, $base_dir . 'evidence.csv');
        self::writeExceptionsCsv($cycleLabel, $cycleStartYear, $base_dir . 'exceptions.csv');

        $summary = [
            'cycle_label' => $cycleLabel,
            'cycle_start_year' => $cycleStartYear,
            'pack_id' => $pack_id,
            'generated_at_gmt' => gmdate('c'),
            'files' => [
                'compliance' => $base_url . 'compliance.csv',
                'evidence' => $base_url . 'evidence.csv',
                'exceptions' => $base_url . 'exceptions.csv',
                'summary_txt' => $base_url . 'summary.txt',
                'summary_json' => $base_url . 'summary.json',
            ],
        ];

        @file_put_contents($base_dir . 'summary.json', wp_json_encode($summary, JSON_PRETTY_PRINT));
        @file_put_contents($base_dir . 'summary.txt', self::summaryText($summary));

        return $pack_id;
    }

    private static function cycleStartYearFromLabel(string $label): int {
        if (function_exists('solas_cpd_admin_tools_cycle_start_year_from_label')) {
            return (int) solas_cpd_admin_tools_cycle_start_year_from_label($label);
        }
        // fall back: first 4 digits
        return (int) substr($label, 0, 4);
    }

    private static function writeComplianceCsv(string $cycleLabel, int $cycleStartYear, string $filePath): void {
        $fh = fopen($filePath, 'wb');
        if (!$fh) return;

        fputcsv($fh, [
            'user_id','name','email','secondary_email','third_email',
            'membership_type','cycle','cycle_start_year',
            'structured','unstructured','solas_structured','total_hours',
            'status','last_activity_date_gmt','cycle_unlocked_override'
        ]);

        $userIds = function_exists('solas_cpd_get_member_user_ids_for_cycle_pack')
            ? (array) solas_cpd_get_member_user_ids_for_cycle_pack(true, true)
            : [];

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) continue;

            $user = get_user_by('id', $uid);
            if (!$user) continue;

            $secondary = (string) get_user_meta($uid, 'solas_secondary_email', true);
            $third = (string) get_user_meta($uid, 'solas_third_email', true);

            $memberType = '';
            if (class_exists(PlanGates::class) && method_exists(PlanGates::class, 'getMemberTypeLabel')) {
                $memberType = (string) PlanGates::getMemberTypeLabel($uid);
            } elseif (function_exists('solas_cpd_get_member_type')) {
                $memberType = (string) solas_cpd_get_member_type($uid);
            }

            $totals = class_exists(Totals::class)
                ? (array) Totals::totalsForUserCycle($uid, $cycleStartYear)
                : ['structured'=>0,'unstructured'=>0,'solas_structured'=>0,'total'=>0];

            $structured = (float) ($totals['structured'] ?? 0);
            $unstructured = (float) ($totals['unstructured'] ?? 0);
            $solasStructured = (float) ($totals['solas_structured'] ?? 0);
            $total = (float) ($totals['total'] ?? ($structured + $unstructured + $solasStructured));

            $complete = class_exists(Totals::class) && method_exists(Totals::class, 'isCycleComplete')
                ? (bool) Totals::isCycleComplete($uid, $cycleStartYear)
                : false;

            $lastActivity = self::lastApprovedActivityDateGmt($uid, $cycleStartYear) ?? '';

            $unlockedCycles = (array) get_user_meta($uid, 'solas_cpd_unlocked_cycles', true);
            $override = in_array($cycleStartYear, array_map('intval', $unlockedCycles), true) ? 'yes' : 'no';

            fputcsv($fh, [
                $uid,
                $user->display_name,
                $user->user_email,
                $secondary,
                $third,
                $memberType,
                $cycleLabel,
                $cycleStartYear,
                $structured,
                $unstructured,
                $solasStructured,
                $total,
                $complete ? 'Completed' : 'Not yet completed',
                $lastActivity,
                $override
            ]);
        }

        fclose($fh);
    }

    private static function lastApprovedActivityDateGmt(int $userId, int $cycleStartYear): ?string {
        global $wpdb;
        $postType = 'solas_cpd_record';
        $statusKey = 'solas_cpd_status';
        $cycleKey = 'solas_cycle_year';
        $userKey = 'solas_user_id';

        $sql = $wpdb->prepare(
            "SELECT MAX(p.post_date_gmt)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} mu ON mu.post_id = p.ID AND mu.meta_key=%s AND mu.meta_value=%s
             INNER JOIN {$wpdb->postmeta} mc ON mc.post_id = p.ID AND mc.meta_key=%s AND mc.meta_value=%s
             INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key=%s AND ms.meta_value=%s
             WHERE p.post_type=%s AND p.post_status='publish'",
            $userKey, (string) $userId,
            $cycleKey, (string) $cycleStartYear,
            $statusKey, 'approved',
            $postType
        );
        $val = $wpdb->get_var($sql);
        return $val ? (string) $val : null;
    }

    private static function writeEvidenceCsv(string $cycleLabel, int $cycleStartYear, string $filePath): void {
        $fh = fopen($filePath, 'wb');
        if (!$fh) return;

        fputcsv($fh, [
            'record_id','user_id','name','email','secondary_email','third_email',
            'membership_type','cycle','category','origin','status',
            'subject','minutes','hours','created_at_gmt','source_ref','evidence_urls'
        ]);

        $args = [
            'post_type' => 'solas_cpd_record',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 500,
            'paged' => 1,
            'meta_query' => [
                [
                    'key' => 'solas_cycle_year',
                    'value' => (string) $cycleStartYear,
                    'compare' => '='
                ],
                [
                    'key' => 'solas_cpd_status',
                    'value' => 'approved',
                    'compare' => '='
                ],
            ]
        ];

        do {
            $q = new \WP_Query($args);
            foreach ($q->posts as $rid) {
                $rid = (int) $rid;
                $uid = (int) get_post_meta($rid, 'solas_user_id', true);
                $user = $uid ? get_user_by('id', $uid) : null;

                $secondary = $uid ? (string) get_user_meta($uid, 'solas_secondary_email', true) : '';
                $third = $uid ? (string) get_user_meta($uid, 'solas_third_email', true) : '';

                $memberType = '';
                if ($uid) {
                    if (class_exists(PlanGates::class) && method_exists(PlanGates::class, 'getMemberTypeLabel')) {
                        $memberType = (string) PlanGates::getMemberTypeLabel($uid);
                    } elseif (function_exists('solas_cpd_get_member_type')) {
                        $memberType = (string) solas_cpd_get_member_type($uid);
                    }
                }

                $category = (string) get_post_meta($rid, 'solas_cpd_category', true);
                $origin = (string) get_post_meta($rid, 'solas_origin', true);
                $status = (string) get_post_meta($rid, 'solas_cpd_status', true);
                $subject = (string) get_post_meta($rid, 'solas_subject', true);
                $minutes = (int) get_post_meta($rid, 'solas_minutes', true);
                $hours = (float) get_post_meta($rid, 'solas_hours', true);
                $sourceRef = (string) get_post_meta($rid, 'solas_source_ref', true);
                $created = get_post_field('post_date_gmt', $rid);

                $evidence = self::normaliseEvidence($rid);

                fputcsv($fh, [
                    $rid,
                    $uid,
                    $user ? $user->display_name : '',
                    $user ? $user->user_email : '',
                    $secondary,
                    $third,
                    $memberType,
                    $cycleLabel,
                    $category,
                    $origin,
                    $status,
                    $subject,
                    $minutes,
                    $hours,
                    $created,
                    $sourceRef,
                    implode(' | ', $evidence),
                ]);
            }

            $args['paged']++;
        } while (!empty($q) && $q->max_num_pages >= $args['paged']);

        fclose($fh);
    }

    private static function writeExceptionsCsv(string $cycleLabel, int $cycleStartYear, string $filePath): void {
        $fh = fopen($filePath, 'wb');
        if (!$fh) return;

        fputcsv($fh, ['record_id','user_id','cycle','issue_code','issue_detail','edit_record_url','edit_user_url']);

        $args = [
            'post_type' => 'solas_cpd_record',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 500,
            'paged' => 1,
            'meta_query' => [
                [
                    'key' => 'solas_cycle_year',
                    'value' => (string) $cycleStartYear,
                    'compare' => '='
                ],
            ]
        ];

        $allowedStatus = ['pending','approved','rejected','voided'];

        do {
            $q = new \WP_Query($args);
            foreach ($q->posts as $rid) {
                $rid=(int)$rid;
                $uid=(int)get_post_meta($rid,'solas_user_id',true);
                $status=(string)get_post_meta($rid,'solas_cpd_status',true);
                $minutes=(float)get_post_meta($rid,'solas_minutes',true);
                $hours=(float)get_post_meta($rid,'solas_hours',true);
                $cycle=(string)get_post_meta($rid,'solas_cycle_year',true);

                $issues=[];

                if ($uid<=0 || !get_user_by('id',$uid)) $issues[]=['missing_user','solas_user_id missing/invalid'];
                if ((int)$cycle!==$cycleStartYear) $issues[]=['cycle_mismatch','solas_cycle_year missing/invalid'];
                if ($status==='' || !in_array($status,$allowedStatus,true)) $issues[]=['invalid_status','Unexpected status: '.$status];
                if ($minutes<0 || $hours<0) $issues[]=['negative_time','minutes/hours negative'];

                $eRaw=get_post_meta($rid,'solas_evidence_urls',true);
                if (is_string($eRaw) && self::looksJson($eRaw)) $issues[]=['evidence_json','Evidence stored as JSON string'];
                if (is_array($eRaw)) {
                    foreach ($eRaw as $v) {
                        if (is_array($v) || is_object($v)) { $issues[]=['evidence_nested','Evidence contains nested values']; break; }
                    }
                }

                if (!$issues) continue;

                $editRecord = get_edit_post_link($rid,'');
                $editUser = $uid>0 ? get_edit_user_link($uid) : '';

                foreach ($issues as [$code,$detail]) {
                    fputcsv($fh, [$rid,$uid,$cycleLabel,$code,$detail,$editRecord,$editUser]);
                }
            }
            $args['paged']++;
        } while (!empty($q) && $q->max_num_pages >= $args['paged']);

        fclose($fh);
    }

    private static function normaliseEvidence(int $recordId): array {
        $urls = [];

        $raw = get_post_meta($recordId, 'solas_evidence_urls', true);
        if (empty($raw)) {
            $single = get_post_meta($recordId, 'solas_evidence_url', true);
            $raw = $single ?: [];
        }

        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') return [];
            if (self::looksJson($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [$raw];
            } else {
                $raw = [$raw];
            }
        }

        if (is_array($raw)) {
            foreach ($raw as $v) {
                if (is_string($v)) {
                    $v = trim($v);
                    if ($v !== '') $urls[] = $v;
                } elseif (is_array($v) && isset($v['url']) && is_string($v['url'])) {
                    $u = trim($v['url']);
                    if ($u !== '') $urls[] = $u;
                }
            }
        }

        $urls = array_values(array_unique($urls));
        return $urls;
    }

    private static function looksJson(string $s): bool {
        return ($s !== '' && ($s[0] === '[' || $s[0] === '{'));
    }

    private static function summaryText(array $summary): string {
        $lines = [];
        $lines[] = 'CPD Cycle Audit Pack';
        $lines[] = 'Cycle: ' . ($summary['cycle_label'] ?? '');
        $lines[] = 'Cycle start year: ' . ($summary['cycle_start_year'] ?? '');
        $lines[] = 'Pack ID: ' . ($summary['pack_id'] ?? '');
        $lines[] = 'Generated (GMT): ' . ($summary['generated_at_gmt'] ?? '');
        $lines[] = '';
        $lines[] = 'Files:';
        foreach (($summary['files'] ?? []) as $k => $v) {
            $lines[] = '- ' . $k . ': ' . $v;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }
}
