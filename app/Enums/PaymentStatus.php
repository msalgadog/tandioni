<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Rejected = 'rejected';
}
