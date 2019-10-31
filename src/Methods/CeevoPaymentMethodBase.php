<?php // strict

namespace Ceevo\Methods;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Application;

/**
 * Class CeevoPaymentMethod
 * @package Ceevo\Methods
 */
class CeevoPaymentMethodBase extends PaymentMethodService
{
    use Loggable;
    /**
     * @var BasketRepositoryContract
     */
    private $basketRepo;

    /**
     * @var ContactRepositoryContract
     */
    private $contactRepo;

    /**
     * @var ConfigRepository
     */
    private $configRepo;
    
    var $type = 'N.A.';
    
    var $icons = array(
      'CV'    => 'cv.png',
      // 'DC'    => 'dc.png',
      // 'DD'    => 'dd.png',
      // 'OTSU'  => 'otsu.png',
      // 'OTGP'  => 'otgp.png',
      // 'OTIDL' => 'otidl.png',
      // 'PF'    => 'pf.png',
      // 'PP'    => 'pp.png',
      //''    => '',
    );

    /**
     * PaymentMethod constructor.
     *
     * @param BasketRepositoryContract $basketRepo
     * @param ContactRepositoryContract $contactRepo
     * @param ConfigRepository $configRepo
     */
    public function __construct(BasketRepositoryContract    $basketRepo,
                                ContactRepositoryContract   $contactRepo,
                                ConfigRepository            $configRepo)
    {
        $this->basketRepo     = $basketRepo;
        $this->contactRepo    = $contactRepo;
        $this->configRepo     = $configRepo;
        
    }

    /**
     * Check whether the plugin is active
     *
     * @return bool
     */
    public function isActive()
    {
      /*
      $this
         ->getLogger('CeevoPaymentMethodBase::isActive')
         //->setReferenceType('this')
         //->setReferenceValue($this)
         ->info('CeevoPaymentMethod', [
           'this' => $this,
           'name_' => $this->configRepo->get('Ceevo.name_'.strtolower($this->type)), 
           'status_' => $this->configRepo->get('Ceevo.status_'.strtolower($this->type)),
           'channel_' => $this->configRepo->get('Ceevo.channel_'.strtolower($this->type)),
         ]);
       */
      if (!empty($this->configRepo->get('Ceevo.status_'.strtolower($this->type)))) return true;
      return false;
    }

    /**
     * Get the name of the plugin
     *
     * @return string
     */
    public function getName()
    {
      $name = $this->configRepo->get('Ceevo.name_'.strtolower($this->type));
      if(!strlen($name))
      {
          $name = 'Ceevo '.strtoupper($this->type);
      }
      return $name;
    }


    /**
     * Get the path of the icon
     *
     * @return string
     */
    public function getIcon()
    {
        
          $app = pluginApp(Application::class);
        $icon = $app->getUrlPath('ceevo').'/images/'.$this->icons[$this->type];

        return $icon;
        
    }

    /**
     * Get the description of the payment method. The description can be entered in the config.json.
     *
     * @return string
     */
    public function getDescription()
    {
        $desc = $this->configRepo->get('Ceevo.desc_'.strtolower($this->type));

        return $desc;
    }
}
