<?php
/**
 * Jingga
 *
 * PHP Version 8.2
 *
 * @package   Modules\Shop
 * @copyright Dennis Eichhorn
 * @license   OMS License 2.0
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

namespace Modules\Shop\Controller;

use Modules\Admin\Models\AccountMapper;
use Modules\Billing\Models\BillElementMapper;
use Modules\Billing\Models\BillMapper;
use Modules\Billing\Models\BillStatus;
use Modules\ClientManagement\Models\ClientMapper;
use Modules\ItemManagement\Models\Item;
use Modules\ItemManagement\Models\ItemMapper;
use Modules\ItemManagement\Models\NullItem;
use Modules\Payment\Models\PaymentMapper;
use Modules\Payment\Models\PaymentStatus;
use phpOMS\Localization\ISO4217CharEnum;
use phpOMS\Message\Http\HttpRequest;
use phpOMS\Message\Http\HttpResponse;
use phpOMS\Message\Http\RequestStatusCode;
use phpOMS\Message\RequestAbstract;
use phpOMS\Message\ResponseAbstract;
use phpOMS\Stdlib\Base\NullAddress;
use phpOMS\System\MimeType;

/**
 * Api controller
 *
 * @package Modules\Shop
 * @license OMS License 2.0
 * @link    https://jingga.app
 * @since   1.0.0
 */
final class ApiController extends Controller
{
    /**
     * Api method to download item files
     *
     * @param RequestAbstract  $request  Request
     * @param ResponseAbstract $response Response
     * @param array            $data     Generic data
     *
     * @return void
     *
     * @api
     *
     * @since 1.0.0
     */
    public function apiItemFileDownload(RequestAbstract $request, ResponseAbstract $response, array $data = []) : void
    {
        // Handle public files
        /** @var \Modules\ItemManagement\Models\Item $item */
        $item = ItemMapper::get()
            ->with('files')
            ->with('files/types')
            ->where('id', $request->getDataInt('item'))
            ->execute();

        $itemFiles = $item->files;
        foreach ($itemFiles as $file) {
            if ($file->id === $request->getDataInt('id')
                && ($file->hasMediaTypeName('item_demo_download')
                    || $file->hasMediaTypeName('item_public_download'))
            ) {
                $this->app->moduleManager->get('Media', 'Api')
                    ->apiMediaExport($request, $response, ['ignorePermission' => true]);

                return;
            }
        }

        // @todo only for sales invoice, currently also for offers
        /** @var \Modules\Billing\Models\Bill[] $bills */
        $bills = BillMapper::getAll()
            ->with('client')
            ->with('elements')
            ->where('client/account', $request->header->account)
            ->where('status', BillStatus::ARCHIVED)
            ->where('elements/item', $request->getDataInt('item'))
            ->execute();

        $items = [];
        foreach ($bills as $bill) {
            $elements = $bill->elements;

            foreach ($elements as $element) {
                /** @var \Modules\ItemManagement\Models\Item $item */
                $item = ItemMapper::get()
                    ->with('files')
                    ->with('files/type')
                    ->where('id', $element->item)
                    ->execute();

                $items[$item->id] = $item;
            }
        }

        foreach ($items as $item) {
            $files = $item->files;

            foreach ($files as $file) {
                if ($file->id === $request->getDataInt('id')
                    && $file->hasMediaTypeName('item_purchase_download')
                ) {
                    $this->app->moduleManager->get('Media', 'Api')
                        ->apiMediaExport($request, $response, ['ignorePermission' => true]);

                    return;
                }
            }
        }

        $response->header->status = RequestStatusCode::R_403;
        $this->createInvalidReturnResponse($request, $response, $item);
    }

    /**
     * Api method to create news article
     *
     * @param RequestAbstract  $request  Request
     * @param ResponseAbstract $response Response
     * @param array            $data     Generic data
     *
     * @return void
     *
     * @api
     *
     * @since 1.0.0
     */
    public function apiSchemaCreate(RequestAbstract $request, ResponseAbstract $response, array $data = []) : void
    {
        $schema = $this->buildSchema(new NullItem(), $request);

        $response->header->set('Content-Type', MimeType::M_JSON . '; charset=utf-8', true);
        $response->set($request->uri->__toString(), $schema);
    }

    /**
     * Method to create a schema from an item
     *
     * @param Item            $item    Item to create the schema from
     * @param RequestAbstract $request Request
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function buildSchema(Item $item, RequestAbstract $request) : array
    {
        $images = $item->getFilesByTypeName('shop_primary_image');

        $schema = [
            '@context'    => 'https://schema.org/',
            '@type'       => 'Product',
            'identifier'  => $item->number,
            'name'        => $item->getL11n('name1')->content,
            'description' => $item->getL11n('description_short')->content,
            'image'       => [
            ],
            'offers' => [
                '@type'         => 'Offer',
                'priceCurrency' => ISO4217CharEnum::_EUR,
                'price'         => $item->salesPrice->getAmount(),
                'availability'  => 'http://schema.org/InStock',
            ],
        ];

        if (!empty($attr = $item->getAttribute('brand')->value->getValue())) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('model')->value->getValue())) {
            $schema['model'] = $attr;
        }

        if (!empty($attr = $item->getAttribute('color')->value->getValue())) {
            $schema['color'] = $attr;
        }

        if (!empty($attr = $item->getAttribute('country_of_origin')->value->getValue())) {
            $schema['countryOfOrigin'] = [
                '@type'      => 'Country',
                'identifier' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('country_of_assembly')->value->getValue())) {
            $schema['countryOfAssembly'] = $attr;
        }

        if (!empty($attr = $item->getAttribute('country_of_last_processing')->value->getValue())) {
            $schema['countryOfLastProcessing'] = $attr;
        }

        if (!empty($attr = $item->getAttribute('gtin')->value->getValue())) {
            $schema['gtin'] = $attr;
        }

        if (!empty($attr = $item->getAttribute('release_date')->value->getValue())) {
            $schema['releasedate'] = $attr;
        }

        if (!empty($attr = $item->getAttribute('weight')->value->getValue())) {
            $schema['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('width')->value->getValue())) {
            $schema['width'] = [
                '@type' => 'QuantitativeValue',
                'value' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('height')->value->getValue())) {
            $schema['height'] = [
                '@type' => 'QuantitativeValue',
                'value' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('manufacturer')->value->getValue())) {
            $schema['manufacturer'] = [
                '@type'     => 'Organization',
                'legalName' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('variantof')->value->getValue())) {
            $schema['isVariantOf'] = [
                '@type'          => 'ProductGroup',
                'productGroupID' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('accessoryfor')->value->getValue())) {
            $schema['isAccessoryOrSparePartFor'] = [];
            $schema['isAccessoryOrSparePartFor'][] = [
                '@type'      => 'Product',
                'identifier' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('sparepartfor')->value->getValue())) {
            if (!isset($schema['isAccessoryOrSparePartFor'])) {
                $schema['isAccessoryOrSparePartFor'] = [];
            }

            $schema['isAccessoryOrSparePartFor'][] = [
                '@type'      => 'Product',
                'identifier' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('consumablefor')->value->getValue())) {
            $schema['isConsumableFor'] = [
                '@type'      => 'Product',
                'identifier' => $attr,
            ];
        }

        if (!empty($attr = $item->getAttribute('isfamilyfriendly')->value->getValue())) {
            $schema['isFamilyFriendly'] = (bool) $attr;
        }

        foreach ($images as $image) {
            $schema['image'][] = $request->uri->getBase() . '/' . $image->getPath();
        }

        return $schema;
    }

    /**
     * Api method to create news article
     *
     * @param RequestAbstract  $request  Request
     * @param ResponseAbstract $response Response
     * @param array            $data     Generic data
     *
     * @return void
     *
     * @api
     *
     * @since 1.0.0
     */
    public function apiOneClickBuy(RequestAbstract $request, ResponseAbstract $response, array $data = []) : void
    {
        /** @var \Modules\ClientManagement\Models\Client $client */
        $client = ClientMapper::get()
            ->with('mainAddress')
            ->with('attributes')
            ->with('attributes/type')
            ->with('attributes/value')
            ->with('account')
            ->where('account', $request->header->account)
            ->execute();

        // Create client if no client created for this account
        if ($client->id === 0) {
            /** @var \Modules\Admin\Models\Account $account */
            $account = AccountMapper::get()
                ->with('addresses')
                ->where('id', $request->header->account)
                ->execute();

            // @todo what if the primary address is not in position 1?
            $address = \reset($account->addresses);
            $address = $address === false ? new NullAddress() : $address;

            $internalRequest  = new HttpRequest();
            $internalResponse = new HttpResponse();

            $internalRequest->header->account = $request->header->account;
            $internalRequest->setData('account', $request->header->account);
            $internalRequest->setData('number', 100000 + $request->header->account);
            $internalRequest->setData('address', $request->getDataString('address') ?? $address->address);
            $internalRequest->setData('postal', $request->getDataString('postal') ?? $address->postal);
            $internalRequest->setData('city', $request->getDataString('city') ?? $address->city);
            $internalRequest->setData('country', $request->getDataString('country') ?? $address->country);
            $internalRequest->setData('state', $request->getDataString('state') ?? $address->state);
            $internalRequest->setData('vat_id', $request->getDataString('vat_id') ?? '');
            $internalRequest->setData('unit', $request->getDataInt('unit'));

            $this->app->moduleManager->get('ClientManagement', 'Api')->apiClientCreate($internalRequest, $internalResponse);

            /** @var \Modules\ClientManagement\Models\Client $client */
            $client = ClientMapper::get()
                ->with('mainAddress')
                ->with('attributes')
                ->with('attributes/type')
                ->with('attributes/value')
                ->with('account')
                ->where('account', $request->header->account)
                ->where('attributes/type/name', [
                    'segment', 'section', 'client_group', 'client_type',
                    'sales_tax_code',
                ], 'IN')
                ->execute();
        }

        $paymentInfoMapper = PaymentMapper::getAll()
            ->where('account', $request->header->account)
            ->where('status', PaymentStatus::ACTIVATE);

        if ($request->hasData('payment_types')) {
            $paymentInfoMapper->where('type', $request->getDataList('payment_types'));
        }

        /** @var \Modules\Payment\Models\Payment[] $paymentInfo */
        $paymentInfo = $paymentInfoMapper->execute();

        $request->setData('client', $client->id, true);
        $bill = $this->app->moduleManager->get('Billing', 'ApiBill')->createBaseBill($client, $request);
        $this->app->moduleManager->get('Billing', 'ApiBill')->createBillDatabaseEntry($bill, $request);

        $old = clone $bill;

        $itemMapper = $request->hasData('item')
            ? ItemMapper::get()
                ->where('id', (int) $request->getData('item'))
            : ItemMapper::get()
                ->where('number', (string) $request->getData('number'));

        $itemMapper->with('l11n')
            ->with('attributes')
            ->with('attributes/type')
            ->with('attributes/value')
            ->with('l11n/type')
            ->where('l11n/type/title', ['name1', 'name2'], 'IN')
            ->where('l11n/language', $bill->language)
            ->where('attributes/type/name', [
                'segment', 'section', 'sales_group', 'product_group', 'product_type',
                'sales_tax_code', 'purchase_tax_code', 'costcenter', 'costobject',
                'default_purchase_container', 'default_sales_container',
                'one_click_pay_cc', 'subscription',
            ], 'IN');

        /** @var \Modules\ItemManagement\Models\Item $item */
        $item = $itemMapper->execute();

        // @todo consider to first create an offer = cart and only when paid turn it into an invoice. This way it's also easy to analyse the conversion rate.

        $billElement = $this->app->moduleManager->get('Billing', 'ApiBill')->createBaseBillElement($item, $bill, $request);
        $bill->addElement($billElement);

        $this->createModel($request->header->account, $billElement, BillElementMapper::class, 'bill_element', $request->getOrigin());
        $this->updateModel($request->header->account, $old, $bill, BillMapper::class, 'bill', $request->getOrigin());

        // @todo make this configurable (either from the customer payment info or some item default setting)!!!
        if ($item->getAttribute('subscription')->value->getValue() === 1) {
            $response->header->status = RequestStatusCode::R_303;
            $response->header->set(
                'Location',
                $item->getAttribute('one_click_pay_cc')->value->valueStr ?? '',
                true
            );
        } else {
            $this->app->moduleManager->get('Payment', 'Api')->setupStripe($request, $response, $bill, $data);
        }
    }
}
