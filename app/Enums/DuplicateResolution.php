<?php

declare(strict_types=1);

namespace App\Enums;

enum DuplicateResolution: string
{
    case ConfirmedDuplicate = 'confirmed_duplicate';
    case KeptSeparate = 'kept_separate';
}
