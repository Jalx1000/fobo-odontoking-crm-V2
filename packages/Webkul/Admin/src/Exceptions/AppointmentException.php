<?php

namespace Webkul\Admin\Exceptions;

class AppointmentException extends \RuntimeException
{
    public function __construct(string $message, protected array $details = [])
    {
        parent::__construct($message);
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
