<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Routing\SignedUrl;

it('generates and validates a signed url', function () {
    $signed = new SignedUrl('secret');
    $url = $signed->make('/users/5');

    expect($signed->validate('/users/5', ['signature' => substr($url, strpos($url, 'signature=') + 10)]))->toBeTrue();
});

it('validates a temporary signed url', function () {
    $signed = new SignedUrl('secret');
    $future = new DateTimeImmutable('+1 hour');
    $url = $signed->make('/users/5', $future);

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($signed->validate('/users/5', $query))->toBeTrue();
});

it('rejects an expired signed url', function () {
    $signed = new SignedUrl('secret');
    $past = new DateTimeImmutable('-1 hour');
    $url = $signed->make('/users/5', $past);

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($signed->validate('/users/5', $query))->toBeFalse();
});

it('rejects a tampered signature', function () {
    $signed = new SignedUrl('secret');
    $url = $signed->make('/users/5');

    expect($signed->validate('/users/6', ['signature' => substr($url, strpos($url, 'signature=') + 10)]))->toBeFalse();
});

it('generates signed urls through the route registrar', function () {
    $tmpDir = $this->tempDir('tondbad_signed_url_route_test');
    mkdir("{$tmpDir}/config", 0777, true);
    mkdir("{$tmpDir}/routes", 0777, true);
    mkdir("{$tmpDir}/database/migrations", 0777, true);
    mkdir("{$tmpDir}/storage/logs", 0777, true);
    mkdir("{$tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http', 'key' => 'secret-key'];");
    file_put_contents("{$tmpDir}/routes/http.php", "<?php\nreturn function (TondbadSwoole\\Core\\Route\\Route \$route) {\n    \$route->get('/users/{user}', fn() => 'ok', [], 'users.show');\n};");

    $app = AppFactory::create($tmpDir);
    $route = $app->container->make(Route::class);

    $signed = $route->signedUrl('users.show', ['user' => 5]);

    expect(str_contains($signed, 'signature='))->toBeTrue();
    expect($route->signatureValid(new TondbadSwoole\Http\Request(new class ('GET', $signed) extends OpenSwoole\Http\Request {
        public function __construct(string $method, string $uri) { $this->server = ['request_method' => $method, 'request_uri' => $uri]; $this->get = []; parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $this->get); $this->header = []; }
    })))->toBeTrue();
});
