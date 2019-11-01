<?php
namespace Ceevo\Helper;

use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PayCore
 * @package Ceevo\Helper
 */
class PayCore
{
  use Loggable;
  /**
   * ContactService constructor.
   */
  public function __construct()
  {
  }

  public $response   = '';
  public $error      = '';

  public $live_url       = 'https://api.ceevo.com';
  public $test_url       = 'https://api.dev.ceevo.com';
  public $live_token_url   = 'https://auth.ceevo.com/auth/realms/ceevo-realm/protocol/openid-connect/token';
  public $test_token_url   = 'https://auth.dev.transact24.com/auth/realms/ceevo-realm/protocol/openid-connect/token';
  public $live_sdk_url = 'https://sdk.ceevo.com';
  public $test_sdk_url = 'https://sdk-beta.ceevo.com';

  public $availablePayments = array('CV');
  public $pageURL = '';
  public $actualPaymethod = 'CV';

  public $lastError = '';
  public $lastErrorCode = '';

  public $access_token = '';

  function createCustomer($param){
    $url = $param['API.URL'];
    $userData = $param['userData'];

    $data = array("billing_address" => array("city" => $userData['city'], "country" => $userData['country'],"state" => $userData['state'],
                  "street" => $userData['street'],"zip_or_postal"=> $userData['zip']),"email" => $userData['email'],"first_name" => $userData['firstname'],
                  "last_name" => $userData['lastname'],"mobile" => $userData['phone'],"phone" => $userData['phone']);  
    $data_string = json_encode($data);
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $data);
    $customer_id = $this->callAPI('POST', $url . '/payment/customer', $param, $data_string);
   
    return $customer_id;
  }

  function genCardTokenWidget($twig, $param) {
    $content = '<button type="button" data-dismiss="modal" aria-label="Close" class="close" onclick="location.href=\'/checkout\'"><span aria-hidden="true">×</span></button>
    <center><iframe src="payment/ceevo/token_frame" frameborder="0" width="100%" height="800px"></iframe></center>';
    return $content;
    // return $twig->render('Ceevo::content.tokenise', ['apiKey' => $param['API.KEY'], 'mode' => $param['ENV.MODE'], 'price' => $param['PRICE'], 
    //                       'currency' => $param['CURRENCY'], 'apiUrl' => $apiUrl, 'cardTokenUrl' => $param['cardTokenUrl']]);
  }

  function registerAccountToken($conf, $customer_registered_id){
      $url = $conf['API.URL'];
      $token_array = array("account_token" => $conf['tokenise']['card_token'],"is_default" => true,"verify" => true);
      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $token_array);
      $token_string = json_encode($token_array);
      $get_data = $this->callAPI('POST', $url . '/payment/customer/'.$customer_registered_id, $conf, $token_string);
      $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $get_data);
      
      $response = json_decode($get_data, true);
  }

  function getToken($conf){
    $api = $conf['TOKEN.URL'];
    $param['grant_type'] = "client_credentials"; 
    $param['client_id'] = $conf['CLIENT.ID']; 
    $param['client_secret'] = $conf['CLIENT.SECRET']; 
    $mode = $conf['ENV.MODE'];

    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL,$api); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); 
    //curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
    $res = curl_exec($ch); 
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $res);
    $jres = json_decode($res, true);

    $this->access_token  = $jres['access_token'];
    return $this->access_token;
  } 

  function chargeApi($param, $cusId){
    $url = $param['API.URL'];

    $userData = $param['userData'];   
    $orderId =  $param['REQUEST']['ORDER.ID'];
    $apiKey =  $param['API.KEY'];
    $mode = $param['ENV.MODE'];

    $items_array = array();
    foreach($param['basketItems'] as $item){      
      $item_json = array("item" => $item['name'],"itemValue" =>(string) ($item['price'] * 100));
      array_push($items_array, json_encode($item_json));
    }
    $itemString = implode(',',$items_array);
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $itemString);
    
    $access_token = $this->access_token;    
    $authorization = "Authorization: Bearer $access_token";   
    $charge_api = $url . "/payment/charge";        
    
    $successURL = $param['REQUEST']['CRITERION.SUCCESSURL'];
    $failURL = $param['REQUEST']['CRITERION.FAILURL'];
    $cparam = '{"amount": '.( $param['REQUEST']['AMOUNT'] * 100 ).',
            "3dsecure": true,
            "capture": true,
            "mode" : "'.$mode.'",
            "method_code":  "'.$param['tokenise']['method_code'].'",
            "currency": "'.$param['REQUEST']['CURRENCY'].'",
            "customer_id": "'.$cusId.'", 
            "account_token": "'.$param['tokenise']['card_token'].'",
            "session_id": "'.$param['tokenise']['session_id'].'",
            "redirect_urls": {
                "failure_url": "'.$failURL.'",
                "success_url": "'.$successURL.'"
            },
            "reference_id": "'.$orderId.'",
            "shipping_address": {
                "city": "'.$userData['city'].'",
                "country": "'.$userData['country'].'",
                "state": "'.$userData['state'].'",
                "street": "'.$userData['street'].'",
                "zip_or_postal": "'.$userData['zip'].'"
            },
            "user_email": "'.$userData['email'].'"}';
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $cparam);
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL,$charge_api); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); 
    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $cparam);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($cparam),
            $authorization
        )
    );
    $cres = curl_exec($ch);
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $cres);

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($cres, 0, $header_size);
    $body = substr($cres, $header_size); 
    $jres = json_decode($body, true);
    $payment_id = $jres['payment_id'];
    // curl_close($ch);
    // $transactionHeaders = $this->http_parse_headers($headers);
    // $transactionId = '';
    // $ThreedURL = ''; 

    // if( $transactionHeaders[0]  == 'HTTP/1.1 201 Created') {        
    //   $transactionId  =  $transactionHeaders['X-Gravitee-Transaction-Id'];
    // }else if($transactionHeaders[0]  == 'HTTP/1.1 302 Found'){
    //   $ThreedURL   = $transactionHeaders['Location'];
    //   $transactionId  =  $transactionHeaders['X-Gravitee-Transaction-Id'];
    //   $_SESSION['3durl'] = $ThreedURL;      
    // }

    return $payment_id;
  }

  function callAPI($method, $url, $conf, $data){
    $apiKey =  $conf['API.KEY'];
    $access_token = $this->access_token;
    $authorization = "Authorization: Bearer $access_token";

    $curl = curl_init();
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $data);
    switch ($method){
        case "POST":
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data)
              curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
          break;
        case "PUT":
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
          if ($data)
              curl_setopt($curl, CURLOPT_POSTFIELDS, $data);                                 
          break;
        default:
          if ($data)
              $url = sprintf("%s?%s", $url, $data);
    }
    
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data),
        $authorization
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

    // EXECUTE:
    $response = curl_exec($curl);
    
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $response);
    // Retudn headers seperatly from the Response Body
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    curl_close($curl);
    header("Content-Type:text/plain; charset=UTF-8");
    $transactionHeaders = $this->http_parse_headers($headers);
    $cusId = '';
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $transactionHeaders);
    if( $transactionHeaders[0]  == 'HTTP/1.1 201 Created') {
        
      $customerIdurl   = $transactionHeaders['Location'];
      $remove_http = str_replace('http://', '', $customerIdurl);
        $split_url = explode('?', $remove_http);
        $get_page_name = explode('/', $split_url[0]);
        $cusId = $get_page_name[4];
    }
    return $cusId;
  }

  function http_parse_headers($raw_headers)
  {
      $headers = array();
      $key = ''; // [+]
      foreach(explode("\n", $raw_headers) as $i => $h)
      {
          $h = explode(':', $h, 2);
          if (isset($h[1]))
          {
              if (!isset($headers[$h[0]]))
                  $headers[$h[0]] = trim($h[1]);
              elseif (is_array($headers[$h[0]]))
              {
                  // $tmp = array_merge($headers[$h[0]], array(trim($h[1]))); // [-]
                  // $headers[$h[0]] = $tmp; // [-]
                  $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1]))); // [+]
              }
              else
              {
                  // $tmp = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [-]
                  // $headers[$h[0]] = $tmp; // [-]
                  $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [+]
              }
              $key = $h[0]; // [+]
          }
          else // [+]
          { // [+]
              if (substr($h[0], 0, 1) == "\t") // [+]
                  $headers[$key] .= "\r\n\t".trim($h[0]); // [+]
              elseif (!$key) // [+]
                  $headers[0] = trim($h[0]);trim($h[0]); // [+]
          } // [+]
      }
      return $headers;
  }

} // end of class
?>
