<?php

namespace App\Analysis;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GroqReceiptAnalyzer implements ReceiptAnalyzerInterface
{

    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $apiKey,
    )
    {
    }

    public function analyze(string $ocrText): ReceiptAnalysisResult
    {
        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'openai/gpt-oss-20b',
                'reasoning_effort' => 'low',
                'max_completion_tokens' => 4096,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<PROMPT
You extract structured data from Russian fiscal receipts.

The input is OCR text and may contain recognition errors, missing lines,
incorrect characters and reordered information.

Extract:
- merchant
- INN
- purchase date and time
- all receipt items
- quantity
- unit price
- total price
- receipt total

Prices must be returned as integer kopecks.

You may correct obvious OCR errors only when the correction is strongly
supported by the OCR text itself.

Do not invent products, prices, quantities, dates, merchants or other data.

If a value cannot be determined reliably, return null where the schema allows it.

The OCR may contain labels and fields that are separated from their values.
Use the entire document to associate them correctly.

For receipt items, match each product with its corresponding price,
quantity and total even if those values appear later in the OCR text.

For purchased_at, return the date and time in ISO 8601 format:
YYYY-MM-DDTHH:MM:SS+03:00.

If the timezone is not present in the receipt, use null only if the
date/time cannot be determined. Do not invent a timezone.

The receipt may contain many items. Do not omit any item.

Identify every distinct product in the OCR before generating the final
JSON.

Every distinct product must appear exactly once in "items".

Do not stop after finding only some products.

Before returning the final answer, verify that all products found in
the OCR are included in "items".
PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => $ocrText,
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'receipt_analysis',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'merchant' => [
                                    'type' => ['string', 'null'],
                                ],
                                'inn' => [
                                    'type' => ['string', 'null'],
                                ],
                                'purchased_at' => [
                                    'type' => ['string', 'null'],
                                ],
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                            ],
                                            'quantity' => [
                                                'type' => 'integer',
                                            ],
                                            'unit_price' => [
                                                'type' => 'integer',
                                            ],
                                            'total_price' => [
                                                'type' => 'integer',
                                            ],
                                        ],
                                        'required' => [
                                            'name',
                                            'quantity',
                                            'unit_price',
                                            'total_price',
                                        ],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'total_amount' => [
                                    'type' => 'integer',
                                ],
                            ],
                            'required' => [
                                'merchant',
                                'inn',
                                'purchased_at',
                                'items',
                                'total_amount',
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ],
        ]);

        try {
            $data = $response->toArray();
        } catch (\Throwable $e) {
            dump($response->getStatusCode());
            dump($response->getContent(false));
            throw $e;
        }

        $content = $data['choices'][0]['message']['content'];

        $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $this->createResult($result);
    }

    private function createResult(array $data): ReceiptAnalysisResult
    {
        $items = array_map(
            static fn(array $item): ReceiptItemData => new ReceiptItemData(
                name: $item['name'],
                quantity: $item['quantity'],
                unitPrice: $item['unit_price'],
                totalPrice: $item['total_price'],
            ),
            $data['items'],
        );

        $purchasedAt = isset($data['purchased_at'])
            ? new \DateTimeImmutable($data['purchased_at'])
            : null;

        return new ReceiptAnalysisResult(
            merchant: $data['merchant'],
            inn: $data['inn'],
            purchasedAt: $purchasedAt,
            items: $items,
            totalAmount: $data['total_amount']
        );
    }

}
