<?php

namespace App\Concerns;

trait AudioValidationRules
{
    /**
     * Get the validation rules for audio file uploads.
     *
     * @return array<string, string>
     */
    protected function audioFileRules(): array
    {
        return [
            'audioFiles.*' => 'file|mimes:mp3,wav,flac,m4a,aac,ogg|max:102400',
        ];
    }
}
