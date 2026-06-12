<?php

namespace App\Services\Onec;

use App\Models\OnecExchangeSession;
use App\Models\Order;
use Illuminate\Support\Collection;

class OrdersExporter
{
    public function export(?string $since = null): string
    {
        $session = OnecExchangeSession::create([
            'direction' => 'export',
            'type' => 'orders',
            'status' => 'started',
            'started_at' => now(),
        ]);

        try {
            $query = Order::with(['items.product', 'items.variant', 'user'])
                ->whereIn('order_status', ['placed', 'confirmed', 'completed', 'cancelled'])
                ->orderBy('id');

            if ($since) {
                $query->where('updated_at', '>=', $since);
            } else {
                // По умолчанию — заказы за последние 24 часа
                $query->where('created_at', '>=', now()->subDay());
            }

            $orders = $query->get();
            $xml = $this->buildXml($orders);

            $session->markSuccess(['records_processed' => $orders->count()]);

            return $xml;
        } catch (\Throwable $e) {
            $session->markError($e->getMessage());
            throw $e;
        }
    }

    private function buildXml(Collection $orders): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('КоммерческаяИнформация');
        $root->setAttribute('xmlns', 'urn:1C.ru:commerceml_2');
        $root->setAttribute('ВерсияСхемы', '2.05');
        $root->setAttribute('ДатаФормирования', now()->toIso8601String());
        $dom->appendChild($root);

        $ordersEl = $dom->createElement('Документы');
        $root->appendChild($ordersEl);

        foreach ($orders as $order) {
            $doc = $dom->createElement('Документ');
            $ordersEl->appendChild($doc);

            $this->appendEl($dom, $doc, 'Ид', $order->onec_uuid ?? $order->order_number);
            $this->appendEl($dom, $doc, 'Номер', $order->order_number);
            $this->appendEl($dom, $doc, 'Дата', $order->created_at->format('Y-m-d'));
            $this->appendEl($dom, $doc, 'ХозОперация', 'Заказ товара');
            $this->appendEl($dom, $doc, 'Роль', 'Продавец');
            $this->appendEl($dom, $doc, 'Валюта', $order->currency ?? 'RUB');
            $this->appendEl($dom, $doc, 'Сумма', number_format((float) $order->total, 2, '.', ''));
            $this->appendEl($dom, $doc, 'СтатусЗаказа', $this->mapStatus($order->order_status));

            // Контрагент
            $contragent = $dom->createElement('Контрагенты');
            $doc->appendChild($contragent);
            $agent = $dom->createElement('Контрагент');
            $contragent->appendChild($agent);
            $this->appendEl($dom, $agent, 'Наименование', $order->customer_name ?? '');
            $this->appendEl($dom, $agent, 'Email', $order->customer_email ?? '');
            $this->appendEl($dom, $agent, 'Телефон', $order->customer_phone ?? '');
            $this->appendEl($dom, $agent, 'Роль', 'Покупатель');

            // Товары
            $goods = $dom->createElement('Товары');
            $doc->appendChild($goods);

            foreach ($order->items as $item) {
                $good = $dom->createElement('Товар');
                $goods->appendChild($good);

                $extId = $item->variant?->external_id ?? $item->product?->external_id ?? '';
                $this->appendEl($dom, $good, 'Ид', $extId);
                $this->appendEl($dom, $good, 'Наименование', $item->product_name ?? '');
                $this->appendEl($dom, $good, 'Артикул', $item->product?->sku ?? '');
                $this->appendEl($dom, $good, 'КоличествоУпаковок', (string) $item->quantity);
                $this->appendEl($dom, $good, 'ЦенаЗаЕдиницу', number_format((float) $item->price, 2, '.', ''));
                $this->appendEl($dom, $good, 'Сумма', number_format((float) ($item->price * $item->quantity), 2, '.', ''));
            }

            // Реквизиты
            $reqs = $dom->createElement('ЗначенияРеквизитов');
            $doc->appendChild($reqs);
            $this->appendRequisite($dom, $reqs, 'СпособОплаты', $order->payment_method ?? '');
            $this->appendRequisite($dom, $reqs, 'СпособДоставки', $order->delivery_method ?? '');
            $this->appendRequisite($dom, $reqs, 'АдресДоставки', $order->delivery_address ?? '');
            $this->appendRequisite($dom, $reqs, 'СтатусОплаты', $order->payment_status ?? '');
        }

        return $dom->saveXML();
    }

    private function appendEl(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private function appendRequisite(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $req = $dom->createElement('ЗначениеРеквизита');
        $parent->appendChild($req);
        $this->appendEl($dom, $req, 'Наименование', $name);
        $this->appendEl($dom, $req, 'Значение', $value);
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'placed' => 'Новый',
            'confirmed' => 'Подтверждён',
            'completed' => 'Доставлен',
            'cancelled' => 'Отменён',
            default => $status,
        };
    }
}
