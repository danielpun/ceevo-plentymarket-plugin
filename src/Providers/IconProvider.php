<?php
namespace Cvpa\Providers;

use Plenty\Plugin\Templates\Twig;


class IconProvider
{
    public function call(Twig $twig):string
    {
        return $twig->render('CeevoPayment::Icon');
    }

}