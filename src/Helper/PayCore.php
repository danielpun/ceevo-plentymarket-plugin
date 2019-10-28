<?php
namespace Cvpa\Helper;

use Plenty\Plugin\ConfigRepository;

/**
 * Class PayCore
 * @package Cvpa\Helper
 */
class PayCore
{

  /**
   * ContactService constructor.
   */
  public function __construct()
  {
  }

  var $response   = '';
  var $error      = '';

  var $live_url       = 'https://api.ceevo.com';
  var $test_url       = 'https://api.dev.ceevo.com';
  var $live_token_url   = 'https://auth.ceevo.com/auth/realms/ceevo-realm/protocol/openid-connect/token';
  var $test_token_url   = 'https://auth.dev.transact24.com/auth/realms/ceevo-realm/protocol/openid-connect/token';
  var $live_sdk_url = 'https://sdk.ceevo.com';
  var $test_sdk_url = 'https://sdk-beta.ceevo.com';

  var $availablePayments = array('CV');
  var $pageURL = '';
  var $actualPaymethod = 'CV';

  var $lastError = '';
  var $lastErrorCode = '';

  var $access_token = '';

  function prepareData($orderId, $amount, $currency, $conf, $userData, $capture = false, $uniqueId = NULL)
  {
    $mode = $conf['PAY_MODE'];
    $lang = $conf['LANGUAGE'];
    $payCode = strtoupper($conf['PAY_CODE']);
    $amount = sprintf('%1.2f', $amount);
    $currency = strtoupper($currency);

    $parameters['SECURITY.SENDER']              = $conf['SECURITY_SENDER'];
    $parameters['USER.LOGIN']                   = $conf['USER_LOGIN'];
    $parameters['USER.PWD']                     = $conf['USER_PWD'];
    $parameters['TRANSACTION.CHANNEL']          = $conf['TRANSACTION_CHANNEL'];
    $parameters['ENV.MODE']             = $conf['TRANSACTION_MODE'];
    $parameters['REQUEST.VERSION']              = "1.0";
    $parameters['IDENTIFICATION.TRANSACTIONID'] = $orderId;

    if (!empty($userData['userid']))
      $parameters['IDENTIFICATION.SHOPPERID']   = $userData['userid'];

    if ($payCode == 'RM'){
      $parameters['FRONTEND.ENABLED']           = "false";
    } else if ($capture){
      $parameters['FRONTEND.ENABLED']           = "false";
      if (!empty($uniqueId)){
        $parameters['ACCOUNT.REGISTRATION']     = $uniqueId;
      }
    } else {
      $parameters['FRONTEND.ENABLED']           = "true";
    }

    if (!empty($conf['FRONTEND_HEIGHT'])){
      $parameters['FRONTEND.HEIGHT']            = $conf['FRONTEND_HEIGHT'];
    } else {
      $parameters['FRONTEND.HEIGHT']            = "250";
    }

		$parameters['FRONTEND.REDIRECT_TIME']       = "0";
    $parameters['FRONTEND.POPUP']               = "false";
    $parameters['FRONTEND.MODE']                = "DEFAULT";
    $parameters['FRONTEND.LANGUAGE']            = $lang;
    $parameters['FRONTEND.LANGUAGE_SELECTOR']   = "true";
    $parameters['FRONTEND.ONEPAGE']             = "true";
    #$parameters['FRONTEND.RETURN_ACCOUNT']      = "true";
    $parameters['FRONTEND.NEXTTARGET']          = "top.location.href";

    if (!empty($conf['STYLE_URL'])){
      $parameters['FRONTEND.CSS_PATH']          = $conf['STYLE_URL'];
    }
    if (!empty($conf['IMG_PAY_URL'])){
      $parameters['FRONTEND.BUTTON.1.NAME']     = 'PAY';
      $parameters['FRONTEND.BUTTON.1.TYPE']     = 'IMAGE';
      $parameters['FRONTEND.BUTTON.1.LINK']     = $conf['IMG_PAY_URL'];
    }
    if (!empty($conf['IMG_BACK_URL'])){
      $parameters['FRONTEND.BUTTON.2.NAME']     = 'CANCEL';
      $parameters['FRONTEND.BUTTON.2.TYPE']     = 'IMAGE';
      $parameters['FRONTEND.BUTTON.2.LINK']     = $conf['IMG_BACK_URL'];
    }

    if ($conf['ACTPM'] == 'PP'){
      $parameters['ACCOUNT.BRAND']          = 'PAYPAL';
      $parameters['FRONTEND.PM.DEFAULT_DISABLE_ALL']  = 'true';
      $parameters['FRONTEND.PM.1.ENABLED']            = 'true';
      $parameters['FRONTEND.PM.1.METHOD']             = 'VA';
      $parameters['FRONTEND.PM.1.SUBTYPES']           = 'PAYPAL';
      $payCode = 'VA';
    } else if ($conf['ACTPM'] == 'PF'){
      $parameters['ACCOUNT.BRAND']          = 'PF_KARTE_DIRECT';
      $parameters['ACCOUNT.ID']             = $userData['email'];
      $parameters['FRONTEND.ENABLED']       = "false";
      //$currency = 'CHF';
      $payCode = 'VA';
    }
    
    if ($conf['ACTPM'] != 'PP'){
      foreach($this->availablePayments as $key=>$value) {
        if ($value != $payCode) {
          $parameters["FRONTEND.PM." . (string)($key + 1) . ".METHOD"] = $value;
          $parameters["FRONTEND.PM." . (string)($key + 1) . ".ENABLED"] = "false";
        }
      }
    }
    
    $parameters['PAYMENT.CODE']                 = $payCode.".".$mode;
    $parameters['FRONTEND.RESPONSE_URL']        = $conf['RESPONSE_URL'];
    $parameters['NAME.GIVEN']                   = trim($userData['firstname']);
    $parameters['NAME.FAMILY']                  = trim($userData['lastname']);
    $parameters['ADDRESS.STREET']               = $userData['street'];
    $parameters['ADDRESS.ZIP']                  = $userData['zip'];
    $parameters['ADDRESS.CITY']                 = $userData['city'];
    $parameters['ADDRESS.COUNTRY']              = $userData['country'];
    $parameters['CONTACT.EMAIL']                = $userData['email'];
    $parameters['PRESENTATION.AMOUNT']          = $amount; // 99.00
    $parameters['PRESENTATION.CURRENCY']        = $currency; // EUR
    $parameters['ACCOUNT.COUNTRY']              = $userData['country'];
    $parameters['CONTACT.IP']                   = $userData['ip'];
    $parameters['CONTACT.PHONE']                = $userData['phone'];

    if (!empty($userData['mobile']))
      $parameters['CONTACT.MOBILE']             = $userData['mobile'];

    if (!empty($userData['dob']))
      $parameters['NAME.BIRTHDATE']             = $userData['dob'];

    if (!empty($userData['sex']))
      $parameters['NAME.SEX']                   = $userData['sex'];

    if (!empty($userData['company']))
      $parameters['NAME.COMPANY']               = $userData['company'];

    return $parameters;
  }

  function doRequest($conf, $xml = NULL, $query = false)
  {
    $url = $this->test_url;
    $sdkUrl = $this->test_sdk_url;
    $tokenUrl = $this->test_token_url;
    if ($conf['ENV.MODE'] == 'LIVE'){
      $url = $this->live_url;      
      $sdkUrl = $this->test_sdk_url;
      $tokenUrl = $this->live_token_url;
    }

    $pString = '';
    foreach ($conf AS $k => $v) {
      $pString.= '&'.strtoupper($k).'='.urlencode(utf8_decode($v));
      //$pString.= '&'.strtoupper($k).'='.$v;
    }
    $pString = stripslashes($pString);
    if (!empty($xml)) {
      $pString = 'load='.urlencode($xml);
      //$pString = 'load='.$xml;
      $url = $this->test_url;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $pString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
    curl_setopt($ch, CURLOPT_USERAGENT, "payment request");

    $this->response     = curl_exec($ch);
    $this->error        = curl_error($ch);
    curl_close($ch);

    $res = $this->response;
    if (!$this->response && $this->error){
      $msg = urlencode('Curl Fehler');
      //$msg = 'Curl Fehler';
      $res = 'status=FAIL&msg='.$this->error;
    }

    return $res;
  }

  function parseResult($curlresultURL)
  {
    $r_arr=explode("&",$curlresultURL);
    foreach($r_arr AS $buf) {
      $temp=urldecode($buf);
      $temp=explode("=",$temp,2);
      $postatt=$temp[0];
      $postvar=$temp[1];
      $returnvalue[$postatt]=$postvar;
    }
    $processingresult = $returnvalue['PROCESSING.RESULT'];
    if (empty($processingresult)) $processingresult = $returnvalue['POST.VALIDATION'];
    $redirectURL = $returnvalue['FRONTEND.REDIRECT_URL'];
    if (!isset($returnvalue['PROCESSING.RETURN']) && $returnvalue['POST.VALIDATION'] > 0){
      $returnvalue['PROCESSING.RETURN'] = 'Errorcode: '.$returnvalue['POST.VALIDATION'];
    }
    ksort($returnvalue);
    return array('result' => $processingresult, 'url' => $redirectURL, 'all' => $returnvalue);
  }

  function createCustomer($param){
    $url = ($param['ENV.MODE'] == 'LIVE') ? $this->live_url : $this->test_url;
    $userData = $param['userData'];

    $data = array("billing_address" => array("city" => $userData['city'], "country" => $userData['country'],"state" => $userData['state'],
                  "street" => $userData['street'],"zip_or_postal"=> $userData['zip']),"email" => $userData['email'],"first_name" => $userData['firstname'],
                  "last_name" => $userData['lastname'],"mobile" => $userData['phone'],"phone" => $userData['phone']);  
    $data_string = json_encode($data);
   
    $customer_id = $this->callAPI('POST', $url . '/payment/customer', $param, $data_string);
   
    return $customer_id;
}

function genCardTokenWidget($twig, $param) {
  $apiUrl = ($param['ENV.MODE'] == 'LIVE') ? $this->live_url : $this->test_url;
  return $twig->render('Ceevo::content.tokenise', ['apiKey' => $param['API.KEY'], 'mode' => $param['ENV.MODE'], 'price' => $param['PRICE'], 
                        'currency' => $param['CURRENCY'], 'apiUrl' => $apiUrl, ])->getContent();
}

function registerAccountToken($conf, $customer_registered_id){
    $url = ($conf['ENV.MODE'] == 'LIVE') ? $this->live_url : $this->test_url;

    $token_array = array("account_token" => $_POST['token_hidden_input'],"is_default" => true,"verify" => true);
    $token_string = json_encode($token_array);
    $get_data = $this->callAPI('POST', $url . '/payment/customer/'.$customer_registered_id, $conf, $token_string);
    $response = json_decode($get_data, true);
}

function getToken($conf){
    $api = ($conf['ENV.MODE'] == 'LIVE') ? $this->live_token_url : $this->test_token_url;
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
        

    $jres = json_decode($res, true);

    $this->access_token  = $jres['access_token'];
    return $this->access_token;
} 

function chargeApi($param, $cusId){
    $url = ($param['ENV.MODE'] == 'LIVE') ? $this->live_url : $this->test_url;

    $userData = $param['userData'];   
    $orderId =  $param['ORDER.ID'];
    $apiKey =  $param['API.KEY'];
    $mode = $param['ENV.MODE'];

    $items_array = array();
    foreach($param['basketItems'] as $item){
      
      $item_json = array("item" => $item['name'],"itemValue" =>(string) $item['price']);
      array_push($items_array, json_encode($item_json));
    }
    $itemString = implode(',',$items_array);

    $access_token = $this->access_token;
    
    $authorization = "Authorization: Bearer $access_token";
   
    $charge_api = $url . "/payment/charge"; 
       
    
    $successURL = $param['REQUEST']['CRITERION.SUCCESSURL'];
    $failURL = $param['REQUEST']['CRITERION.FAILURL'];
    $cparam = '{"amount": '.( $param['AMOUNT'] * 100 ).',
            "3dsecure": true,
            "mode" : "'.$mode.'",
            "method_code":  "'.$_POST['method_code'].'",
            "currency": "'.$param['CURRENCY'].'",
            "customer_id": "'.$cusId.'", 
            "account_token": "'.$_POST['token_hidden_input'].'",
            "session_id": "'.$_POST['session_hidden_input'].'",
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
    //print_r($cparam);
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
        
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($cres, 0, $header_size);
        $body = substr($cres, $header_size); 
    //print_r($headers);
        curl_close($ch);
        $transactionHeaders = $this->http_parse_headers($headers);
        $transactionId = '';
        $ThreedURL = ''; 
        
    //print_r($transactionHeaders); die();
        if( $transactionHeaders[0]  == 'HTTP/1.1 201 Created') {
            
           $transactionId  =  $transactionHeaders['X-Gravitee-Transaction-Id'];
         }else if($transactionHeaders[0]  == 'HTTP/1.1 302 Found'){
            $ThreedURL   = $transactionHeaders['Location'];
            $transactionId  =  $transactionHeaders['X-Gravitee-Transaction-Id'];
            $_SESSION['3durl'] = $ThreedURL;
            
         }
        return $transactionId;
    }

    function callAPI($method, $url, $conf, $data){
      $apiKey =  $conf['API.KEY'];
       $access_token = $this->access_token;
  
       $authorization = "Authorization: Bearer $access_token";
      $curl = curl_init();
 
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
          //'X-CV-APIKey: 553fbbcd-f488-4e97-bf90-ad418a781e62'
          
      ));
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
 
      // EXECUTE:
      $response = curl_exec($curl);
      // Retudn headers seperatly from the Response Body
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($curl);
        header("Content-Type:text/plain; charset=UTF-8");
         $transactionHeaders = $this->http_parse_headers($headers);
          $cusId = '';
 
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
