<?php

namespace Ceevo\Containers;
 
use Plenty\Plugin\Templates\Twig;

use Ceevo\Services\SessionStorageService;
 
class CeevoTokeniseContainer
{
    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
  
    public function call(Twig $twig, SessionStorageService $sessionStorage)
    {
        $requestParams = $sessionStorage->getSessionValue('requestParams');

        if (!empty($requestParams)){
          return $twig->render('Ceevo::content.tokenise', ['requestParams' => $requestParams]);
        } else {
          return '404';
        }
    }
}
