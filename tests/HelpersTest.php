<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WCProFrancoCartons\Helpers;
use WCProFrancoCartons\LotsValidator;

final class HelpersTest extends TestCase
{
    public function test_describe_items_applies_tag_rules(): void
    {
        $helpers = new Helpers();

        $cart = [
            [
                'name' => 'Lot 4 Half Slot Product',
                'quantity' => 8,
                'line_total' => 160.0,
                'tags' => ['Lot4_Half_Slot'],
            ],
            [
                'name' => 'Black Garlic Kilo',
                'quantity' => 3,
                'line_total' => 90.0,
                'tags' => ['black-garlic-kilo'],
            ],
        ];

        $items = $helpers->describe_cart_items($cart);

        self::assertCount(2, $items);
        self::assertSame(4, $items[0]['lot_size']);
        self::assertSame(0.5, $items[0]['slot_factor']);
        self::assertFalse($items[0]['requires_chf']);

        self::assertSame(0.0, $items[1]['slot_factor']);
        self::assertTrue($items[1]['requires_chf']);
    }

    public function test_calculation_helpers(): void
    {
        $helpers = new Helpers();

        $items = [
            [
                'name' => 'Half Slot Lots',
                'quantity' => 8,
                'line_total' => 160.0,
                'lot_size' => 4,
                'slot_factor' => 0.5,
                'requires_chf' => false,
            ],
            [
                'name' => 'Black Garlic',
                'quantity' => 3,
                'line_total' => 90.0,
                'lot_size' => 1,
                'slot_factor' => 0.0,
                'requires_chf' => true,
            ],
        ];

        self::assertSame(1.0, $helpers->calculate_slots($items));
        self::assertSame(250.0, $helpers->calculate_total($items));
        self::assertTrue($helpers->has_slot_items($items));
        self::assertTrue($helpers->has_chf_items($items));
        self::assertFalse($helpers->requires_chf_only($items));

        $analysis = [
            'slots' => $helpers->calculate_slots($items),
            'total' => $helpers->calculate_total($items),
            'requires_chf_only' => $helpers->requires_chf_only($items),
            'has_slot_items' => $helpers->has_slot_items($items),
            'has_chf_items' => $helpers->has_chf_items($items),
        ];

        $eligibility = $helpers->determine_eligibility($analysis, 1.0, 400.0);
        self::assertTrue($eligibility['eligible']);
        self::assertTrue($eligibility['slot_met']);
        self::assertFalse($eligibility['chf_met']);

        $chfRequiredAnalysis = [
            'slots' => 0.0,
            'total' => 90.0,
            'requires_chf_only' => true,
            'has_slot_items' => false,
            'has_chf_items' => true,
        ];

        $eligibility = $helpers->determine_eligibility($chfRequiredAnalysis, 1.0, 120.0);
        self::assertFalse($eligibility['eligible']);
        self::assertFalse($eligibility['chf_met']);

        $eligibility = $helpers->determine_eligibility($chfRequiredAnalysis, 1.0, 80.0);
        self::assertTrue($eligibility['eligible']);
        self::assertTrue($eligibility['chf_met']);
    }

    public function test_lots_validator_detects_invalid_quantities(): void
    {
        $helpers = new Helpers();
        $validator = new LotsValidator($helpers);

        $items = [
            [
                'name' => 'Strict Lot Product',
                'quantity' => 3,
                'lot_size' => 2,
            ],
            [
                'name' => 'Valid Lot Product',
                'quantity' => 4,
                'lot_size' => 2,
            ],
        ];

        $messages = $validator->validate_items($items);

        self::assertCount(1, $messages);
        self::assertStringContainsString('multiples of 2', $messages[0]);
    }
}
