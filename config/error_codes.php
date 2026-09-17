<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Error\SystemErrorCode;

return [
    /*
     * Every enum implementing App\Shared\Domain\Error\ErrorCode.
     * Add a module's enum here the moment the module declares one.
     */
    'enums' => [
        SystemErrorCode::class,
        IdentityErrorCode::class,
    ],
];
