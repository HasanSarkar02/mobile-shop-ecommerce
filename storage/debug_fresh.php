<?php

require 'C:\laragon\www\mobile-shop-Ecommerce\vendor\autoload.php';

function bootApp()
{
    return require 'C:\laragon\www\mobile-shop-Ecommerce\bootstrap\app.php';
}

function handle($app, $url, $method = 'GET', $body = [], $cookies = [])
{
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create($url, $method, $body, $cookies);
    $response = $kernel->handle($request);

    $out = [];
    foreach ($response->headers->getCookies() as $cookie) {
        $out[$cookie->getName()] = $cookie->getValue();
    }

    return [
        'status' => $response->getStatusCode(),
        'location' => $response->headers->get('Location'),
        'cookies' => $out,
        'body' => substr($response->getContent(), 0, 200),
    ];
}

$demo = 'http://demo.mobile-shop-Ecommerce.test';

// Request 1 (fresh app, like a real browser): GET /login
$app = bootApp();
$r1 = handle($app, $demo . '/login', 'GET');
echo 'GET /login: status=' . $r1['status'] . PHP_EOL;

$cookies = $r1['cookies'];

// Need CSRF token from the rendered login page
preg_match('/name="csrf-token" content="([^"]+)"/', $r1['body'], $m);
if (empty($m)) {
    preg_match('/_token" value="([^"]+)"/', $r1['body'], $m2);
    $m = $m2;
}
$token = $m[1] ?? null;
echo 'CSRF token: ' . var_export($token, true) . PHP_EOL;

// Request 2 (fresh app): POST /login
$app = bootApp();
$r2 = handle($app, $demo . '/login', 'POST', [
    'email' => 'customer@demo.test',
    'password' => 'password',
    '_token' => $token,
], $cookies);
echo 'POST /login: status=' . $r2['status'] . ' location=' . var_export($r2['location'], true) . PHP_EOL;

foreach ($r2['cookies'] as $k => $v) {
    $cookies[$k] = $v;
}

// Request 3 (fresh app): GET /account with the session cookie
$app = bootApp();
$r3 = handle($app, $demo . '/account', 'GET', [], $cookies);
echo 'GET /account: status=' . $r3['status'] . PHP_EOL;
echo 'GET /account body: ' . $r3['body'] . PHP_EOL;
