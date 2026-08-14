<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

class GenericIdentityProvider implements IdentityProvider
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $name,
        private readonly array $config,
        private readonly HttpClient $httpClient,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function redirect(string $callbackUrl, string $state, string $codeChallenge): string
    {
        $endpoint = (string) ($this->config['authorization_endpoint'] ?? '');

        if ($endpoint === '') {
            throw new \InvalidArgumentException("Provider [{$this->name}] has no authorization endpoint.");
        }

        $query = http_build_query([
            'client_id' => $this->config['client_id'] ?? '',
            'redirect_uri' => $callbackUrl,
            'response_type' => 'code',
            'scope' => $this->config['scope'] ?? 'openid email profile',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return $endpoint . '?' . $query;
    }

    public function callback(string $code, string $callbackUrl, string $state, string $codeVerifier): IdentityToken
    {
        $tokenResponse = $this->exchangeCode($code, $callbackUrl, $codeVerifier);

        $accessToken = (string) ($tokenResponse['access_token'] ?? '');

        if ($accessToken === '') {
            throw new \RuntimeException('Provider did not return an access token.');
        }

        return $this->resolveIdentity($accessToken, $tokenResponse);
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code, string $callbackUrl, string $codeVerifier): array
    {
        $endpoint = (string) ($this->config['token_endpoint'] ?? '');

        if ($endpoint === '') {
            throw new \InvalidArgumentException("Provider [{$this->name}] has no token endpoint.");
        }

        $response = $this->httpClient->post($endpoint, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $callbackUrl,
            'client_id' => $this->config['client_id'] ?? '',
            'client_secret' => $this->config['client_secret'] ?? '',
            'code_verifier' => $codeVerifier,
        ]);

        if ($response->status < 200 || $response->status >= 300) {
            throw new \RuntimeException('Token exchange failed with status ' . $response->status . ': ' . $response->body);
        }

        return $response->json;
    }

    /**
     * @param array<string, mixed> $tokenResponse
     */
    private function resolveIdentity(string $accessToken, array $tokenResponse): IdentityToken
    {
        $userinfoEndpoint = $this->config['userinfo_endpoint'] ?? null;

        if (is_string($userinfoEndpoint) && $userinfoEndpoint !== '') {
            $response = $this->httpClient->get($userinfoEndpoint, [
                'Authorization' => 'Bearer ' . $accessToken,
            ]);

            if ($response->status >= 200 && $response->status < 300) {
                return $this->mapUserinfo($response->json);
            }
        }

        $idToken = $tokenResponse['id_token'] ?? null;

        if (is_string($idToken) && $idToken !== '') {
            $claims = $this->decodeJwtPayload($idToken);

            return $this->mapUserinfo($claims);
        }

        throw new \RuntimeException('Could not resolve identity from provider.');
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function mapUserinfo(array $claims): IdentityToken
    {
        return new IdentityToken(
            provider: $this->name,
            providerUserId: (string) ($claims['sub'] ?? ($claims['id'] ?? '')),
            email: (string) ($claims['email'] ?? '') ?: null,
            name: (string) ($claims['name'] ?? '') ?: null,
            accessToken: null,
            refreshToken: null,
            claims: $claims,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return [];
        }

        $payload = strtr($parts[1], '-_', '+/');
        $remainder = strlen($payload) % 4;

        if ($remainder !== 0) {
            $payload .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            return [];
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : [];
    }
}
