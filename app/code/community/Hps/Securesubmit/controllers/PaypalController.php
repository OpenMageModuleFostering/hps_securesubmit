<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * Paypal Checkout Controller
 */
class Hps_Securesubmit_PaypalController extends Hps_Securesubmit_Controller_Paypal_Abstract
{
    /**
     * Config mode type
     *
     * @var string
     */
    protected $_configType = 'hps_securesubmit/config';

    /**
     * Config method type
     *
     * @var string
     */
    protected $_configMethod = Hps_Securesubmit_Model_Config::METHOD_WPP_EXPRESS;

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected $_checkoutType = 'hps_securesubmit/paypal_checkout';

    /**
     * Action for Bill Me Later checkout button (product view and shopping cart pages)
     */
    public function creditAction()
    {
        $this->_forward('start', 'paypal', 'securesubmit', array(
            'credit' => 1,
            'button' => $this->getRequest()->getParam('button')
        ));
    }
}
