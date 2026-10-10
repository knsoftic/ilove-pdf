<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Makes untrusted HTML safe to render with mPDF on the server.
 *
 * The PDF renderer can load images, stylesheets and fonts. To prevent
 * server-side request forgery and local file disclosure every resource
 * reference is removed unless it is an inline data: image. Scripts, frames
 * and event handlers are stripped as well.
 */
class HtmlSanitizer
{
    private const REMOVE_ELEMENTS = [
        'script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'link', 'meta', 'base',
        'audio', 'video', 'source', 'track', 'template', 'svg', 'math', 'portal', 'input', 'button', 'select', 'textarea',
    ];

    private const RESOURCE_ATTRIBUTES = ['src', 'srcset', 'background', 'data', 'poster', 'lowsrc', 'dynsrc', 'action', 'formaction', 'xlink:href', 'longdesc', 'usemap', 'cite'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        foreach (self::REMOVE_ELEMENTS as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            /** @var DOMElement $element */
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = trim($attribute->nodeValue ?? '');

                if (str_starts_with($name, 'on')) {
                    $element->removeAttribute($attribute->nodeName);
                } elseif (in_array($name, self::RESOURCE_ATTRIBUTES, true)) {
                    if (! $this->isInlineImage($value)) {
                        $element->removeAttribute($attribute->nodeName);
                    }
                } elseif ($name === 'href') {
                    if (! preg_match('#^(https?:|mailto:|\#)#i', $value)) {
                        $element->removeAttribute($attribute->nodeName);
                    }
                } elseif ($name === 'style') {
                    $element->setAttribute('style', $this->sanitizeCss($value));
                }
            }

            if (strtolower($element->nodeName) === 'style') {
                $element->nodeValue = $this->sanitizeCss($element->textContent);
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $head = $document->getElementsByTagName('head')->item(0);
        $styles = '';

        if ($head) {
            foreach ($head->getElementsByTagName('style') as $style) {
                $styles .= $document->saveHTML($style);
            }
        }

        $content = '';

        if ($body) {
            foreach ($body->childNodes as $child) {
                $content .= $document->saveHTML($child);
            }
        }

        return $styles.$content;
    }

    public function sanitizeCss(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';
        $css = preg_replace('#@import[^;]*;?#i', '', $css) ?? '';
        $css = preg_replace('#expression\s*\(#i', '(', $css) ?? '';
        $css = preg_replace('#behavior\s*:#i', 'x:', $css) ?? '';

        // Keep data: images, drop every other url(...).
        return preg_replace_callback('#url\s*\(\s*([\'"]?)(.*?)\1\s*\)#i', function ($m) {
            return $this->isInlineImage($m[2]) ? $m[0] : 'none';
        }, $css) ?? '';
    }

    private function isInlineImage(string $value): bool
    {
        return (bool) preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $value);
    }
}
