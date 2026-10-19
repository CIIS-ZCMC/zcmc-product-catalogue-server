<?php

namespace App\Services;

use App\Models\Product;

/**
 * Service responsible for ZCMC Procurement Uniform Naming verification & suggestions.
 * Following SRP: Handles naming rules and compliance audits.
 */
class UniformNamingService
{
    /**
     * Forbidden/vague terms that must not appear in standardized procurement specifications.
     */
    protected array $vagueTerms = [
        'GOOD QUALITY',
        'CHEAP',
        'STANDARD',
        'REGULAR',
        'HIGH QUALITY',
        'BEST',
        'GENUINE',
        'ORIGINAL',
    ];

    /**
     * Validate an item description against ZCMC uniform naming standards.
     *
     * Format: [GENERIC NOUN], [SPECIFICATION 1], [SPECIFICATION 2], [PACKAGING / SIZE]
     * Example: "PAPER, MULTICOPY, A4, 80GSM, 500 SHEETS/REAM"
     */
    public function validate(string $description): array
    {
        $description = trim($description);

        if (empty($description)) {
            return [
                'is_valid' => false,
                'score' => 0,
                'formatted_suggestion' => '',
                'feedback' => ['Description cannot be empty.'],
                'suggestions' => [],
                'similar_items' => [],
            ];
        }

        $feedback = [];
        $suggestions = [];
        $score = 100;

        // Rule 1: Upper case check
        if ($description !== strtoupper($description)) {
            $score -= 20;
            $feedback[] = 'Description must be in UPPERCASE for uniform hospital procurement naming.';
            $suggestions[] = 'Converted to: '.strtoupper($description);
        }

        // Rule 2: Comma separation structure
        $parts = array_filter(array_map('trim', explode(',', $description)));
        if (count($parts) < 2) {
            $score -= 30;
            $feedback[] = 'Uniform naming must follow: [GENERIC NOUN], [SPECIFICATION / MODIFIER], [SIZE / PACKAGING]. Example: "GLOVES, EXAMINATION, NITRILE, MEDIUM".';
        } else {
            $primaryNoun = $parts[0];
            if (strlen($primaryNoun) < 2) {
                $score -= 20;
                $feedback[] = 'Primary generic item noun is too short.';
            }
        }

        // Rule 3: Avoid vague subjective words
        foreach ($this->vagueTerms as $vague) {
            if (stripos($description, $vague) !== false) {
                $score -= 15;
                $feedback[] = "Avoid vague adjective '{$vague}'. Use objective technical specs (e.g., 80gsm, 500ml, sterile).";
            }
        }

        // Rule 4: Duplicate & similarity check in database
        $words = explode(' ', trim(str_replace(',', ' ', $description)));
        $firstWord = $words[0];
        $similar = [];
        if (strlen($firstWord) >= 3) {
            $similar = Product::where('item_description', 'like', "%{$firstWord}%")
                ->limit(5)
                ->get(['id', 'erp_item_code', 'item_description', 'uom', 'category_key'])
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'erp_item_code' => $item->erp_item_code,
                        'item_description' => $item->item_description,
                        'uom' => $item->uom,
                        'category_path' => $item->full_category_path,
                    ];
                });
        }

        return [
            'is_valid' => $score >= 70,
            'score' => max(0, $score),
            'formatted_suggestion' => strtoupper($description),
            'feedback' => $feedback,
            'similar_items' => $similar,
        ];
    }

    /**
     * Construct a standardized uniform name from components.
     */
    public function format(string $noun, array $specifications = []): string
    {
        $parts = [strtoupper(trim($noun))];
        foreach ($specifications as $spec) {
            $trimmed = strtoupper(trim($spec));
            if (! empty($trimmed)) {
                $parts[] = $trimmed;
            }
        }

        return implode(', ', $parts);
    }
}
