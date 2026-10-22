<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans the HTML that the rich text editor produces. Only a short list of tags and attributes survives,
 * links may only be http, https, mailto or tel, and everything else (scripts, handlers, iframes, styles) is dropped.
 */
class Html
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'code', 'pre', 'span'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'title', 'head', 'noscript', 'template', 'img', 'video', 'audio'];
    private const ALIGN = ['left', 'center', 'right', 'justify'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('root');
        if ($root === null) {
            return e(strip_tags($html));
        }
        self::walk($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /** Plain text for previews and search. */
    public static function text(?string $html, int $limit = 300): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '</li>', '</tr>'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, $limit);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $node->removeChild($child); // comments, processing instructions, CDATA
                }

                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }
            self::walk($child);
            if (! in_array($tag, self::TAGS, true)) {
                // Unknown tag: keep its text and children, lose the tag itself.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            self::attributes($child, $tag);
        }
    }

    private static function attributes(DOMElement $el, string $tag): void
    {
        $keep = [];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            if ($tag === 'a' && $name === 'href' && preg_match('#^(https?://|mailto:|tel:)#i', $value) && ! preg_match('/[\x00-\x1f]/', $value)) {
                $keep['href'] = $value;
            } elseif (in_array($tag, ['td', 'th'], true) && in_array($name, ['colspan', 'rowspan'], true) && ctype_digit($value) && (int) $value > 0 && (int) $value < 20) {
                $keep[$name] = $value;
            } elseif ($name === 'style' && in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'td', 'th', 'li', 'blockquote'], true) && preg_match('/^\s*text-align\s*:\s*(left|center|right|justify)\s*;?\s*$/i', $value, $m)) {
                $keep['style'] = 'text-align: '.strtolower($m[1]);
            }
        }
        foreach (iterator_to_array($el->attributes) as $attr) {
            $el->removeAttribute($attr->name);
        }
        foreach ($keep as $name => $value) {
            $el->setAttribute($name, $value);
        }
        if ($tag === 'a') {
            if (! $el->hasAttribute('href')) {
                // A link with no safe address is just text.
                while ($el->firstChild) {
                    $el->parentNode->insertBefore($el->firstChild, $el);
                }
                $el->parentNode->removeChild($el);

                return;
            }
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }
}
