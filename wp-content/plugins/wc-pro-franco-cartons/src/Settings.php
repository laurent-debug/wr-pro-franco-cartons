<?php

declare(strict_types=1);

namespace WCProFrancoCartons;

class Settings
{
    public const OPTION_SLOT_THRESHOLD = 'wc_pro_franco_cartons_slot_threshold';
    public const OPTION_CHF_THRESHOLD = 'wc_pro_franco_cartons_chf_threshold';
    private const TEXT_DOMAIN = 'wc-pro-franco-cartons';

    public function register(): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('woocommerce_shipping_settings', [$this, 'register_settings_section']);
        add_action('woocommerce_settings_save_shipping', [$this, 'save_settings']);
    }

    public function register_settings_section(array $settings): array
    {
        $fields = [
            [
                'title' => $this->translate('Franco Cartons thresholds'),
                'type' => 'title',
                'desc' => $this->translate('Configure the wholesaler slot and CHF requirements.'),
                'id' => 'wc_pro_franco_cartons_section_start',
            ],
            [
                'title' => $this->translate('Slot threshold'),
                'id' => self::OPTION_SLOT_THRESHOLD,
                'type' => 'number',
                'default' => '',
                'desc_tip' => $this->translate('Number of Franco Cartons slots required for free shipping.'),
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0',
                ],
            ],
            [
                'title' => $this->translate('CHF threshold'),
                'id' => self::OPTION_CHF_THRESHOLD,
                'type' => 'number',
                'default' => '',
                'desc_tip' => $this->translate('Minimum CHF total required for free shipping.'),
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0',
                ],
            ],
            [
                'type' => 'sectionend',
                'id' => 'wc_pro_franco_cartons_section_end',
            ],
        ];

        return array_merge($settings, $fields);
    }

    public function save_settings(): void
    {
        if (!class_exists('WC_Admin_Settings')) {
            return;
        }

        \WC_Admin_Settings::save_fields($this->register_settings_section([]));
    }

    public function get_slot_threshold(): float
    {
        $value = $this->get_option(self::OPTION_SLOT_THRESHOLD);
        return $value !== '' ? max(0.0, (float) $value) : 0.0;
    }

    public function get_chf_threshold(): float
    {
        $value = $this->get_option(self::OPTION_CHF_THRESHOLD);
        return $value !== '' ? max(0.0, (float) $value) : 0.0;
    }

    public function get_missing_thresholds_message(): string
    {
        return $this->translate('Franco Cartons thresholds are not configured. Set them in WooCommerce → Settings → Shipping.');
    }

    private function get_option(string $key)
    {
        if (function_exists('get_option')) {
            return get_option($key, '');
        }

        return '';
    }

    private function translate(string $text): string
    {
        if (function_exists('__')) {
            return __( $text, self::TEXT_DOMAIN );
        }

        return $text;
    }
}
