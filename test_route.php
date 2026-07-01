<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/quiz/active-now', 'GET');
$request->headers->set('Authorization', 'Bearer 1|IKE1edKoKG1752YqTVtXaZIG2iycfaGJ25ONh4196de20324');

$response = $kernel->handle($request);
echo "STATUS: " . $response->getStatusCode() . "\n";
echo "BODY: " . $response->getContent() . "\n";
