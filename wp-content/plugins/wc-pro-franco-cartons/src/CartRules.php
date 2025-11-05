<?php

declare(strict_types=1);

namespace WCProFrancoCartons;

use WC_Cart;

class CartRules
{
    private Helpers $helpers;
    private Settings $settings;
    private LotsValidator $validator;
    private ShippingAdjuster $shippingAdjuster;

    public function __construct(Helpers $helpers, Settings $settings, LotsValidator $validator, ShippingAdjuster $shippingAdjuster)
    {
        $this->helpers = $helpers;
        $this->settings = $settings;
        $this->validator = $validator;
        $this->shippingAdjuster = $shippingAdjuster;
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('woocommerce_before_calculate_totals', [$this, 'capture_state'], 20, 1);
        add_action('woocommerce_check_cart_items', [$this, 'validate_current_cart']);
    }

    public function capture_state($cart): void
    {
        $this->evaluate($cart, false);
    }

    public function validate_current_cart(): void
    {
        if (!function_exists('WC')) {
            return;
        }

        $cart = WC()->cart ?? null;
        if (!$cart instanceof WC_Cart) {
            return;
        }

        $this->evaluate($cart, true);
    }

    private function evaluate($cart, bool $addNotices): void
    {
        if (!$this->helpers->should_enforce()) {
            $this->helpers->set_free_shipping_eligible(false);
            return;
        }

        $items = $this->helpers->describe_cart_items($cart);
        $analysis = [
            'items' => $items,
            'slots' => $this->helpers->calculate_slots($items),
            'total' => $this->helpers->calculate_total($items),
            'has_slot_items' => $this->helpers->has_slot_items($items),
            'has_chf_items' => $this->helpers->has_chf_items($items),
            'requires_chf_only' => $this->helpers->requires_chf_only($items),
            'invalid_lots' => $this->validator->validate_items($items),
        ];

        $this->helpers->set_latest_analysis($analysis);

        $slotThreshold = $this->settings->get_slot_threshold();
        $chfThreshold = $this->settings->get_chf_threshold();

        if ($slotThreshold <= 0.0 && $chfThreshold <= 0.0) {
            $this->helpers->set_free_shipping_eligible(false);
            if ($addNotices) {
                $this->helpers->add_notice($this->settings->get_missing_thresholds_message(), 'notice');
            }
            return;
        }

        if (!empty($analysis['invalid_lots'])) {
            $this->helpers->set_free_shipping_eligible(false);
            if ($addNotices) {
                foreach ($analysis['invalid_lots'] as $message) {
                    $this->helpers->add_notice($message, 'error');
                }
            }
            return;
        }

        $eligibility = $this->helpers->determine_eligibility($analysis, $slotThreshold, $chfThreshold);
        $this->helpers->set_free_shipping_eligible((bool) ($eligibility['eligible'] ?? false));

        if ($addNotices && empty($eligibility['eligible'])) {
            $this->helpers->add_notice(
                $this->helpers->format_requirements_message($eligibility, $slotThreshold, $chfThreshold),
                'notice'
            );
        }
    }
}
