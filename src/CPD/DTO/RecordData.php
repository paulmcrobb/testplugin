<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\DTO;

defined('ABSPATH') || exit;

/**
 * Immutable DTO for CPD record creation.
 *
 * This provides a stable contract between source adapters (GF/LearnDash/imports)
 * and the RecordFactory, reducing drift and duplicated meta wiring.
 */
final class RecordData {

    private int $userId;
    private int $cycleYear;

    private string $category;
    private string $origin;
    private string $status;
    private string $postStatus;

    private float $hours;
    private float $minutes;
    private float $points;

    private string $subject;
    private string $subjectDetail;
    private string $reflection;

    /** @var string[] */
    private array $evidenceUrls;

    private string $title;

    /** Idempotency key - optional but recommended. */
    private string $sourceRef;

    /** @var array<string,mixed> */
    private array $meta;

    /**
     * @param string[] $evidenceUrls
     * @param array<string,mixed> $meta
     */
    public function __construct(
        int $userId,
        int $cycleYear,
        string $category = '',
        string $origin = '',
        string $status = 'approved',
        string $postStatus = 'publish',
        float $hours = 0.0,
        float $minutes = 0.0,
        ?float $points = null,
        string $subject = '',
        string $subjectDetail = '',
        string $reflection = '',
        array $evidenceUrls = [],
        string $title = '',
        string $sourceRef = '',
        array $meta = []
    ) {
        $this->userId = $userId;
        $this->cycleYear = $cycleYear;

        $this->category = $category;
        $this->origin = $origin;
        $this->status = $status ?: 'approved';
        $this->postStatus = $postStatus ?: 'publish';

        $this->hours = $hours;
        $this->minutes = $minutes;
        $this->points = $points === null ? $hours : $points;

        $this->subject = $subject;
        $this->subjectDetail = $subjectDetail;
        $this->reflection = $reflection;

        // Ensure scalar strings only
        $cleanEvidence = [];
        foreach ($evidenceUrls as $u) {
            if (is_scalar($u)) {
                $u = trim((string) $u);
                if ($u !== '') $cleanEvidence[] = $u;
            }
        }
        $this->evidenceUrls = $cleanEvidence;

        $this->title = $title;
        $this->sourceRef = $sourceRef;

        $this->meta = is_array($meta) ? $meta : [];
    }

    public function userId(): int { return $this->userId; }
    public function cycleYear(): int { return $this->cycleYear; }

    public function category(): string { return $this->category; }
    public function origin(): string { return $this->origin; }
    public function status(): string { return $this->status; }
    public function postStatus(): string { return $this->postStatus; }

    public function hours(): float { return $this->hours; }
    public function minutes(): float { return $this->minutes; }
    public function points(): float { return $this->points; }

    public function subject(): string { return $this->subject; }
    public function subjectDetail(): string { return $this->subjectDetail; }
    public function reflection(): string { return $this->reflection; }

    /** @return string[] */
    public function evidenceUrls(): array { return $this->evidenceUrls; }

    public function title(): string { return $this->title; }
    public function sourceRef(): string { return $this->sourceRef; }

    /** @return array<string,mixed> */
    public function meta(): array { return $this->meta; }

    /**
     * Back-compat helper: create DTO from legacy array payload.
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self {
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $cycleYearRaw = isset($data['cycle_year']) ? (string) $data['cycle_year'] : '';
        $cycleYear = (int) preg_replace('/\D+/', '', $cycleYearRaw);

        $category = isset($data['category']) ? (string) $data['category'] : '';
        $origin = isset($data['origin']) ? (string) $data['origin'] : '';
        $status = isset($data['status']) ? (string) $data['status'] : 'approved';
        $postStatus = isset($data['post_status']) ? (string) $data['post_status'] : 'publish';

        $hours = isset($data['hours']) ? (float) $data['hours'] : 0.0;
        $minutes = isset($data['minutes']) ? (float) $data['minutes'] : 0.0;
        $points = array_key_exists('points', $data) ? (float) $data['points'] : null;

        $subject = isset($data['subject']) ? (string) $data['subject'] : '';
        $subjectDetail = isset($data['subject_detail']) ? (string) $data['subject_detail'] : '';
        $reflection = isset($data['reflection']) ? (string) $data['reflection'] : '';

        $evidenceUrls = (!empty($data['evidence_urls']) && is_array($data['evidence_urls'])) ? $data['evidence_urls'] : [];

        $title = isset($data['title']) ? (string) $data['title'] : '';
        $sourceRef = isset($data['source_ref']) ? (string) $data['source_ref'] : '';

        $meta = (!empty($data['meta']) && is_array($data['meta'])) ? $data['meta'] : [];

        return new self(
            $userId,
            $cycleYear,
            $category,
            $origin,
            $status,
            $postStatus,
            $hours,
            $minutes,
            $points,
            $subject,
            $subjectDetail,
            $reflection,
            $evidenceUrls,
            $title,
            $sourceRef,
            $meta
        );
    }
}
