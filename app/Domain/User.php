<?php
declare(strict_types=1);
namespace Somi\Domain;

/** Account identity shared by every workspace; never contains credentials. */
class User
{
    public function __construct(
        private int $userId,
        private string $name,
        private ?string $email,
        private ?string $phone,
        private string $accountStatus
    ) {
        if ($userId < 1) throw new \InvalidArgumentException('User ID must be positive.');
    }

    public static function fromRow(array $row): self
    {
        return new self((int)$row['user_id'], (string)$row['full_name'], $row['email'] ?? null,
            $row['phone'] ?? null, (string)$row['account_status']);
    }

    public function id(): int { return $this->userId; }
    public function name(): string { return $this->name; }
    public function email(): ?string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function accountStatus(): string { return $this->accountStatus; }
    public function isActive(): bool { return $this->accountStatus === 'ACTIVE'; }
}
