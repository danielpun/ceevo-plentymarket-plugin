<?php // strict

namespace Cvpa\Providers;

use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Plugin\Templates\Twig;

use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Log\Loggable;

use Cvpa\Services\PaymentService;
use Cvpa\Helper\PaymentHelper;
use Cvpa\Methods\CvpaPaymentMethodBase;
use Cvpa\Methods\CvpaPaymentMethodCV;
// use Cvpa\Methods\CvpaPaymentMethodDC;
// use Cvpa\Methods\CvpaPaymentMethodDD;
// use Cvpa\Methods\CvpaPaymentMethodOTSU;
// use Cvpa\Methods\CvpaPaymentMethodOTGP;
// use Cvpa\Methods\CvpaPaymentMethodOTIDL;
// use Cvpa\Methods\CvpaPaymentMethodPF;
// use Cvpa\Methods\CvpaPaymentMethodPP;

/**
 * Class CvpaServiceProvider
 * @package Cvpa\Providers
 */
class CvpaServiceProvider extends ServiceProvider
{
  use Loggable;
  var $availablePayments = array(
    'CV'    => 'CEEVO',
    // 'DC'    => 'Debit Card',
    // 'DD'    => 'Direct Debit',
    // 'OTSU'  => 'Sofort Banking',
    // 'OTGP'  => 'Giropay',
    // 'OTIDL' => 'iDeal',
    // 'PF'    => 'Post Finance',
    // 'PP'    => 'PayPal',
    //''    => '',
  );

  private $twig;
  
  /**
     * Register the route service provider
     */
    public function register()
    {
      $this->getApplication()->register(CvpaRouteServiceProvider::class);
      //$this->getApplication()->bind(RefundEventProcedure::class);
    }
    
    /**
     * Boot additional services
     *
     * @param Dispatcher               $eventDispatcher
     * @param PaymentHelper            $paymentHelper
     * @param PaymentService           $paymentService
     * @param BasketRepositoryContract $basket
     * @param PaymentMethodContainer   $payContainer
     * @param EventProceduresService   $eventProceduresService
     */
    public function boot(   Twig $twig, Dispatcher $eventDispatcher,
                            PaymentHelper $paymentHelper,
                            PaymentService $paymentService,
                            BasketRepositoryContract $basket,
                            PaymentMethodContainer $payContainer,
                            EventProceduresService $eventProceduresService)
    {
      foreach ($this->availablePayments AS $k => $v){
        // Create the ID of the payment method if it doesn't exist yet
        $paymentHelper->createMopIfNotExists($k, $v);
    
        $regName = 'cvpa::CVPA'.$k;
        $className = 'Cvpa\Methods\CvpaPaymentMethod'.$k; 
        // Register the payment method in the payment method container
        $payContainer->register($regName, $className, [ AfterBasketChanged::class, AfterBasketItemAdd::class, AfterBasketCreate::class ]);
      }
        $this->twig = $twig;
        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(GetPaymentMethodContent::class,
            function(GetPaymentMethodContent $event) use( $paymentHelper,  $basket,  $paymentService)
            {
                $basket = $basket->load();
                
                //$output = 'getMop: '.$event->getMop();
                $selectedPaymethod = '';
                $selectedMopID = '';
                foreach ($this->availablePayments AS $k => $v){
                  //$output.= 'getPaymentMethod: '.$paymentHelper->getPaymentMethod($k);
                  if ($paymentHelper->getPaymentMethod($k) == $event->getMop()){
                    $selectedPaymethod = $k;
                    $selectedMopID = $paymentHelper->getPaymentMethod($k);
                  }
                }
                //$output.= 'basket: '.$paymentService->getPaymentContent($basket, $selectedPaymethod);
                $this
                ->getLogger('CvpaServiceProvider::boot::GetPaymentMethodContent')
                ->setReferenceType('this')
                ->setReferenceValue($this)
                ->info('CvpaServiceProvider', [
                  'this' => $this,
                  'basket' => $basket, 
                ]);
                  
                // $content = $paymentService->getPaymentContent($this->twig, $basket, $selectedPaymethod, $selectedMopID);
                $content = '<center><iframe src="https://cdn02.plentymarkets.com/uqmy53mopxtg/plugin/2/ceevo/views/content/tokenise.html" frameborder="0" width="90%" height="500"></iframe></center>';
                
                $event->setValue($content);
                $event->setType('htmlContent');
                
                $this
                  ->getLogger('CvpaServiceProvider::boot::GetPaymentMethodContent')
                  //->setReferenceType('this')
                  //->setReferenceValue($this)
                  ->info('CvpaServiceProvider', [
                    'this' => $this,
                    'basket' => $basket, 
                  ]);
                
            }
        );
        
        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function(ExecutePayment $event) use ( $paymentHelper, $paymentService)
            {
                $selectedPaymethod = '';
                $selectedMopID = '';
                foreach ($this->availablePayments AS $k => $v){
                  if ($paymentHelper->getPaymentMethod($k) == $event->getMop()){
                    $selectedPaymethod = $k;
                    $selectedMopID = $paymentHelper->getPaymentMethod($k);
                  }
                }
                
                // Execute the payment
                $paymentRes = $paymentService->executePayment($event->getOrderId(), $selectedPaymethod, $selectedMopID);
                
                $this
                  ->getLogger('CvpaServiceProvider::boot::ExecutePayment')
                  //->setReferenceType('this')
                  //->setReferenceValue($this)
                  ->info('CvpaServiceProvider', [
                    'this' => $this,
                    'getOrderId' => $event->getOrderId(),
                    'selectedPaymethod' => $selectedPaymethod, 
                    'selectedMopID' => $selectedMopID,
                    'paymentRes' => $paymentRes, 
                  ]);
                
                if ($paymentRes['success']){
                  $event->setType('success');
                  $event->setValue('The Payment has been executed successfully!');
                } else {
                  $event->setType('error');
                  $event->setValue('The Payment could not be executed!');
                }
                
                
            }
        );
        
        /*
        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(GetPaymentMethodContent::class,
            function(GetPaymentMethodContent $event) use( $paymentHelper,  $basket,  $paymentService)
            {
                $this->sendDbgMail('GetPaymentMethodContent called');
                if($event->getMop() == $paymentHelper->getPaymentMethod())
                {
                    $basket = $basket->load();
                    //echo '<pre>basket:'.print_r($basket, 1).'</pre>';
                    $this->sendDbgMail($basket);

                    $event->setValue($paymentService->getPaymentContent($basket));
                    $event->setType( $paymentService->getReturnType());
                }
            });

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function(ExecutePayment $event) use ( $paymentHelper, $paymentService)
            {
                $this->sendDbgMail('ExecutePayment called');
                if($event->getMop() == $paymentHelper->getPaymentMethod())
                {
                
                    // Execute the payment
                    $paymentData = $paymentService->executePayment();
                    //echo '<pre>paymentData:'.print_r($paymentData, 1).'</pre>';
                    $this->sendDbgMail($paymentData);
                    /*
                    // Check whether the PayPal payment has been executed successfully
                    if($paymentService->getReturnType() != 'errorCode')
                    {
                        // Create a plentymarkets payment from the paypal execution params
                        $plentyPayment = $paymentHelper->createPlentyPayment($paymentData);

                        if($plentyPayment instanceof Payment)
                        {
                            // Assign the payment to an order in plentymarkets
                            $paymentHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $event->getOrderId());

                            $event->setType('success');
                            $event->setValue('The Payment has been executed successfully!');
                        }
                    }
                    else
                    {
                        $event->setType('error');
                        $event->setValue('The PayPal-Payment could not be executed!');
                    }
                    */
        /*
                }
            });
    */
    }
    
}
