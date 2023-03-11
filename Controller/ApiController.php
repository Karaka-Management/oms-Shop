<?php
/**
 * Karaka
 *
 * PHP Version 8.1
 *
 * @package   Modules\Shop
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

namespace Modules\Shop\Controller;

use Modules\Billing\Models\Attribute\BillAttributeTypeMapper;
use Modules\Billing\Models\Bill;
use Modules\ClientManagement\Models\ClientMapper;
use Modules\ClientManagement\Models\NullClient;
use Modules\ItemManagement\Models\ItemMapper;
use Modules\Payment\Models\PaymentMapper;
use Modules\Payment\Models\PaymentStatus;
use phpOMS\Autoloader;
use phpOMS\Message\Http\HttpRequest;
use phpOMS\Message\Http\HttpResponse;
use phpOMS\Message\RequestAbstract;
use phpOMS\Message\ResponseAbstract;
use phpOMS\System\MimeType;
use phpOMS\Uri\HttpUri;

/**
 * Api controller
 *
 * @package Modules\Shop
 * @license OMS License 1.0
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
        // @todo: implement https://schema.org/Product
        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => '...',
            'image' => [

            ],
            'description' => '...',
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => '...',
                'price' => '...',
                'availability' => '...',
            ],
            'isVariantOf' => '...',
        ];

        $response->header->set('Content-Type', MimeType::M_JSON . '; charset=utf-8', true);
        $response->set($request->uri->__toString(), $schema);
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
        $item = $request->hasData('item')
            ? ItemMapper::get()->where('id', (int) $request->getData('item'))->execute()
            : ItemMapper::get()->where('number', (string) $request->getData('number'))->execute();

        // get item
        // get client
            // get client data
            // get payment data
        // create one-click-shoping-cart = invoice
        // create payment based on client data

        $client = ClientMapper::get()
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

        $bill = new Bill();

        // add item to bill
        // set quantity
        // set price
        // attach payment to bill
        // set external payment id to bill
        // execute bill payment

        $this->setupStripe($request, $response, $bill, $data);
    }

    private function setupStripe(
        RequestAbstract $request,
        ResponseAbstract $response,
        Bill $bill,
        mixed $data = null
    ) : void {
        $session = $this->createStripeSession($bill, $data['success'], $data['cancel']);

        // Assign payment id to bill
        /** \Modules\Billing\Models\Attribute\BillAttributeType $type */
        $type = BillAttributeTypeMapper::get()->where('name', 'external_payment_id')->execute();

        $internalRequest = new HttpRequest(new HttpUri(''));
        $internalResponse = new HttpResponse();

        $internalRequest->header->account = $request->header->account;
        $internalRequest->setData('type', $type->getId());
        $internalRequest->setData('custom', (string) $session->id);
        $internalRequest->setData('bill', $bill->getId());
        $this->app->moduleManager->get('Billing', 'ApiAttribute')->apiBillAttributeCreate($internalRequest, $internalResponse, $data);

        // Redirect to stripe checkout page
        $response->header->set('Content-Type', MimeType::M_JSON, true);
        $response->header->set('', 'HTTP/1.1 303 See Other', true);
        $response->header->set('Location', $session->url, true);
    }

    private function createStripeSession(
        Bill $bill,
        string $success,
        string $cancel
    ) {
        // $this->app->appSettings->getEncrypted()

        // $stripeSecretKeyTemp = $this->app->appSettings->get();
        // $stripeSecretKey = $this->app->appSettings->decrypt($stripeSecretKeyTemp);

        // \Stripe\Stripe::setApiKey($stripeSecretKey);

        $api_key = $_SERVER['OMS_STRIPE_SECRET'] ?? '';
        $endpoint_secret = $_SERVER['OMS_STRIPE_PUBLIC'] ?? '';

        $include = \realpath(__DIR__ . '/../../../Resources/');

        if (empty($api_key) || empty($endpoint_secret) || $include === false) {
            return null;
        }

        Autoloader::addPath($include);

        //$stripe = new \Stripe\StripeClient($api_key);
        \Stripe\Stripe::setApiKey($api_key);

        $session = \Stripe\Checkout\Session::create([
            'line_items' => [[
                'amount_subtotal' => '...',
                'amount_tax' => '...',
                'amount_total' => '...',
                'quantity' => 1,
                'price_data' => [
                    'name' => '',
                    'metadata' => [
                        'pro_id' => 0,
                    ]
                ],
                'unit_amount' => 0,
                'currency' => 0,
            ]],
            'amount_subtotal' => '...',
            'amount_total' => '...',
            'mode' => 'payment',
            'currency' => '...',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'client_reference_id' => '...',
            'customer' => 'stripe_customer_id...',
            'customer_email' => 'customer_email...',
        ]);

        return $session;
    }
}
