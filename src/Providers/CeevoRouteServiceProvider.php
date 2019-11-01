<?php

namespace Ceevo\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class CeevoRouteServiceProvider
 * @package Ceevo\Providers
 */
class CeevoRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        $router->get('payment/ceevo/style',            'Ceevo\Controllers\CeevoResponseController@getStyle');
        $router->post('payment/ceevo/response',        'Ceevo\Controllers\CeevoResponseController@handleResponse');
        $router->post('payment/ceevo/card_token',        'Ceevo\Controllers\CeevoResponseController@handleCardToken');
        $router->get('payment/ceevo/token_frame',        'Ceevo\Controllers\CeevoResponseController@getTokenFrame');
        $router->get('payment/ceevo/checkout_failure', 'Ceevo\Controllers\CeevoResponseController@checkoutFailure');
        $router->get('payment/ceevo/checkout_success', 'Ceevo\Controllers\CeevoResponseController@checkoutSuccess');
    }
}
