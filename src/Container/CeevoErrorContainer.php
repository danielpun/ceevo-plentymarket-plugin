<?php

namespace Ceevo\Containers;
 
use Plenty\Plugin\Templates\Twig;

use Ceevo\Services\SessionStorageService;
 
class CeevoErrorContainer
{
    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
  
    public function call(Twig $twig, SessionStorageService $sessionStorage)
    {
        $status = $sessionStorage->getSessionValue('lastPS');
        $result = $sessionStorage->getSessionValue('lastPR');
        if (!empty($status)){
          $errorMSG = $status.': '.$result;
          $sessionStorage->setSessionValue('lastPS', NULL);
          $sessionStorage->setSessionValue('lastPR', NULL);
          return $twig->render('Ceevo::content.error', ['errorText' => $errorMSG]);
        } else {
          return '';
        }
    }
}
