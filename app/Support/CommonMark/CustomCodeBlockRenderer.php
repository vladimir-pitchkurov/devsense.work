<?php

namespace App\Support\CommonMark;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * Custom renderer for fenced code blocks.
 * Parses the first line to check if it's a file path comment (e.g. `// app/Models/User.php`).
 * If a path is found, it renders a file header and exposes a `data-filepath` attribute.
 */
class CustomCodeBlockRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        FencedCode::assertInstanceOf($node);

        $codeContent = $node->getLiteral();
        $lines = explode("\n", $codeContent);
        $firstLine = $lines[0] ?? '';
        $filePath = $this->extractFilePath($firstLine);

        if ($filePath !== null) {
            array_shift($lines);
            $codeContent = implode("\n", $lines);
        }

        // Resolve code attributes (e.g. language classes)
        $attrs = $node->data->getData('attributes');
        $infoWords = $node->getInfoWords();
        if (\count($infoWords) !== 0 && $infoWords[0] !== '') {
            $class = $infoWords[0];
            if (! \str_starts_with($class, 'language-')) {
                $class = 'language-' . $class;
            }

            $attrs->append('class', $class);
        }

        // Build <code> tag
        $codeElement = new HtmlElement('code', $attrs->export(), Xml::escape($codeContent));

        // Build <pre> tag
        $preAttrs = [
            'class' => 'code-block__code',
        ];
        $preElement = new HtmlElement('pre', $preAttrs, $codeElement);

        // Build outer wrapper <div>
        $containerAttrs = [
            'class' => 'code-block',
        ];

        if ($filePath !== null) {
            $containerAttrs['data-filepath'] = $filePath;
            $header = new HtmlElement(
                'div',
                ['class' => 'code-block__header'],
                new HtmlElement('span', ['class' => 'code-block__filename'], Xml::escape($filePath))
            );

            return new HtmlElement('div', $containerAttrs, [$header, $preElement]);
        }

        return new HtmlElement('div', $containerAttrs, [$preElement]);
    }

    /**
     * Extracts a file path from a comment if it looks like a path or specific configuration filename.
     */
    private function extractFilePath(string $firstLine): ?string
    {
        $firstLine = trim($firstLine);

        // Match common comment syntaxes: // path, # path, /* path */
        if (preg_match('/^(?:\/\/\s*|#\s*|\/\*\s*)([a-zA-Z0-9_\-\.\/]+)(?:\s*\*\/)?$/', $firstLine, $matches)) {
            $potentialPath = trim($matches[1]);

            // Validate if it contains standard path/file characteristics
            if (
                str_contains($potentialPath, '.') ||
                str_contains($potentialPath, '/') ||
                in_array(strtolower($potentialPath), ['dockerfile', 'makefile'])
            ) {
                // Ensure no spaces (not a general sentence comment)
                if (! str_contains($potentialPath, ' ')) {
                    return $potentialPath;
                }
            }
        }

        return null;
    }
}
