<?php

declare(strict_types=1);

namespace WCProFrancoCartons;

class Plugin
{
    private Helpers $helpers;
    private Settings $settings;
    private LotsValidator $validator;
    private CartRules $cartRules;
    private ShippingAdjuster $shippingAdjuster;

    public function __construct(
        ?Helpers $helpers = null,
        ?Settings $settings = null,
        ?LotsValidator $validator = null,
        ?CartRules $cartRules = null,
        ?ShippingAdjuster $shippingAdjuster = null
    ) {
        $this->helpers = $helpers ?? new Helpers();
        $this->settings = $settings ?? new Settings();
        $this->validator = $validator ?? new LotsValidator($this->helpers);
        $this->shippingAdjuster = $shippingAdjuster ?? new ShippingAdjuster($this->helpers, $this->settings);
        $this->cartRules = $cartRules ?? new CartRules($this->helpers, $this->settings, $this->validator, $this->shippingAdjuster);
    }

    public function boot(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        $this->settings->register();
        $this->cartRules->register();
        $this->shippingAdjuster->register();
    }
}
