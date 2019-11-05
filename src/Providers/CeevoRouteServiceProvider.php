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
        $router->post('payment/ceevo/card_token',        'Ceevo\Controllers\CeevoResponseController@handleCardToken');
        $router->get('payment/ceevo/token_frame',        'Ceevo\Controllers\CeevoResponseController@getTokenFrame');
        $router->post('payment/ceevo/checkout_failure', 'Ceevo\Controllers\CeevoResponseController@checkoutFailure');
        $router->post('payment/ceevo/checkout_success', 'Ceevo\Controllers\CeevoResponseController@checkoutSuccess');
        $router->get('payment/ceevo/error_page',        'Ceevo\Controllers\CeevoResponseController@errorPage');
        $router->get('payment/ceevo/redirect_page',        'Ceevo\Controllers\CeevoResponseController@redirectPage');
    }
}
