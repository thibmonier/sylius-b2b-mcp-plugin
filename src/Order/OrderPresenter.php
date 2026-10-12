<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

/**
 * Orders and carts as data for the assistant. Amounts are decimal strings in the order currency.
 * Colleagues are named, never with their e-mail or phone number.
 */
final class OrderPresenter
{
    /** @return array<string, mixed> */
    public function summary(OrderInterface $order, bool $own = true): array
    {
        $summary = [
            'number' => $order->getNumber(),
            'placed_at' => Text::date($order->getCheckoutCompletedAt()),
            'state' => $order->getState(),
            'payment_state' => $order->getPaymentState(),
            'shipping_state' => $order->getShippingState(),
            'currency' => $order->getCurrencyCode(),
            'total' => Text::money($order->getTotal()),
            'items_quantity' => $order->getTotalQuantity(),
        ];
        if (!$own) {
            $summary['placed_by'] = Text::clean($order->getCustomer()?->getFullName(), 100);
        }

        return $summary;
    }

    /** @return array<string, mixed> */
    public function detail(OrderInterface $order, bool $own = true): array
    {
        $address = $order->getShippingAddress();

        return $this->summary($order, $own) + [
            'items' => $this->items($order),
            'items_total' => Text::money($order->getItemsTotal()),
            'shipping_total' => Text::money($order->getShippingTotal()),
            'tax_total' => Text::money($order->getTaxTotal()),
            'promotion_total' => Text::money($order->getOrderPromotionTotal()),
            'shipping_methods' => array_values(array_filter(array_map(
                static fn (ShipmentInterface $shipment): ?string => Text::clean($shipment->getMethod()?->getName()),
                $order->getShipments()->toArray(),
            ))),
            'payment_methods' => array_values(array_filter(array_map(
                static fn (PaymentInterface $payment): ?string => Text::clean($payment->getMethod()?->getName()),
                $order->getPayments()->toArray(),
            ))),
            'shipping_address' => null === $address ? null : [
                'company' => Text::clean($address->getCompany()),
                'city' => Text::clean($address->getCity()),
                'postcode' => Text::clean($address->getPostcode(), 32),
                'country_code' => $address->getCountryCode(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function cart(OrderInterface $cart): array
    {
        return [
            'currency' => $cart->getCurrencyCode(),
            'items' => $this->items($cart),
            'items_total' => Text::money($cart->getItemsTotal()),
            'total' => Text::money($cart->getTotal()),
            'items_quantity' => $cart->getTotalQuantity(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function items(OrderInterface $order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            /** @var OrderItemInterface $item */
            $items[] = [
                'sku' => $item->getVariant()?->getCode(),
                'product' => Text::clean($item->getProductName() ?? $item->getProduct()?->getName()),
                'variant' => Text::clean($item->getVariantName() ?? $item->getVariant()?->getName()),
                'quantity' => $item->getQuantity(),
                'unit_price' => Text::money($item->getUnitPrice()),
                'total' => Text::money($item->getTotal()),
            ];
        }

        return $items;
    }
}
