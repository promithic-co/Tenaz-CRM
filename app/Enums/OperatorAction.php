<?php

namespace App\Enums;

enum OperatorAction: string
{
    case Command = 'command';
    case Takeover = 'takeover';
    case Ignored = 'ignored';
}
