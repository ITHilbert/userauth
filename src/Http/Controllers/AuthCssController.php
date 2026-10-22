<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion;
use Symfony\Component\HttpFoundation\Response;

final class AuthCssController
{
    public function __invoke(AuthAssetVersion $assets): Response
    {
        abort_unless(is_file($assets->cssPath()), 404);

        return response()->file($assets->cssPath(), [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
