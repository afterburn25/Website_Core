<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

/** Renders package-owned proposal/BEO wording for Core's immutable document store. */
final class EventDocuments
{
    public static function proposal(array $event, array $quote, string $businessName): string
    {
        $items = '';
        foreach ($quote['items'] ?? [] as $item) {
            $items .= '<tr><td>' . self::h((string) ($item['description'] ?? '')) . '</td><td>'
                . (int) ($item['quantity'] ?? 1) . '</td><td>$'
                . number_format(((int) ($item['line_total_minor'] ?? 0)) / 100, 2) . '</td></tr>';
        }
        return '<article><header><p>' . self::h($businessName) . '</p><h1>Catering proposal</h1><p>'
            . self::h((string) ($quote['quote_number'] ?? '')) . '</p></header>'
            . self::eventSummary($event)
            . '<h2>Quoted services</h2><table><thead><tr><th>Item</th><th>Quantity</th><th>Total</th></tr></thead><tbody>'
            . $items . '</tbody></table><p><strong>Proposal total: $'
            . number_format(((int) ($quote['total_minor'] ?? 0)) / 100, 2) . ' '
            . self::h((string) ($quote['currency'] ?? 'USD')) . '</strong></p><p>'
            . nl2br(self::h((string) ($quote['customer_notes'] ?? '')), false) . '</p>'
            . '<p>This proposal is an immutable snapshot of the current quotation. Availability is confirmed only through the accepted quotation and event status.</p></article>';
    }

    public static function beo(array $event, array $quote, string $businessName): string
    {
        $menu = '';
        foreach ($event['menu']['items'] ?? [] as $item) {
            $menu .= '<li>' . self::h((string) ($item['name'] ?? 'Menu item')) . '</li>';
        }
        $services = '';
        foreach ($event['operations']['lines'] ?? [] as $line) {
            $services .= '<li>' . self::h((string) ($line['name'] ?? 'Service')) . '</li>';
        }
        return '<article><header><p>' . self::h($businessName) . '</p><h1>Banquet Event Order</h1><p>Revision '
            . (int) ($event['revision'] ?? 1) . '</p></header>' . self::eventSummary($event)
            . '<h2>Menu</h2><ul>' . ($menu !== '' ? $menu : '<li>See accepted quotation.</li>') . '</ul>'
            . '<h2>Service and fulfillment</h2><p>'
            . self::h(ucfirst((string) ($event['fulfillment_method'] ?? 'Pending'))) . ' · '
            . self::h((string) ($event['fulfillment_window'] ?? 'Time pending')) . '</p><ul>' . $services . '</ul>'
            . '<h2>Run of show</h2><p>' . nl2br(self::h((string) ($event['run_of_show'] ?? 'To be finalized.')), false) . '</p>'
            . '<h2>Setup instructions</h2><p>' . nl2br(self::h((string) ($event['setup_instructions'] ?? 'To be finalized.')), false) . '</p>'
            . '<h2>Commercial reference</h2><p>' . self::h((string) ($quote['quote_number'] ?? 'Accepted quote'))
            . ' · $' . number_format(((int) ($quote['total_minor'] ?? 0)) / 100, 2) . ' '
            . self::h((string) ($quote['currency'] ?? 'USD')) . '</p></article>';
    }

    private static function eventSummary(array $event): string
    {
        $address = trim(implode(', ', array_filter([
            $event['venue'] ?? '', $event['address_line_1'] ?? '', $event['address_line_2'] ?? '',
            $event['city'] ?? '', $event['region'] ?? '', $event['postal_code'] ?? '',
        ])));
        return '<section><h2>Event details</h2><dl>'
            . '<dt>Event</dt><dd>' . self::h((string) ($event['title'] ?? 'Catering event')) . '</dd>'
            . '<dt>Customer</dt><dd>' . self::h((string) ($event['customer_name'] ?? '')) . '</dd>'
            . '<dt>Date and time</dt><dd>' . self::h(trim((string) ($event['event_date'] ?? '') . ' ' . (string) ($event['event_time'] ?? ''))) . '</dd>'
            . '<dt>Final headcount</dt><dd>' . (int) ($event['final_guest_count'] ?: $event['guest_count'] ?: 0) . '</dd>'
            . '<dt>Location</dt><dd>' . self::h($address !== '' ? $address : 'To be confirmed') . '</dd>'
            . '</dl></section>';
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
