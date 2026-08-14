<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Mfa;

use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\DatabaseManager;

class MfaManager
{
    /**
     * @var array<string, MfaFactor>
     */
    private array $factors = [];

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly Config $config,
        private readonly AuthManager $auth,
    ) {
    }

    public function registerFactor(MfaFactor $factor): self
    {
        $this->factors[$factor->type()] = $factor;

        return $this;
    }

    public function challenge(Authenticatable $user, string $type): array
    {
        return $this->factor($type)->challenge($user);
    }

    public function verify(Authenticatable $user, string $type, string $input): bool
    {
        if (!$this->factor($type)->verify($user, $input)) {
            return false;
        }

        $this->auth->addSessionClaim('mfa_verified', true);

        return true;
    }

    public function hasFactor(Authenticatable $user, string $type): bool
    {
        return $this->databaseManager
            ->table('mfa_factors')
            ->where('user_id', '=', $user->getAuthIdentifier())
            ->where('type', '=', $type)
            ->where('enabled', '=', true)
            ->first() !== null;
    }

    private function factor(string $type): MfaFactor
    {
        if (!isset($this->factors[$type])) {
            throw new \InvalidArgumentException("MFA factor [{$type}] is not registered.");
        }

        return $this->factors[$type];
    }
}
