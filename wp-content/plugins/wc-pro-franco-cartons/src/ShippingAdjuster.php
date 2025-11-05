<?php

declare(strict_types=1);

namespace WCProFrancoCartons;

class ShippingAdjuster
{
    private Helpers $helpers;
    private Settings $settings;

    public function __construct(Helpers $helpers, Settings $settings)
    {
        $this->helpers = $helpers;
        $this->settings = $settings;
    }

    public function register(): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('woocommerce_package_rates', [$this, 'inject_rate'], 20, 2);
    }

    public function inject_rate($rates, $package)
    {
        if (!$this->helpers->should_enforce()) {
            return $rates;
        }

        if (!$this->helpers->is_free_shipping_eligible()) {
            return $rates;
        }

        $slotThreshold = $this->settings->get_slot_threshold();
        $chfThreshold = $this->settings->get_chf_threshold();
        if ($slotThreshold <= 0.0 && $chfThreshold <= 0.0) {
            return $rates;
        }

        $rateId = 'wc_pro_franco_cartons_free_shipping';
        $label = $this->helpers->translate('Franco Cartons Free Shipping');

        if (is_array($rates) && isset($rates[$rateId])) {
            return $rates;
        }

        if (class_exists('WC_Shipping_Rate')) {
            $rates[$rateId] = new \WC_Shipping_Rate(
                $rateId,
                $label,
                0.0,
                [],
                'wc_pro_franco_cartons_free_shipping'
            );
        } else {
            $rates[$rateId] = [
                'id' => $rateId,
                'label' => $label,
                'cost' => 0.0,
            ];
        }

        return $rates;
    }
}
