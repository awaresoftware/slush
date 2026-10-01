<?php

namespace App\Enums;

enum SourceType: string
{
    case Uploaded = 'uploaded';
    case Local = 'local';
}
