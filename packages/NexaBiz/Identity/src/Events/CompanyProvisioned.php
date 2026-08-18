<?php

namespace NexaBiz\Identity\Events;

class CompanyProvisioned
{
    public function __construct(public readonly string $companyId) {}
}
