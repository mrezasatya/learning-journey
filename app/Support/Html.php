<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Pembersih HTML untuk materi teks kaya (mencegah XSS — Brief 9.2). */
class Html
{
    private const ALLOWED = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote',
        'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'span', 'div', 'pre', 'code'];

    private const ATTRS = ['a' => ['href', 'title', 'target'], 'img' => ['src', 'alt', 'width', 'height'], 'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan']];

    public static function clean(?string $html): string
    {
        if (! $html || trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        foreach (iterator_to_array($xpath->query('//*')) as $el) {
            /** @var DOMElement $el */
            if ($el->getAttribute('id') === '__root') {
                continue;
            }
            $tag = strtolower($el->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'], true)) {
                $el->parentNode?->removeChild($el);
                continue;
            }
            if (! in_array($tag, self::ALLOWED, true)) {
                while ($el->firstChild) {
                    $el->parentNode->insertBefore($el->firstChild, $el);
                }
                $el->parentNode?->removeChild($el);
                continue;
            }
            foreach (iterator_to_array($el->attributes) as $attr) {
                $name = strtolower($attr->name);
                $ok = in_array($name, self::ATTRS[$tag] ?? [], true);
                if ($ok && in_array($name, ['href', 'src'], true)) {
                    $ok = (bool) preg_match('#^(https?://|/|\#|mailto:)#i', trim($attr->value));
                }
                if (! $ok) {
                    $el->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $root = $doc->getElementById('__root');
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }
}
