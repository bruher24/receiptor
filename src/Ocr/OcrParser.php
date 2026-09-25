<?php

namespace App\Ocr;

final class OcrParser
{
    public function parse(string $tsv): array
    {
        $rows = preg_split('/\R/', trim($tsv));

        if ($rows === false || $rows === []) {
            return [];
        }

        array_shift($rows);
        $currentLineKey = null;
        $currentLineData = null;
        $lines = [];
        $words = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = explode("\t", $row);

            if (count($columns) !== 12) {
                continue;
            }

            [
                $level,
                $pageNum,
                $blockNum,
                $parNum,
                $lineNum,
                $wordNum,
                $left,
                $top,
                $width,
                $height,
                $confidence,
                $text,
            ] = $columns;

            if ((int)$level !== 5 || $text === '') {
                continue;
            }

            $lineKey = "{$pageNum}:{$blockNum}:{$parNum}:{$lineNum}";

            if (!empty($currentLineKey) && $lineKey !== $currentLineKey) {
                $lines[] = new OcrLine(
                    page: $pageNum,
                    block: $blockNum,
                    paragraph: $parNum,
                    line: $lineNum,
                    words: $words
                );

                $words = [];
                $currentLineKey = $lineKey;
            }

            $words[] = new OcrWord(
                page: $pageNum,
                block: $blockNum,
                paragraph: $parNum,
                line: $lineNum,
                word: $wordNum,
                left: $left,
                top: $top,
                width: $width,
                height: $height,
                confidence: $confidence,
                text: $text
            );

            $currentLineData = [
                'page' => $pageNum,
                'block' => $blockNum,
                'paragraph' => $parNum,
                'line' => $lineNum,
            ];
        }

        if (!empty($words)) {
            $lines[] = new OcrLine(
                page: $currentLineData['page'],
                block: $currentLineData['block'],
                paragraph: $currentLineData['paragraph'],
                line: $currentLineData['line'],
                words: $words
            );
        }

        return $lines;
    }
}
