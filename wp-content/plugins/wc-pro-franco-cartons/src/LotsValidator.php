<?php

declare(strict_types=1);

namespace WCProFrancoCartons;

class LotsValidator
{
    private Helpers $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    public function validate_items(array $items): array
    {
        $messages = [];

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $lotSize = (int) ($item['lot_size'] ?? 1);
            $name = (string) ($item['name'] ?? '');

            if ($lotSize > 0 && $quantity > 0 && $quantity % $lotSize !== 0) {
                $messages[] = sprintf(
                    $this->helpers->translate('“%s” must be ordered in multiples of %d.'),
                    $name,
                    $lotSize
                );
            }
        }

        return $messages;
    }
}
