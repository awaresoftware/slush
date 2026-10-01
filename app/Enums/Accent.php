<?php

namespace App\Enums;

use App\Models\User;
use Illuminate\Support\Facades\Cookie;

enum Accent: string
{
    public const COOKIE = 'rekodo_appearance_accent';

    case MusicRed = 'music-red';
    case Blue = 'blue';
    case Purple = 'purple';
    case Pink = 'pink';
    case Orange = 'orange';
    case Green = 'green';
    case Mono = 'mono';

    /**
     * Resolve the accent for a user, falling back to the guest cookie and then
     * the default for guests and for values that are no longer recognised.
     */
    public static function forUser(?User $user): self
    {
        return self::tryFrom((string) $user?->accent) ?? self::fromCookie();
    }

    /**
     * Resolve the accent of a guest, who has no user record to read and whose
     * preference therefore comes from the appearance cookie. Falls back to the
     * default accent when no cookie is set and when the cookie holds a value
     * that is not one of ours.
     */
    private static function fromCookie(): self
    {
        return self::tryFrom((string) Cookie::get(self::COOKIE)) ?? self::MusicRed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $accent): string => $accent->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::MusicRed => __('Music Red'),
            self::Blue => __('Blue'),
            self::Purple => __('Purple'),
            self::Pink => __('Pink'),
            self::Orange => __('Orange'),
            self::Green => __('Green'),
            self::Mono => __('Mono'),
        };
    }
}
