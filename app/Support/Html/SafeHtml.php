<?php

namespace App\Support\Html;

final class SafeHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote',
        'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'a', 'img',
        'span', 'div', 'figure', 'figcaption',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="dape-safe-html-root">'.$trimmed.'</div>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('dape-safe-html-root');
        if (! $root) {
            return '';
        }

        self::cleanNode($root);

        $safe = '';
        foreach ($root->childNodes as $child) {
            $safe .= $document->saveHTML($child);
        }

        return trim($safe);
    }

    private static function cleanNode(\DOMNode $node): void
    {
        $toRemove = [];

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    $toRemove[] = $child;
                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::cleanNode($child);
            } elseif ($child instanceof \DOMComment) {
                $toRemove[] = $child;
            }
        }

        foreach ($toRemove as $child) {
            $node->removeChild($child);
        }
    }

    private static function cleanAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        $toRemove = [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim((string) $attribute->value);

            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $toRemove[] = $name;
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && self::isDangerousUrl($value)) {
                $toRemove[] = $name;
            }
        }

        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }

        if ($tag === 'a') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isDangerousUrl(string $value): bool
    {
        $lower = strtolower($value);

        return str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'vbscript:');
    }
}
