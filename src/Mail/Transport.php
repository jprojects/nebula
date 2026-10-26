<?php
declare(strict_types=1);

namespace Nebula\Mail;

interface Transport
{
    /** @throws MailException si no se puede entregar */
    public function send(Message $message): void;
}
