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

use Modules\Billing\Models\Attribute\BillAttributeTypeMapper;
use Modules\Billing\Models\Bill;
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

        $this->app->moduleManager->get('Billing', 'ApiBill')->createBillDatabaseEntry($bill, $request);

        // @tood: make this configurable (either from the customer payment info or some item default setting)!!!
        $this->setupStripe($request, $response, $bill, $data);
    }

    /**
     * Create stripe checkout response
     *
     * @param RequestAbstract  $request  Request
     * @param ResponseAbstract $response Response
     * @param Bill             $bill     Bill
     * @param mixed            $data     Generic data
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function setupStripe(
        RequestAbstract $request,
        ResponseAbstract $response,
        Bill $bill,
        mixed $data = null
    ) : void
    {
        $session = $this->createStripeSession($bill, $data['success'], $data['cancel']);

        // Assign payment id to bill
        /** \Modules\Billing\Models\Attribute\BillAttributeType $type */
        $type = BillAttributeTypeMapper::get()
            ->where('name', 'external_payment_id')
            ->execute();

        $internalRequest  = new HttpRequest(new HttpUri(''));
        $internalResponse = new HttpResponse();

        $internalRequest->header->account = $request->header->account;
        $internalRequest->setData('type', $type->getId());
        $internalRequest->setData('custom', (string) $session->id);
        $internalRequest->setData('bill', $bill->getId());
        $this->app->moduleManager->get('Billing', 'ApiAttribute')->apiBillAttributeCreate($internalRequest, $internalResponse, $data);

        // Redirect to stripe checkout page
        $response->header->status = RequestStatusCode::R_303;
        $response->header->set('Content-Type', MimeType::M_JSON, true);
        $response->header->set('Location', $session->url, true);
    }

    /**
     * Create stripe session
     *
     * @param Bill   $bill    Bill
     * @param string $success Success url
     * @param string $cancel  Cancel url
     *
     * @return \Stripe\Checkout\Session|null
     *
     * @since 1.0.0
     */
    private function createStripeSession(
        Bill $bill,
        string $success,
        string $cancel
    ) : ?\Stripe\Checkout\Session
    {
        // $this->app->appSettings->getEncrypted()

        // $stripeSecretKeyTemp = $this->app->appSettings->get();
        // $stripeSecretKey = $this->app->appSettings->decrypt($stripeSecretKeyTemp);

        // \Stripe\Stripe::setApiKey($stripeSecretKey);

        $api_key         = $_SERVER['OMS_STRIPE_SECRET'] ?? '';
        $endpoint_secret = $_SERVER['OMS_STRIPE_PUBLIC'] ?? '';

        $include = \realpath(__DIR__ . '/../../../Resources/Stripe');

        if (empty($api_key) || empty($endpoint_secret) || $include === false) {
            return null;
        }

        Autoloader::addPath($include);

        $stripeData = [
            'line_items' => [],
            'mode' => 'payment',
            'currency' => $bill->getCurrency(),
            'success_url' => $success,
            'cancel_url' => $cancel,
            'client_reference_id' => $bill->number,
           // 'customer' => 'stripe_customer_id...',
            'customer_email' => $bill->client->account->getEmail(),
        ];

        $elements = $bill->getElements();
        foreach ($elements as $element) {
            $stripeData['line_items'][] = [
                'quantity' => 1,
                'price_data' => [
                    'tax_behavior' => 'inclusive',
                    'currency' => $bill->getCurrency(),
                    'unit_amount' => (int) ($element->totalSalesPriceGross->getInt() / 100),
                    //'amount_subtotal' => (int) ($bill->netSales->getInt() / 100),
                    //'amount_total' => (int) ($bill->grossSales->getInt() / 100),
                    'product_data' => [
                        'name' => $element->itemName,
                        'metadata' => [
                            'pro_id' => $element->itemNumber,
                        ],
                    ],
                ]
            ];
        }

        //$stripe = new \Stripe\StripeClient($api_key);
        \Stripe\Stripe::setApiKey($api_key);

        // @todo: instead of using account email, use client billing email if defined and only use account email as fallback
        $session = \Stripe\Checkout\Session::create($stripeData);

        return $session;
    }
}
