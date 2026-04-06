<?php

namespace App\Services;

/**
 * CancellationPolicyService
 *
 * Defines the 8 cancellation policy types and their refund rules.
 * Each policy is described by:
 *   - label        : Human-readable name shown in the UI
 *   - description  : Short description for guests during the booking process
 *   - guest_can_cancel : Whether guests are allowed to cancel (false = host only)
 *   - rules        : Ordered array of refund tiers (evaluated from most-generous to strictest)
 *                    Each rule has:
 *                      - days_before : days before check-in (>= this value to qualify)
 *                      - refund_pct  : percentage of the booking amount refunded (0-100)
 *                      - note        : human-readable clause
 *
 * Evaluation: iterate rules in order; apply the first one whose days_before threshold is met.
 */
class CancellationPolicyService
{
    /**
     * All supported policy types and their metadata / refund rules.
     */
    public static function getPolicies(): array
    {
        return [
            'full_refund' => [
                'type'             => 'full_refund',
                'label'            => 'Full Refund',
                'description'      => '100% refund on cancellation at any time, no questions asked.',
                'guest_can_cancel' => true,
                'rules'            => [
                    ['days_before' => 0, 'refund_pct' => 100, 'note' => 'Full refund regardless of when the cancellation is made.'],
                ],
            ],
            'flexible' => [
                'type'             => 'flexible',
                'label'            => 'Flexible',
                'description'      => 'Full refund up to 24 hours before check-in. After that, the first night is non-refundable.',
                'guest_can_cancel' => true,
                'rules'            => [
                    ['days_before' => 1, 'refund_pct' => 100, 'note' => 'Full refund if cancelled at least 24 hours before check-in.'],
                    ['days_before' => 0, 'refund_pct' => 0,   'note' => 'No refund if cancelled less than 24 hours before check-in.'],
                ],
            ],
            'moderate' => [
                'type'             => 'moderate',
                'label'            => 'Moderate',
                'description'      => 'Full refund up to 5 days before check-in. After that, 50% refund for nights not stayed.',
                'guest_can_cancel' => true,
                'rules'            => [
                    ['days_before' => 5, 'refund_pct' => 100, 'note' => 'Full refund if cancelled at least 5 days before check-in.'],
                    ['days_before' => 1, 'refund_pct' => 50,  'note' => '50% refund if cancelled between 1–5 days before check-in.'],
                    ['days_before' => 0, 'refund_pct' => 0,   'note' => 'No refund if cancelled less than 24 hours before check-in.'],
                ],
            ],
            'strict' => [
                'type'             => 'strict',
                'label'            => 'Strict',
                'description'      => 'Full refund within 48 hours of booking if check-in is ≥14 days away. 50% refund if cancelled at least 7 days before check-in.',
                'guest_can_cancel' => true,
                'rules'            => [
                    ['days_before' => 14, 'refund_pct' => 100, 'note' => 'Full refund if cancelled within 48 hours of booking AND check-in is ≥14 days away.'],
                    ['days_before' => 7,  'refund_pct' => 50,  'note' => '50% refund if cancelled at least 7 days before check-in.'],
                    ['days_before' => 0,  'refund_pct' => 0,   'note' => 'No refund if cancelled less than 7 days before check-in.'],
                ],
            ],
            'super_strict_30' => [
                'type'             => 'super_strict_30',
                'label'            => 'Super Strict 30',
                'description'      => '50% refund if cancelled at least 30 days before check-in.',
                'guest_can_cancel' => true,
                'rules'            => [
                    ['days_before' => 30, 'refund_pct' => 50, 'note' => '50% refund if cancelled at least 30 days before check-in.'],
                    ['days_before' => 0,  'refund_pct' => 0,  'note' => 'No refund if cancelled less than 30 days before check-in.'],
                ],
            ],
            'super_strict_60' => [
                'type'             => 'super_strict_60',
                'label'            => 'Super Strict 60',
                'description'      => '50% refund if cancelled at least 60 days before check-in.',
                'guest_can_cancel' => true,
                'rules'            => [
                    ['days_before' => 60, 'refund_pct' => 50, 'note' => '50% refund if cancelled at least 60 days before check-in.'],
                    ['days_before' => 0,  'refund_pct' => 0,  'note' => 'No refund if cancelled less than 60 days before check-in.'],
                ],
            ],
            'long_term' => [
                'type'             => 'long_term',
                'label'            => 'Long-Term',
                'description'      => 'For stays of 28 nights or more. Full refund if cancelled within 48 hours of booking. After that, first 30 days are non-refundable.',
                'guest_can_cancel' => true,
                'rules'            => [
                    ['days_before' => 1, 'refund_pct' => 100, 'note' => 'Full refund if cancelled within 48 hours of booking.'],
                    ['days_before' => 0, 'refund_pct' => 0,   'note' => 'First 30 days of the stay are non-refundable once 48-hour window has passed.'],
                ],
            ],
            'no_cancellation' => [
                'type'             => 'no_cancellation',
                'label'            => 'No Cancellation',
                'description'      => 'Guests cannot cancel this booking. Only the host may cancel.',
                'guest_can_cancel' => false,
                'rules'            => [
                    ['days_before' => 0, 'refund_pct' => 0, 'note' => 'No cancellation or refund is permitted for guests. Only the host can cancel.'],
                ],
            ],
        ];
    }

    /**
     * Get metadata for a single policy type.
     */
    public static function getPolicy(string $type): ?array
    {
        return self::getPolicies()[$type] ?? null;
    }

    /**
     * Build the full snapshot array to store on a booking at creation time.
     *
     * @param string $policyType  e.g. 'flexible'
     * @return array              Full policy details to persist as JSON
     */
    public static function buildSnapshot(string $policyType): array
    {
        $policy = self::getPolicy($policyType) ?? self::getPolicy('flexible');

        return [
            'type'             => $policy['type'],
            'label'            => $policy['label'],
            'description'      => $policy['description'],
            'guest_can_cancel' => $policy['guest_can_cancel'] ?? true,
            'rules'            => $policy['rules'],
        ];
    }

    /**
     * Calculate the refund percentage for a cancellation given the policy snapshot
     * and the number of days until check-in.
     *
     * @param array $snapshot   The cancellation_policy_snapshot stored on the booking
     * @param int   $daysBefore Days remaining until check-in (0 = same day or past)
     * @return int              Refund percentage (0–100)
     */
    public static function calculateRefundPercentage(array $snapshot, int $daysBefore): int
    {
        $rules = $snapshot['rules'] ?? [];

        // Rules are ordered most-generous first; find the first qualifying rule.
        foreach ($rules as $rule) {
            if ($daysBefore >= $rule['days_before']) {
                return (int) $rule['refund_pct'];
            }
        }

        return 0;
    }

    /**
     * Validate that a policy type string is one of the supported values.
     */
    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::getPolicies());
    }

    /**
     * Check whether a guest is allowed to cancel given a policy snapshot.
     * Defaults to true for legacy bookings that have no snapshot type.
     */
    public static function guestCanCancel(array $snapshot): bool
    {
        return $snapshot['guest_can_cancel'] ?? true;
    }
}
