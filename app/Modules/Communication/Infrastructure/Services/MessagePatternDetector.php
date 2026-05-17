<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Enums\ChatFlagType;

/**
 * Curated regex detector for off-platform contact attempts in chat bodies.
 *
 * Per research.md §R4:
 *  - Egyptian mobile carriers 010/011/012/015 (Latin + Eastern-Arabic digits)
 *  - International E.164 phone numbers
 *  - Email addresses (RFC-tolerant)
 *  - External links (excludes self-domain instaparty.eg)
 *
 * Pre-normalization steps applied before any regex match:
 *  1. Eastern Arabic digits (٠١٢٣٤٥٦٧٨٩) → Latin digits (0–9)
 *  2. Strip ZWNJ (U+200C), ZWJ (U+200D), NBSP (U+00A0)
 *  3. Collapse runs of whitespace to a single ASCII space
 *
 * Empty/whitespace bodies return an empty array (do not throw).
 *
 * Returns deduplicated MatchedPattern[] — at most one entry per ChatFlagType,
 * to satisfy the UNIQUE (chat_message_log_id, flag_type) index downstream.
 */
final class MessagePatternDetector
{
    private const EASTERN_ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const EGYPTIAN_MOBILE_PATTERN = '/(?:\+?20|0)?[\s.\-]*1[\s.\-]*[0125][\s.\-]*\d(?:[\s.\-]*\d){7}/u';

    private const INTERNATIONAL_E164_PATTERN = '/\+\d{10,15}/u';

    private const EMAIL_PATTERN = '/[\w.+\-]+@[\w\-]+\.[\w.\-]+/iu';

    private const EXTERNAL_LINK_PATTERN = '/https?:\/\/(?!(?:www\.)?instaparty\.eg)[^\s]+/iu';

    /**
     * Detect curated patterns inside the given message body.
     *
     * @return MatchedPattern[] Deduplicated by flag_type (first match wins).
     */
    public function detect(string $body): array
    {
        $normalized = $this->normalize($body);

        if ($normalized === '') {
            return [];
        }

        /** @var array<string, MatchedPattern> $matches keyed by flag_type value */
        $matches = [];

        // Phone: Egyptian carriers first, then international E.164 fallback.
        if (! isset($matches[ChatFlagType::Phone->value])) {
            if (preg_match(self::EGYPTIAN_MOBILE_PATTERN, $normalized, $m) === 1) {
                $matches[ChatFlagType::Phone->value] = new MatchedPattern(
                    ChatFlagType::Phone,
                    $this->trimSnippet($m[0]),
                );
            } elseif (preg_match(self::INTERNATIONAL_E164_PATTERN, $normalized, $m) === 1) {
                $matches[ChatFlagType::Phone->value] = new MatchedPattern(
                    ChatFlagType::Phone,
                    $this->trimSnippet($m[0]),
                );
            }
        }

        if (preg_match(self::EMAIL_PATTERN, $normalized, $m) === 1) {
            $matches[ChatFlagType::Email->value] = new MatchedPattern(
                ChatFlagType::Email,
                $this->trimSnippet($m[0]),
            );
        }

        if (preg_match(self::EXTERNAL_LINK_PATTERN, $normalized, $m) === 1) {
            $matches[ChatFlagType::ExternalLink->value] = new MatchedPattern(
                ChatFlagType::ExternalLink,
                $this->trimSnippet($m[0]),
            );
        }

        return array_values($matches);
    }

    private function normalize(string $body): string
    {
        // Eastern Arabic digit normalization.
        $body = str_replace(self::EASTERN_ARABIC_DIGITS, self::LATIN_DIGITS, $body);

        // Strip ZWNJ, ZWJ, NBSP.
        $body = str_replace(["\u{200C}", "\u{200D}", "\u{00A0}"], '', $body);

        // Collapse whitespace runs.
        $body = preg_replace('/\s+/u', ' ', $body) ?? $body;

        return trim($body);
    }

    private function trimSnippet(string $snippet): string
    {
        // chat_moderation_flags.matched_pattern is VARCHAR(255).
        if (strlen($snippet) <= 255) {
            return $snippet;
        }

        return substr($snippet, 0, 255);
    }
}
