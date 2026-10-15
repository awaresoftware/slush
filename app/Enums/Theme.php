<?php

namespace App\Enums;

use App\Models\User;
use Illuminate\Support\Facades\Cookie;

enum Theme: string
{
    public const COOKIE = 'foonk_appearance_theme';

    case System = 'system';
    case Porcelain = 'porcelain';
    case Graphite = 'graphite';
    case Midnight = 'midnight';
    case CatppuccinFrappe = 'catppuccin-frappe';

    /**
     * Resolve the theme for a user, falling back to the guest cookie and then
     * the default for guests and for values that are no longer recognised.
     */
    public static function forUser(?User $user): self
    {
        return self::tryFrom((string) $user?->theme) ?? self::fromCookie();
    }

    /**
     * Resolve the theme of a guest, who has no user record to read and whose
     * preference therefore comes from the appearance cookie. Falls back to the
     * system theme when no cookie is set and when the cookie holds a value
     * that is not one of ours.
     */
    private static function fromCookie(): self
    {
        return self::tryFrom((string) Cookie::get(self::COOKIE)) ?? self::System;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $theme): string => $theme->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::System => __('Auto'),
            self::Porcelain => __('Porcelain'),
            self::Graphite => __('Graphite'),
            self::Midnight => __('Midnight'),
            self::CatppuccinFrappe => __('Frappé'),
        };
    }

    /**
     * Short description of how the theme behaves, shown under its name.
     */
    public function schemeLabel(): string
    {
        return match ($this) {
            self::System => __('Follows your device'),
            self::Porcelain => __('Light'),
            self::Graphite, self::Midnight, self::CatppuccinFrappe => __('Dark'),
        };
    }

    public function isSystem(): bool
    {
        return $this === self::System;
    }

    /**
     * Canvas colour, used for the browser theme-color meta tag.
     * Auto has no single colour; it resolves to Porcelain or Graphite.
     */
    public function canvas(): ?string
    {
        return match ($this) {
            self::System => null,
            self::Porcelain => '#f5f5f7',
            self::Graphite => '#161618',
            self::Midnight => '#000000',
            self::CatppuccinFrappe => '#292c3c',
        };
    }
}
