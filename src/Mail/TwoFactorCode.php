<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class TwoFactorCode extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function build()
    {
        return $this->markdown('userauth::emails.two_factor_code')
            ->subject('Ihr Sicherheitscode');
    }
}
