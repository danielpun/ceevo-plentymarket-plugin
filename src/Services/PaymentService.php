<?php //strict

namespace Ceevo\Services;

use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

use Ceevo\Helper\PaymentHelper;
use Ceevo\Helper\PayCore;
use Ceevo\Services\SessionStorageService;

/**
 * @package Ceevo\Services
 */
class PaymentService
{
  use Loggable;
    /**
     * @var string
     */
    private $returnType = 'htmlContent';

    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var AddressRepositoryContract
     */
    private $addressRepo;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
    
    /**
     * @var PayCore
     */
    private $payCore;
    
    /**
     * @var OrderRepositoryContract
     */
    private $orderRepo;

    /**
     * PaymentService constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentRepositoryContract $paymentRepository
     * @param ConfigRepository $config
     * @param PaymentHelper $paymentHelper
     * @param AddressRepositoryContract $addressRepo
     * @param SessionStorageService $sessionStorage
     * @param PayCore $payCore
     * @param OrderRepositoryContract $orderRepo
     */
    public function __construct(  PaymentMethodRepositoryContract $paymentMethodRepository,
                                  PaymentRepositoryContract $paymentRepository,
                                  ConfigRepository $config,
                                  PaymentHelper $paymentHelper,
                                  AddressRepositoryContract $addressRepo,
                                  SessionStorageService $sessionStorage,
                                  PayCore $payCore,
                                  OrderRepositoryContract $orderRepo)
    {
        $this->paymentMethodRepository    = $paymentMethodRepository;
        $this->paymentRepository          = $paymentRepository;
        $this->paymentHelper              = $paymentHelper;
        $this->addressRepo                = $addressRepo;
        $this->config                     = $config;
        $this->sessionStorage             = $sessionStorage;
        $this->payCore                    = $payCore;
        $this->orderRepo                  = $orderRepo;
    }
    
    /**
     * Get the type of payment from the content of the PayPal container
     *
     * @return string
     */
    public function getReturnType()
    {
        return $this->returnType;
    }

    /**
     * Execute the PayPal payment
     *
     * @return array
     */
    public function executePayment($orderId, $selectedPaymethod, $selectedMopID)
    {
        // this is fired when place-order is called
        
        $order = $this->orderRepo->findOrderById($orderId);
        $this
            ->getLogger('PaymentService::executePayment')
            ->info('Ceevo::Logger.infoCaption', [
              'order' => $order,
            ]);

        $lastReq = $this->sessionStorage->getSessionValue('lastReq');
        $lastTrxID = $this->sessionStorage->getSessionValue('lastTrxID');
        $lastUniqueID = $this->sessionStorage->getSessionValue('lastUniqueID');
        $this
            ->getLogger('PaymentService::executePayment')
            ->info('Ceevo::Logger.infoCaption', [
              'req' => $lastReq,
              'trxid' => $lastTrxID,
              'uniqueid' => $lastUniqueID,
            ]);
        
        //try {
          // create payment
          $payment = $this->paymentHelper->createPlentyPayment($lastReq, $lastTrxID, $lastUniqueID);

          $this
            ->getLogger('PaymentService::executePayment')
            ->info('Ceevo::Logger.infoCaption', [
              'payment' => $payment,
              'orderId' => $orderId,
            ]);
          
          // assign payment to order
          $this->paymentHelper->assignPlentyPaymentToPlentyOrder($payment, $orderId);
        /*
        } catch (Exception $e) {
          $this
           ->getLogger('PaymentService::executePayment')
           ->info('Ceevo::Logger.infoCaption', [
            $e->getMessage()
          ]);
        }
        */
        
        // Execute the payment
        $executeResponse = array('success' => 1);

        // Check for errors
        /*
        if(is_array($executeResponse) && $executeResponse['error'])
        {
            $this->returnType = 'errorCode';
            return $executeResponse['error'].': '.$executeResponse['error_msg'];
        }
        */

        return $executeResponse;
    }

    /**
     * @return array
     */
    private function getApiContextParams()
    {
        $apiContextParams = [];
        $apiContextParams['CLIENT.ID'] = $this->config->get('Ceevo.clientId');
        $apiContextParams['CLIENT.SECRET'] = $this->config->get('Ceevo.clientSecret');
        $apiContextParams['API.KEY'] = $this->config->get('Ceevo.apiKey');
        $apiContextParams['ENV.MODE'] = $this->config->get('Ceevo.environment');
        return $apiContextParams;
    }
    
    /**
     * Fill and return the api parameters
     *
     * @param Basket $basket
     * @return array
     */
    private function getApiParams(Basket $basket = null, $selectedPaymethod, $mopID)
    {
        $requestParams = $this->getApiContextParams();

        /** @var Basket $basket */
        $requestParams['basket'] = $basket;

        /** @var \Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract $itemContract */
        $itemContract = pluginApp(\Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract::class);

        /** declarce the variable as array */
        $requestParams['basketItems'] = [];

        /** @var BasketItem $basketItem */
        foreach($basket->basketItems as $basketItem)
        {
            /** @var \Plenty\Modules\Item\Item\Models\Item $item */
            $item = $itemContract->show($basketItem->itemId);
            $basketItem['itemId'] = $basketItem->itemId;
            $basketItem['quantity'] = $basketItem->quantity;
            $basketItem['price'] = $basketItem->price;

            // $basketItem = $basketItem->getAttributes();

            /** @var \Plenty\Modules\Item\Item\Models\ItemText $itemText */
            $itemText = $item->texts;

            $basketItem['name'] = $itemText->first()->name1;
            

            $requestParams['basketItems'][] = $basketItem;
        }

        $shippingAddressId = $basket->customerInvoiceAddressId;
        if (empty($shippingAddressId) && !empty($basket->customerShippingAddressId)) 
          $shippingAddressId = $basket->customerShippingAddressId;
        
        if(!is_null($shippingAddressId))
        {
          $shippingAddress = $this->addressRepo->findAddressById($shippingAddressId);

          /** declarce the variable as array */
          $requestParams['shippingAddress'] = [];
          $requestParams['shippingAddress']['town']           = $shippingAddress->town;
          $requestParams['shippingAddress']['postalCode']     = $shippingAddress->postalCode;
          $requestParams['shippingAddress']['firstname']      = $shippingAddress->firstName;
          $requestParams['shippingAddress']['lastname']       = $shippingAddress->lastName;
          $requestParams['shippingAddress']['street']         = $shippingAddress->street;
          $requestParams['shippingAddress']['houseNumber']    = $shippingAddress->houseNumber;
          $requestParams['shippingAddress']['companyName']    = $shippingAddress->companyName;
          $requestParams['shippingAddress']['stateId']        = $shippingAddress->stateId;
          $requestParams['shippingAddress']['phone']        = ($shippingAddress->phone != "") ? $shippingAddress->phone : "123456789";
          $requestParams['shippingAddress']['email']        = $shippingAddress->email;
        }

        /** @var \Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract $countryRepo */
        $countryRepo = pluginApp(\Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract::class);

        // Fill the country 
        $country = [];
        $country['isoCode2'] = $countryRepo->findIsoCode($basket->shippingCountryId, 'iso_code_2');
        $requestParams['country'] = $country;
        $requestParams['countryDetails'] = $countryRepo->getCountryById($basket->shippingCountryId);
        
        //$payCore = pluginApp(Ceevo\Helper\PayCore::class);
        $payCore = $this->payCore;
        $orderId = $basket->id;
        $amount = $basket->basketAmount;
        //if ($requestParams['ENV.MODE'] != 'LIVE' && $amount <= 0) $amount = 10; // Testing
        $currency = $basket->currency;
        
        /** @var \Plenty\Modules\Helper\Services\WebstoreHelper $webstoreHelper */
        $webstoreHelper = pluginApp(\Plenty\Modules\Helper\Services\WebstoreHelper::class);

        /** @var \Plenty\Modules\System\Models\WebstoreConfiguration $webstoreConfig */
        $webstoreConfig = $webstoreHelper->getCurrentWebstoreConfiguration();

        $domain = $_SERVER['HTTP_HOST'];
        if(!is_null($webstoreConfig)) {
          $domain = $webstoreConfig->domainSsl;
        }
        
        $userData = array(
          "userid"      => $basket->customerId,
          "firstname"   => $requestParams['shippingAddress']['firstname'],
          "lastname"    => $requestParams['shippingAddress']['lastname'],
          "company"     => $requestParams['shippingAddress']['companyName'],
          "street"      => $requestParams['shippingAddress']['street'].' '.$requestParams['shippingAddress']['houseNumber'],
          "zip"         => $requestParams['shippingAddress']['postalCode'],
          "city"        => $requestParams['shippingAddress']['town'],
          "state"       => $requestParams['shippingAddress']['town'],
          "country"     => $country['isoCode2'],
          "phone"       => $requestParams['shippingAddress']['phone'],
          "mobile"      => $requestParams['shippingAddress']['phone'],
          "email"       => $requestParams['shippingAddress']['email'],
          "ip"          => $_SERVER['REMOTE_ADDR'],
        );

        $requestParams['userData'] = $userData;
        $requestParams['tokenUrl'] = $domain.'/payment/ceevo/card_token';
        $requestParams['REQUEST']['ORDER.ID'] = $orderId;
        $requestParams['REQUEST']['AMOUNT'] = $amount;
        $requestParams['REQUEST']['CURRENCY'] = $currency;
        // $requestParams['REQUEST'] = $payCore->prepareData($orderId, $amount, $currency, $conf, $userData);
        $requestParams['REQUEST']['CRITERION.MOPID'] = $mopID;
        $requestParams['REQUEST']['CRITERION.PM'] = $selectedPaymethod;
        $requestParams['REQUEST']['CRITERION.FAILURL'] = $domain.'/payment/ceevo/checkout_failure';
        $requestParams['REQUEST']['CRITERION.SUCCESSURL'] = $domain.'/payment/ceevo/checkout_success';        
        $requestParams['REQUEST']['CRITERION.PLACEURL'] = $domain.'/place-order';
        // $requestParams['REQUEST']['CRITERION.RESPONSEURL'] = $conf['RESPONSE_URL'];
        $i=1;
        foreach($_COOKIE AS $k => $v){
          $requestParams['REQUEST']['CRITERION.COOKIE.'.$i.'.NAME'] = $k;
          $requestParams['REQUEST']['CRITERION.COOKIE.'.$i.'.VALUE'] = $v;
          $i++;
        }
        
        $this->sessionStorage->setSessionValue('lastReq', $requestParams);

        return $requestParams;
    }
    
    public function getPaymentContent($twig, $basket, $selectedPaymethod, $mopID)
    {
      // clear if last payment is still there
      $lastReq = $this->sessionStorage->setSessionValue('lastReq', null);
      $lastTrxID = $this->sessionStorage->setSessionValue('lastTrxID', null);
      $lastUniqueID = $this->sessionStorage->setSessionValue('lastUniqueID', null);
      
      $selectedPaymethod = strtolower($selectedPaymethod);
      $requestParams = $this->getApiParams($basket, $selectedPaymethod, $mopID);

      $payCore = $this->payCore;
      $access_token = $payCore->getToken($requestParams);
      $requestParams['tokenise'] = array( "3dsecure"=>  "true" ,
      "price"=>  "1234", "currency"=> "EUR","referenceId"=>"dantest123",
      "card_token"=> "017dd335-b311-4bf9-8d0b-5eae5c1ee82c","session_id"=>"f8314df7-8152-4ca3-b4d1-141ba6dda16f","method_code"=>"CARDS" );
      $customer_id = $payCore->createCustomer($requestParams);
      $payCore->registerAccountToken($requestParams, $customer_id );
      $transaction_id = $payCore->chargeApi($requestParams, $customer_id);
      // $content = "access_token = $access_token, customer_id = $customer_id, transaction_id = $transaction_id";
      $content = $payCore->genCardTokenWidget($twig, $requestParams);


      // $rawRes = $payCore->doRequest($requestParams['REQUEST']);
      // $res = $payCore->parseResult($rawRes);
      $this
        ->getLogger('PaymentService::getPaymentContent')
        //->setReferenceType('this')
        //->setReferenceValue($this)
        ->info('Ceevo::Logger.infoCaption', [
          'requestParamsALL' => $requestParams, 
          'parse_result' => $content, 
          'this' => $this,
        ]);
      
      // $iframeURL = 'about:blank';
      // if ($res['result'] == 'ACK'){
      //   $iframeURL = $res['url'];
       
      //   $content = '<center><iframe src="'.$iframeURL.'" frameborder="0" width="80%" height="500"></iframe></center>';
      // } else {
      //   $content = '<h3 style="color: red">ERROR: '.$res['all']['PROCESSING.RETURN'].'</h3>';
      // }
      // // modal way
      // //$content.= '<span class="button" , data-dismiss="modal" aria-label="Close">cancel</span>';
      // // redirect way
      // $content.= '<a href="/checkout">cancel</a>';
      

      
      return $content;
    }

}

