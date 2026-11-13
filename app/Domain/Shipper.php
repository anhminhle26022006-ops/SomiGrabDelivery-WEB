<?php
declare(strict_types=1);
namespace Somi\Domain;
require_once __DIR__.'/User.php';

final class Shipper extends User
{
    public function __construct(User $user, private string $vehicleType,
        private string $verificationStatus, private ?string $identityReviewStatus,
        private string $reliabilityScore, private bool $availability)
    {
        parent::__construct($user->id(), $user->name(), $user->email(), $user->phone(),
            $user->accountStatus());
    }

    public static function fromRow(array $row): self
    {
        return new self(User::fromRow($row), (string)$row['vehicle_type'],
            (string)$row['verification_status'], $row['identity_review_status'] ?? null,
            (string)$row['reliability_score'], self::databaseBoolean($row['availability']));
    }

    private static function databaseBoolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }

    public function vehicleType(): string { return $this->vehicleType; }
    public function isAvailable(): bool { return $this->availability; }
    public function reliabilityScore(): string { return $this->reliabilityScore; }

    /** Policy threshold is loaded by the service, never hardcoded in the entity. */
    public function workBlockers(?string $threshold): array
    {
        $blockers = [];
        if (!$this->isActive()) $blockers[] = 'ACCOUNT_NOT_ACTIVE';
        if ($this->verificationStatus !== 'APPROVED') $blockers[] = 'KYC_NOT_APPROVED';
        if ($this->identityReviewStatus !== 'APPROVED') $blockers[] = 'IDENTITY_NOT_APPROVED';
        if ($threshold !== null && (float)$this->reliabilityScore < (float)$threshold) $blockers[] = 'SCORE_LOCKED';
        return $blockers;
    }
}
