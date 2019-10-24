<?php

namespace Cvpa\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class CvpaRouteServiceProvider
 * @package Cvpa\Providers
 */
class CvpaRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        $router->get('payment/cvpa/style',            'Cvpa\Controllers\CvpaResponseController@getStyle');
        $router->post('payment/cvpa/response',        'Cvpa\Controllers\CvpaResponseController@handleResponse');
        $router->post('payment/cvpa/card_token',        'Cvpa\Controllers\CvpaResponseController@handleCardToken');
        $router->get('payment/cvpa/checkout_failure', 'Cvpa\Controllers\CvpaResponseController@checkoutFailure');
        $router->get('payment/cvpa/checkout_success', 'Cvpa\Controllers\CvpaResponseController@checkoutSuccess');
    }
}
