<?php

namespace Atomicptr\FlysystemGithub;

use Github\AuthMethod;
use Github\Client;

final readonly class Credentials
{
    private function __construct(
        private string $login,
        private ?string $password,
        private ?string $authMethod,
    ) {
    }

    public function authenticate(Client $client): void
    {
        // public mode do nothing
        if ($this->authMethod === null) {
            return;
        }

        match ($this->authMethod) {
            AuthMethod::ACCESS_TOKEN, AuthMethod::JWT => $client->authenticate($this->login, $this->authMethod),
            AuthMethod::CLIENT_ID => $client->authenticate($this->login, $this->password, $this->authMethod),
            default => new InvalidArgumentException("Unknown auth method: $this->authMethod"),
        };
    }

    public static function public(): Credentials
    {
        return new Credentials(null, null, null);
    }

    public static function fromToken(string $token): Credentials
    {
        return new Credentials($token, null, AuthMethod::ACCESS_TOKEN);
    }

    public static function fromJwt(string $jwt): Credentials
    {
        return new Credentials($jwt, null, AuthMethod::JWT);
    }

    public static function fromClientId(string $clientId, string $clientSecret): Credentials
    {
        return new Credentials($clientId, $clientSecret, AuthMethod::CLIENT_ID);
    }
}
