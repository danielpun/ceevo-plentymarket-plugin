<?php // strict

namespace Ceevo\Methods;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;

use Ceevo\Methods\CeevoPaymentMethodBase;

/**
 * Class CeevoPaymentMethod
 * @package Ceevo\Methods
 */
class CeevoPaymentMethodCV extends CeevoPaymentMethodBase
{
  var $type = 'CV';
}