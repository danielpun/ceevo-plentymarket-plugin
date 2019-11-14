<?php

namespace Ceevo\Controllers;

use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Controller;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
//use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
//use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Log\Loggable;

use Ceevo\Helper\PaymentHelper;
use Ceevo\Services\SessionStorageService;
use Ceevo\Helper\PayCore;


class CeevoResponseController extends Controller
{
    use Loggable;

    /**
     * @var Request
     */
    private $request;
    
    /**
     * @var Response
     */
    private $response;
    
    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;
    
    /**
     * @var OrderRepositoryContract
     */
    private $orderRepo;
    
    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
    
    /**
     * @var ConfigRepository
     */
    private $config;
    
    private $payCore;
     
    private $twig;
  

    /**
     * PaymentNotificationController constructor.
     * @param Request $request
     * @param Response $response
     * @param PaymentRepositoryContract $paymentRepository
     * @param PaymentHelper $paymentHelper
     * @param SessionStorageService $sessionStorage
     * @param OrderRepositoryContract $orderRepo
     * @param \Ceevo\Controllers\ConfigRepository $config
     */
    public function __construct(Request $request,
                                Response $response,
                                PaymentRepositoryContract $paymentRepository,
                                PaymentHelper $paymentHelper,
                                SessionStorageService $sessionStorage,
                                OrderRepositoryContract $orderRepo,
                                PayCore $payCore, 
                                Twig $twig,                              
                                ConfigRepository $config)
    {
        $this->request            = $request;
        $this->response           = $response;
        $this->paymentRepository  = $paymentRepository;
        $this->paymentHelper      = $paymentHelper;
        $this->orderRepo          = $orderRepo;
        $this->sessionStorage     = $sessionStorage;
        $this->config             = $config;
        $this->payCore            = $payCore;
        $this->twig               = $twig;       
    }

    public function checkoutFailure()
    {
      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $this->request->getContent());
      return $this->checkoutResponse();
    }

    public function checkoutSuccess()
    {
      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $this->request->getContent());
      return $this->checkoutResponse();
    }
    
    public function checkoutResponse()
    {
      $body = $this->request->getContent();
      $data = array();
      $tmp = explode('&', $body);
      foreach($tmp AS $v){
        $t = explode('=', $v);
        $data[$t[0]] = $t[1];
      }
      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', ['data_raw' => $data]);
      $payload = base64_decode(urldecode($data['payload']));
      $HMACSHA256 = urldecode($data['HMACSHA256']);
      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', ['payload' => $payload, 'HMACSHA256' => $HMACSHA256]);
      
      $returnData =  json_decode($payload,true);
      $transactionId = $returnData['payment_id'];      
      $orderId = $returnData['reference_id'];
      $status = $returnData['status'];
      $oneTimeKey = $this->sessionStorage->getSessionValue('oneTimeKey');
      $requestParams = $this->sessionStorage->getSessionValue('lastReq');
      $requestParams['STATUS']= $status;
      $this->sessionStorage->setSessionValue('lastReq', $requestParams);

      $s = hash_hmac('sha256', $payload, $oneTimeKey, true);
      $checksum = base64_encode($s);

      
      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', ['status' => $status]);
      if($HMACSHA256 == $checksum) {
        $redirection = $this->getRedirection($status);
      } else {        
        $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', ['checksum' => $checksum]);
      }

      return $this->redirectPage($redirection);
    }

    public function getRedirection($status) {
      switch($status) {
        case 'SUCCEEDED':
          $redirection = 'place-order';
          break;
        case 'PENDING':
          $redirection = 'checkout';
          break;
        case 'CANCEL':          
          $redirection = 'basket';
          break;
        case 'FAILED':
          $redirection = 'checkout';
          break;
        case 'ERROR':
          $redirection = 'checkout';
          break;
        default:
          $redirection = 'checkout';
      }
      return $redirection;
    }

    public function redirectPage($redirection) {
      $redirection = '../../' . $redirection; 
      return $this->twig->render('Ceevo::content.redirect', ['redirection' => $redirection]);
    }

    public function getTokenFrame() {
      $requestParams = $this->sessionStorage->getSessionValue('lastReq');
      return $this->twig->render('Ceevo::content.tokenise', ['apiKey' => $requestParams['API.KEY'], 'mode' => $requestParams['ENV.MODE'], 'price' => $requestParams['REQUEST']['AMOUNT'], 
                            'currency' => $requestParams['REQUEST']['CURRENCY'], 'sdkUrl' => $requestParams['SDK.URL'], 'cardTokenUrl' => $requestParams['cardTokenUrl']]);
    }

    public function handleCardToken()
    {
      $body = $this->request->getContent();
      $data = array();
      $tmp = explode('&', $body);
      foreach($tmp AS $v){
        $t = explode('=', $v);
        $data[$t[0]] = $t[1];
      }

      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', ['handleCardToken' => $data]);
      $requestParams = $this->sessionStorage->getSessionValue('lastReq');
      $payCore = $this->payCore;
      $access_token = $payCore->getToken($requestParams);
      $requestParams['tokenise'] = $data;
      $customer_id = $payCore->createCustomer($requestParams);
      $requestParams['customer_id'] = $customer_id;
      $payCore->registerAccountToken($requestParams, $customer_id );
      $this->sessionStorage->setSessionValue('lastReq', $requestParams);

      $res = $payCore->chargeApi($requestParams);
      $this->sessionStorage->setSessionValue('lastRes', $res);
      $this->sessionStorage->setSessionValue('lastTrxID', $res['payment_id']);
      $this->sessionStorage->setSessionValue('lastUniqueID', $res['payment_id']);
      

      if($res['3d_url'] != "") {
        $this->sessionStorage->setSessionValue('oneTimeKey', $res['message']);
        return $this->response->redirectTo($res['3d_url']);
      } else {
        $redirection = $this->getRedirection($res['status']);
        return $this->redirectPage($redirection);
      }        
    }
}
