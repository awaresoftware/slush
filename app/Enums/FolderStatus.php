<?php

namespace App\Enums;

enum FolderStatus: string
{
    case Active = 'active';
    case Scanning = 'scanning';
    case Error = 'error';
}
