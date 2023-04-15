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

use Modules\ClientManagement\Models\ClientMapper;
use Modules\ClientManagement\Models\NullClient;
use Modules\ItemManagement\Models\Item;
use Modules\ItemManagement\Models\ItemMapper;
use Modules\ItemManagement\Models\NullItem;
use Modules\Payment\Models\PaymentMapper;
use Modules\Payment\Models\PaymentStatus;
use phpOMS\Autoloader;
use phpOMS\Localization\ISO4217CharEnum;
use phpOMS\Message\Http\HttpRequest;
use phpOMS\Message\Http\HttpResponse;
use phpOMS\Message\Http\RequestStatusCode;
use phpOMS\Message\RequestAbstract;
use phpOMS\Message\ResponseAbstract;
use phpOMS\System\MimeType;
use phpOMS\Uri\HttpUri;

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
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $item->getL11n('name1')->description,
            'description' => $item->getL11n('description_short')->description,
            'image' => [
            ],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => ISO4217CharEnum::_EUR,
                'price' => $item->salesPrice->getAmount(),
                'availability' => 'http://schema.org/InStock',
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

        if (!($client instanceof NullClient)) {
            $paymentInfoMapper = PaymentMapper::getAll()
                ->where('account', $request->header->account)
                ->where('status', PaymentStatus::ACTIVATE);

            if ($request->hasData('payment_types')) {
                $paymentInfoMapper->where('type', $request->getDataList('payment_types'));
            }

            $paymentInfo = $paymentInfoMapper->execute();
        }

        $request->setData('client', $client->getId(), true);
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
            ->where('l11n/language', $bill->getLanguage());

        /** @var \Modules\ItemManagement\Models\Item $item */
        $item = $itemMapper->execute();

        // @todo: consider to first create an offer = cart and only when paid turn it into an invoice. This way it's also easy to analyse the conversion rate.

        $billElement = $this->app->moduleManager->get('Billing', 'ApiBill')->createBaseBillElement($client, $item, $bill, $request);
        $bill->addElement($billElement);

        $this->updateModel($request->header->account, $old, $bill, BillMapper::class, 'bill_element', $request->getOrigin());

        // @tood: make this configurable (either from the customer payment info or some item default setting)!!!
        $this->app->moduleManager->get('Payment', 'Api')->setupStripe($request, $response, $bill, $data);
    }
}
