<?php // strict

namespace Cvpa\Methods;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;

use Cvpa\Methods\CvpaPaymentMethodBase;

/**
 * Class CvpaPaymentMethod
 * @package Cvpa\Methods
 */
class CvpaPaymentMethodCV extends CvpaPaymentMethodBase
{
  var $type = 'CV';
}