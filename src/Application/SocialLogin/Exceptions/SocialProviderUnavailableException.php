<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin\Exceptions;

final class SocialProviderUnavailableException extends \RuntimeException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : (string) __('userauth::social.errors.provider_unavailable'), 0, $previous);
    }
}
