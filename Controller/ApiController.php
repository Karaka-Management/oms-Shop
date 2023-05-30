<?php
/**
 * Karaka
 *
 * PHP Version 8.1
 *
 * @package   Modules\Shop
 * @copyright Dennis Eichhorn
 * @license   OMS License 2.0
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

namespace Modules\Shop\Controller;

use Modules\Billing\Models\BillMapper;
use Modules\Billing\Models\BillStatus;
use Modules\ClientManagement\Models\ClientMapper;
use Modules\ItemManagement\Models\Item;
use Modules\ItemManagement\Models\ItemMapper;
use Modules\ItemManagement\Models\NullItem;
use Modules\Payment\Models\PaymentMapper;
use Modules\Payment\Models\PaymentStatus;
use phpOMS\Localization\ISO4217CharEnum;
use phpOMS\Message\Http\RequestStatusCode;
use phpOMS\Message\NotificationLevel;
use phpOMS\Message\RequestAbstract;
use phpOMS\Message\ResponseAbstract;
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
     * @param mixed            $data     Generic data
     *
     * @return void
     *
     * @api
     *
     * @since 1.0.0
     */
    public function apiItemFileDownload(RequestAbstract $request, ResponseAbstract $response, mixed $data = null) : void
    {
        // Handle public files
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

        // Handle private files
        // @todo: this is another example where it would be useful to have clients and items as models in the bill and bill element
        $client = ClientMapper::get()
            ->where('account', $request->header->account)
            ->execute();

        // @todo: only for sales invoice, currently also for offers
        $bills = BillMapper::getAll()
            ->with('elements')
            ->where('client', $client->id)
            ->where('status', BillStatus::ARCHIVED)
            ->where('elements/item', $request->getDataInt('item'))
            ->execute();

        $items = [];
        foreach ($bills as $bill) {
            $elements = $bill->getElements();

            foreach ($elements as $element) {
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

        $this->fillJsonResponse($request, $response, NotificationLevel::ERROR, '', '', []);
        $response->header->status = RequestStatusCode::R_403;
    }

    /**
     * Api method to create news article
     *
     * @param RequestAbstract  $request  Request
     * @param ResponseAbstract $response Response
     * @param mixed            $data     Generic data
     *
     * @return void
     *
     * @api
     *
     * @since 1.0.0
     */
    public function apiSchemaCreate(RequestAbstract $request, ResponseAbstract $response, mixed $data = null) : void
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

        // @todo: implement https://schema.org/Product
        $schema = [
            '@context'    => 'https://schema.org/',
            '@type'       => 'Product',
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
            //'isVariantOf' => '...',
        ];

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
     * @param mixed            $data     Generic data
     *
     * @return void
     *
     * @api
     *
     * @since 1.0.0
     */
    public function apiOneClickBuy(RequestAbstract $request, ResponseAbstract $response, mixed $data = null) : void
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

        if ($client->id > 0) {
            $paymentInfoMapper = PaymentMapper::getAll()
                ->where('account', $request->header->account)
                ->where('status', PaymentStatus::ACTIVATE);

            if ($request->hasData('payment_types')) {
                $paymentInfoMapper->where('type', $request->getDataList('payment_types'));
            }

            $paymentInfo = $paymentInfoMapper->execute();
        }

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
            ->where('l11n/type/title', ['name1', 'name2', 'name3'], 'IN')
            ->where('l11n/language', $bill->language);

        /** @var \Modules\ItemManagement\Models\Item $item */
        $item = $itemMapper->execute();

        // @todo: consider to first create an offer = cart and only when paid turn it into an invoice. This way it's also easy to analyse the conversion rate.

        $billElement = $this->app->moduleManager->get('Billing', 'ApiBill')->createBaseBillElement($client, $item, $bill, $request);
        $bill->addElement($billElement);

        $this->updateModel($request->header->account, $old, $bill, BillMapper::class, 'bill_element', $request->getOrigin());

        // @tood: make this configurable (either from the customer payment info or some item default setting)!!!
        if ($item->getAttribute('subscription')->value->getValue() === 1) {
            $response->header->status = RequestStatusCode::R_303;
            $response->header->set('Location', $item->getAttribute('one_click_pay_cc')->value->getValue(), true);
        } else {
            $this->app->moduleManager->get('Payment', 'Api')->setupStripe($request, $response, $bill, $data);
        }
    }
}
