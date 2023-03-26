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

use Modules\Admin\Models\NullAccount;
use Modules\Billing\Models\Attribute\BillAttributeTypeMapper;
use Modules\Billing\Models\Bill;
use Modules\Billing\Models\BillElement;
use Modules\Billing\Models\BillMapper;
use Modules\Billing\Models\BillStatus;
use Modules\Billing\Models\BillType;
use Modules\Billing\Models\BillTypeMapper;
use Modules\Billing\Models\NullBillType;
use Modules\Billing\Models\Tax\TaxCombinationMapper;
use Modules\ClientManagement\Models\ClientMapper;
use Modules\ClientManagement\Models\NullClient;
use Modules\Finance\Models\TaxCodeMapper;
use Modules\ItemManagement\Models\Item;
use Modules\ItemManagement\Models\ItemMapper;
use Modules\ItemManagement\Models\ItemStatus;
use Modules\ItemManagement\Models\NullItem;
use Modules\Payment\Models\PaymentMapper;
use Modules\Payment\Models\PaymentStatus;
use phpOMS\Autoloader;
use phpOMS\Localization\ISO3166TwoEnum;
use phpOMS\Localization\ISO4217CharEnum;
use phpOMS\Localization\ISO4217SymbolEnum;
use phpOMS\Localization\ISO639x1Enum;
use phpOMS\Message\Http\HttpRequest;
use phpOMS\Message\Http\HttpResponse;
use phpOMS\Message\RequestAbstract;
use phpOMS\Message\ResponseAbstract;
use phpOMS\Stdlib\Base\FloatInt;
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
        $schema = $this->buildSchema(new NullItem());

        $response->header->set('Content-Type', MimeType::M_JSON . '; charset=utf-8', true);
        $response->set($request->uri->__toString(), $schema);
    }

    public function buildSchema(Item $item) : array
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
            $schema['image'][] = $image->getPath();
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
        // one-click-shoping-cart = invoice

        /** @var \Modules\ClientManagement\Models\Client */
        $client = ClientMapper::get()
            ->with('mainAddress')
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

        $bill = $this->app->moduleManager->get('Billing', 'ApiBill')->createBaseBill($client, $request);

        $bill->type = BillTypeMapper::get()
            ->with('l11n')
            ->where('name', 'sales_invoice')
            ->where('l11n/language', $bill->getLanguage())
            ->execute();

        $itemMapper = ItemMapper::get()
            ->with('l11n')
            ->with('l11n/type')
            ->where('status', ItemStatus::ACTIVE)
            ->where('l11n/language', $bill->getLanguage());

        /** @var \Modules\ItemManagement\Models\Item */
        $item = $request->hasData('item')
            ? $itemMapper->where('id', $request->getDataInt('item'))->execute()
            : $itemMapper->where('number', $request->getDataString('number'))->execute();

        /** @var \Modules\Billing\Models\Tax\TaxCombination $taxCombination */
        $taxCombination = TaxCombinationMapper::get()
            ->where('itemCode', $item->getAttribute('sales_tax_code')?->value->getValue())
            ->where('clientCode', $client->getAttribute('sales_tax_code')?->value->getValue())
            ->execute();
        
        /** @var \Modules\Finance\Models\TaxCode $taxCode */
        $taxCode = TaxCodeMapper::get()
            ->where('abbr', $taxCombination->taxCode)
            ->execute();

        $billElement = BillElement::fromItem($item, $taxCode);
        $billElement->quantity = 1;

        $bill->addElement($billElement);

        $this->app->moduleManager->get('Billing', 'ApiBill')->createBillDatabaseEntry($bill, $request);

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
        $type = BillAttributeTypeMapper::get()
            ->where('name', 'external_payment_id')
            ->execute();

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

        $api_key         = $_SERVER['OMS_STRIPE_SECRET'] ?? '';
        $endpoint_secret = $_SERVER['OMS_STRIPE_PUBLIC'] ?? '';

        $include = \realpath(__DIR__ . '/../../../Resources/');

        if (empty($api_key) || empty($endpoint_secret) || $include === false) {
            return null;
        }

        Autoloader::addPath($include);

        //$stripe = new \Stripe\StripeClient($api_key);
        \Stripe\Stripe::setApiKey($api_key);

        $session = \Stripe\Checkout\Session::create([
            'amount_subtotal' => $bill->netSales->getInt(),
            'amount_total' => $bill->grossSales->getInt(),
            'mode' => 'payment',
            'currency' => $bill->getCurrency(),
            'success_url' => $success,
            'cancel_url' => $cancel,
            'client_reference_id' => $bill->number,
            //'customer' => '...', @todo: use existing customer external_id
            'customer_email' => $bill->client->account->getEmail(), // @todo: consider to use contacts
        ]);

        return $session;
    }
}
