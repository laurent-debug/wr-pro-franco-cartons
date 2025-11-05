<?php

declare(strict_types=1);

namespace WCProFrancoCartons;

use WC_Cart;

class Helpers
{
    public const TEXT_DOMAIN = 'wc-pro-franco-cartons';

    private ?bool $forceEnforcement = null;
    private array $notices = [];
    private bool $freeShippingEligible = false;
    private array $latestAnalysis = [];

    public function should_enforce(): bool
    {
        if ($this->forceEnforcement !== null) {
            return $this->forceEnforcement;
        }

        $should = false;

        if (function_exists('is_user_logged_in') && function_exists('wp_get_current_user')) {
            if (!is_user_logged_in()) {
                return false;
            }

            $user = wp_get_current_user();
            $roles = is_object($user) && isset($user->roles) ? (array) $user->roles : [];
            $should = in_array('wholesaler', $roles, true);
        } else {
            $should = true;
        }

        if (function_exists('apply_filters')) {
            /** @phpstan-ignore-next-line */
            $should = (bool) apply_filters('wc_pro_franco_cartons_should_enforce', $should);
        }

        return $should;
    }

    public function force_enforcement(?bool $value): void
    {
        $this->forceEnforcement = $value;
    }

    public function add_notice(string $message, string $type = 'notice'): void
    {
        $this->notices[] = ['message' => $message, 'type' => $type];

        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type);
        }
    }

    public function get_notices(): array
    {
        return $this->notices;
    }

    public function reset_notices(): void
    {
        $this->notices = [];
    }

    public function set_free_shipping_eligible(bool $eligible): void
    {
        $this->freeShippingEligible = $eligible;
    }

    public function is_free_shipping_eligible(): bool
    {
        return $this->freeShippingEligible;
    }

    public function set_latest_analysis(array $analysis): void
    {
        $this->latestAnalysis = $analysis;
    }

    public function get_latest_analysis(): array
    {
        return $this->latestAnalysis;
    }

    /**
     * @param array|WC_Cart $cart
     * @return array<int, array<string, mixed>>
     */
    public function describe_cart_items($cart): array
    {
        $items = $this->extract_cart_items($cart);
        $described = [];

        foreach ($items as $item) {
            $described[] = $this->describe_item($item);
        }

        return $described;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function describe_item($item): array
    {
        $quantity = $this->get_item_quantity($item);
        $lineTotal = $this->get_item_line_total($item);
        $name = $this->get_item_name($item);
        $tags = $this->get_item_tags($item);

        $lotSize = $this->get_item_lot_size($item);
        $slotFactor = $this->get_item_slot_factor($item);
        $requiresChf = false;

        $tagRules = $this->apply_tag_rules($tags, $lotSize, $slotFactor, $requiresChf);
        $lotSize = $tagRules['lot_size'];
        $slotFactor = $tagRules['slot_factor'];
        $requiresChf = $tagRules['requires_chf'];

        return [
            'name' => $name,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
            'tags' => $tags,
            'lot_size' => $lotSize,
            'slot_factor' => $slotFactor,
            'requires_chf' => $requiresChf,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function calculate_slots(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $lotSize = (int) ($item['lot_size'] ?? 1);
            $slotFactor = (float) ($item['slot_factor'] ?? 1.0);
            if ($qty <= 0 || $lotSize <= 0) {
                continue;
            }
            $lots = $qty / $lotSize;
            $total += $lots * $slotFactor;
        }

        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function calculate_total(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) ($item['line_total'] ?? 0.0);
        }

        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function has_slot_items(array $items): bool
    {
        foreach ($items as $item) {
            if (((float) ($item['slot_factor'] ?? 0.0)) > 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function has_chf_items(array $items): bool
    {
        foreach ($items as $item) {
            if (!empty($item['requires_chf'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function requires_chf_only(array $items): bool
    {
        $hasChf = false;
        $hasSlots = false;

        foreach ($items as $item) {
            if (!empty($item['requires_chf'])) {
                $hasChf = true;
            }

            if (((float) ($item['slot_factor'] ?? 0.0)) > 0.0) {
                $hasSlots = true;
            }
        }

        return $hasChf && !$hasSlots;
    }

    /**
     * @param array<string, mixed> $analysis
     */
    public function determine_eligibility(array $analysis, float $slotThreshold, float $chfThreshold): array
    {
        $slots = (float) ($analysis['slots'] ?? 0.0);
        $total = (float) ($analysis['total'] ?? 0.0);
        $requiresChfOnly = (bool) ($analysis['requires_chf_only'] ?? false);
        $hasSlotItems = (bool) ($analysis['has_slot_items'] ?? false);
        $hasChfItems = (bool) ($analysis['has_chf_items'] ?? false);

        $slotMet = $slotThreshold > 0.0 && $slots >= $slotThreshold;
        $chfMet = $chfThreshold > 0.0 && $total >= $chfThreshold;

        if ($requiresChfOnly || ($hasChfItems && !$hasSlotItems && $slotThreshold > 0.0)) {
            $eligible = $chfMet;
        } elseif ($slotThreshold > 0.0 && $chfThreshold > 0.0) {
            $eligible = $slotMet || $chfMet;
        } elseif ($slotThreshold > 0.0) {
            $eligible = $slotMet;
        } elseif ($chfThreshold > 0.0) {
            $eligible = $chfMet;
        } else {
            $eligible = false;
        }

        return [
            'eligible' => $eligible,
            'slot_met' => $slotMet,
            'chf_met' => $chfMet,
            'remaining_slots' => $slotThreshold > 0.0 ? max(0.0, $slotThreshold - $slots) : 0.0,
            'remaining_chf' => $chfThreshold > 0.0 ? max(0.0, $chfThreshold - $total) : 0.0,
        ];
    }

    public function format_requirements_message(array $eligibility, float $slotThreshold, float $chfThreshold): string
    {
        $parts = [];
        if ($slotThreshold > 0.0 && empty($eligibility['slot_met'])) {
            $remaining = $this->format_decimal((float) ($eligibility['remaining_slots'] ?? 0.0));
            $parts[] = sprintf($this->translate('add %s more slots'), $remaining);
        }

        if ($chfThreshold > 0.0 && empty($eligibility['chf_met'])) {
            $remaining = $this->format_decimal((float) ($eligibility['remaining_chf'] ?? 0.0));
            $parts[] = sprintf($this->translate('add CHF %s more to your order'), $remaining);
        }

        if (empty($parts)) {
            return $this->translate('Franco Cartons thresholds have not been met.');
        }

        $joined = $this->join_parts($parts);

        return sprintf($this->translate('To qualify for Franco Cartons free shipping please %s.'), $joined);
    }

    public function format_decimal(float $value, int $precision = 2): string
    {
        return number_format($value, $precision, '.', '');
    }

    private function join_parts(array $parts): string
    {
        $count = count($parts);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);
        return implode(', ', $parts) . $this->translate(' and ') . $last;
    }

    private function extract_cart_items($cart): array
    {
        if (is_array($cart)) {
            return $cart;
        }

        if ($cart instanceof WC_Cart) {
            return $cart->get_cart();
        }

        if (is_object($cart) && method_exists($cart, 'get_cart')) {
            return $cart->get_cart();
        }

        return [];
    }

    private function get_item_quantity($item): int
    {
        if (is_array($item)) {
            if (isset($item['quantity'])) {
                return (int) $item['quantity'];
            }

            if (isset($item['qty'])) {
                return (int) $item['qty'];
            }
        }

        if (is_object($item)) {
            if (isset($item->quantity)) {
                return (int) $item->quantity;
            }

            if (method_exists($item, 'get_quantity')) {
                return (int) $item->get_quantity();
            }
        }

        return 0;
    }

    private function get_item_line_total($item): float
    {
        if (is_array($item)) {
            if (isset($item['line_total'])) {
                return (float) $item['line_total'];
            }
            if (isset($item['subtotal'])) {
                return (float) $item['subtotal'];
            }
        }

        if (is_object($item)) {
            if (isset($item->line_total)) {
                return (float) $item->line_total;
            }
            if (method_exists($item, 'get_total')) {
                return (float) $item->get_total();
            }
        }

        return 0.0;
    }

    private function get_item_name($item): string
    {
        if (is_array($item) && isset($item['name'])) {
            return (string) $item['name'];
        }

        $product = $this->resolve_product_from_item($item);
        if ($product && method_exists($product, 'get_name')) {
            return (string) $product->get_name();
        }

        if (is_object($item) && isset($item->name)) {
            return (string) $item->name;
        }

        return $this->translate('Item');
    }

    private function get_item_tags($item): array
    {
        if (is_array($item) && isset($item['tags']) && is_array($item['tags'])) {
            return array_values(array_map([self::class, 'sanitize_value'], $item['tags']));
        }

        $product = $this->resolve_product_from_item($item);
        if ($product && method_exists($product, 'get_id')) {
            if (function_exists('wp_get_object_terms')) {
                $terms = wp_get_object_terms((int) $product->get_id(), 'product_tag', ['fields' => 'slugs']);
                if (!self::is_wp_error($terms)) {
                    return array_map([self::class, 'sanitize_value'], $terms);
                }
            }

            if (method_exists($product, 'get_tag_ids')) {
                $ids = (array) $product->get_tag_ids();
                $slugs = [];
                foreach ($ids as $id) {
                    if (function_exists('get_term')) {
                        $term = get_term((int) $id);
                        if ($term && !self::is_wp_error($term) && isset($term->slug)) {
                            $slugs[] = self::sanitize_value($term->slug);
                        }
                    }
                }
                if (!empty($slugs)) {
                    return $slugs;
                }
            }
        }

        if (is_object($item) && isset($item->tags) && is_array($item->tags)) {
            return array_values(array_map([self::class, 'sanitize_value'], $item->tags));
        }

        return [];
    }

    private function get_item_lot_size($item): int
    {
        if (is_array($item) && isset($item['lot_size'])) {
            return max(1, (int) $item['lot_size']);
        }

        $product = $this->resolve_product_from_item($item);
        if ($product && method_exists($product, 'get_meta')) {
            $value = $product->get_meta('_wc_fc_lot_size', true);
            if ($value !== '') {
                return max(1, (int) $value);
            }
        }

        return 1;
    }

    private function get_item_slot_factor($item): float
    {
        if (is_array($item) && isset($item['slot_factor'])) {
            return max(0.0, (float) $item['slot_factor']);
        }

        $product = $this->resolve_product_from_item($item);
        if ($product && method_exists($product, 'get_meta')) {
            $value = $product->get_meta('_wc_fc_slot_factor', true);
            if ($value !== '') {
                return max(0.0, (float) $value);
            }
        }

        return 1.0;
    }

    private static function is_wp_error($value): bool
    {
        if (function_exists('is_wp_error')) {
            return is_wp_error($value);
        }

        return false;
    }

    private static function sanitize_value($value): string
    {
        if (!is_scalar($value)) {
            $value = '';
        }
        $value = (string) $value;
        if (function_exists('sanitize_title')) {
            return sanitize_title($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\-]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function apply_tag_rules(array $tags, int $lotSize, float $slotFactor, bool $requiresChf): array
    {
        $tags = array_map([self::class, 'sanitize_value'], $tags);

        if (in_array('lot4_half_slot', $tags, true)) {
            $lotSize = 4;
            $slotFactor = 0.5;
        } elseif (in_array('lot2_half_slot', $tags, true)) {
            $lotSize = 2;
            $slotFactor = 0.5;
        }

        if (!empty(array_intersect($tags, ['black-garlic-kilo', 'black-garlic-unit']))) {
            $slotFactor = 0.0;
            $requiresChf = true;
            if ($lotSize <= 0) {
                $lotSize = 1;
            }
        }

        return [
            'lot_size' => $lotSize,
            'slot_factor' => $slotFactor,
            'requires_chf' => $requiresChf,
        ];
    }

    private function resolve_product_from_item($item)
    {
        if (is_array($item) && isset($item['data'])) {
            return $item['data'];
        }

        if (is_object($item) && isset($item->data)) {
            return $item->data;
        }

        if (is_object($item) && method_exists($item, 'get_product')) {
            return $item->get_product();
        }

        return $item instanceof \WC_Product ? $item : null;
    }

    public function translate(string $text): string
    {
        if (function_exists('__')) {
            return __( $text, self::TEXT_DOMAIN );
        }

        return $text;
    }
}
