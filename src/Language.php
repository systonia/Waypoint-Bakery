<?php

namespace Bakery;

use Waypoint\Attributes\Inject;
use Waypoint\I18n\Translator;
use Waypoint\Live\Scoped;

/**
 * The language of one browser tab, for its live components. The Translator knows the locale of the request
 * that is running; a tab also renders when nobody of it asked (a broadcast from another worker), so the tab
 * keeps the locale it started with.
 */
#[Scoped]
final class Language
{
    #[Inject]
    private Translator $translator;

    private ?string $locale = null;

    public function locale(): string
    {
        return $this->locale ??= $this->translator->locale();
    }

    /** @param array<string, mixed> $params */
    public function t(string $text, array $params = []): string
    {
        return $this->translator->t($text, $params, $this->locale());
    }
}
