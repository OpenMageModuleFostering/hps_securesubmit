<?php
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * Paypal expess checkout shortcut link
 *
 * @method string getShortcutHtmlId()
 * @method string getImageUrl()
 * @method string getCheckoutUrl()
 * @method string getBmlShortcutHtmlId()
 * @method string getBmlCheckoutUrl()
 * @method string getBmlImageUrl()
 * @method string getIsBmlEnabled()
 * @method string getConfirmationUrl()
 * @method string getIsInCatalogProduct()
 * @method string getConfirmationMessage()
 */
class Hps_Securesubmit_Block_Paypal_Shortcut extends Mage_Core_Block_Template
{
    /**
     * Position of "OR" label against shortcut
     */
    const POSITION_BEFORE = 'before';
    const POSITION_AFTER = 'after';

    /**
     * Whether the block should be eventually rendered
     *
     * @var bool
     */
    protected $_shouldRender = true;

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_paymentMethodCode = 'hps_paypal';

    /**
     * Start express action
     *
     * @var string
     */
    protected $_startAction = 'securesubmit/paypal/start/button/1';

    /**
     * Express checkout model factory name
     *
     * @var string
     */
    protected $_checkoutType = 'hps_securesubmit/paypal_checkout';

    /**
     * @return Mage_Core_Block_Abstract
     */
    protected function _beforeToHtml()
    {
        $result = parent::_beforeToHtml();
        $config = Mage::getModel('paypal/config', array($this->_paymentMethodCode));
        $isInCatalog = $this->getIsInCatalogProduct();
        $quote = ($isInCatalog || '' == $this->getIsQuoteAllowed())
            ? null : Mage::getSingleton('checkout/session')->getQuote();

        // check visibility on cart or product page
        $context = $isInCatalog ? 'visible_on_product' : 'visible_on_cart';
        // if (!$config->$context) {
        //     $this->_shouldRender = false;
        //     return $result;
        // }

        // if ($isInCatalog) {
        //     /** @var Mage_Catalog_Model_Product $currentProduct */
        //     $currentProduct = Mage::registry('current_product');
        //     if (!is_null($currentProduct)) {
        //         $price = (float)$currentProduct->getFinalPrice();
        //         $typeInstance = $currentProduct->getTypeInstance();
        //         if (empty($price) && !$currentProduct->isSuper() && !$typeInstance->canConfigure($currentProduct)) {
        //             $this->_shouldRender = false;
        //             return $result;
        //         }
        //     }
        // }

        // validate minimum quote amount and validate quote for zero grandtotal
        if (null !== $quote && (!$quote->validateMinimumAmount()
            || (!$quote->getGrandTotal() && !$quote->hasNominalItems()))) {
            $this->_shouldRender = false;
            return $result;
        }

        // check payment method availability
        $methodInstance = Mage::helper('payment')->getMethodInstance($this->_paymentMethodCode);
        if (!$methodInstance || !$methodInstance->isAvailable($quote)) {
            $this->_shouldRender = false;
            return $result;
        }

        // set misc data
        $this->setShortcutHtmlId($this->helper('core')->uniqHash('hps_shortcut_'))
            ->setCheckoutUrl($this->getUrl($this->_startAction));

        $this->_getBmlShortcut($quote);

        // use static image if in catalog
        // if ($isInCatalog || null === $quote) {
        //     $this->setImageUrl($config->getExpressCheckoutShortcutImageUrl(Mage::app()->getLocale()->getLocaleCode()));
        // } else {
            $this->setImageUrl('https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-medium.png');
        // }

        // // ask whether to create a billing agreement
        // $customerId = Mage::getSingleton('customer/session')->getCustomerId(); // potential issue for caching
        // if (Mage::helper('paypal')->shouldAskToCreateBillingAgreement($config, $customerId)) {
        //     $this->setConfirmationUrl($this->getUrl($this->_startAction,
        //         array(Hps_Securesubmit_Model_Paypal_Checkout::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT => 1)
        //     ));
        //     $this->setConfirmationMessage(Mage::helper('paypal')->__('Would you like to sign a billing agreement to streamline further purchases with PayPal?'));
        // }

        return $result;
    }

    /**
     * @param $quote
     *
     * @return Hps_Securesubmit_Block_Paypal_Shortcut
     */
    protected function _getBmlShortcut($quote)
    {
        $bml = Mage::helper('payment')->getMethodInstance('hps_paypal_bml');
        $isBmlEnabled = $bml && $bml->isAvailable($quote);
        $this->setBmlShortcutHtmlId($this->helper('core')->uniqHash('hps_shortcut_bml_'))
            ->setBmlCheckoutUrl($this->getUrl('securesubmit/paypal/credit/button/1'))
            ->setBmlImageUrl('https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-medium.png')
            ->setMarketMessage('https://www.paypalobjects.com/webstatic/en_US/btn/btn_bml_text.png')
            ->setMarketMessageUrl('https://www.securecheckout.billmelater.com/paycapture-content/'
                . 'fetch?hash=AU826TU8&content=/bmlweb/ppwpsiw.html')
            ->setIsBmlEnabled(true);
        return $this;
    }

    /**
     * Render the block if needed
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->_shouldRender) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Check is "OR" label position before shortcut
     *
     * @return bool
     */
    public function isOrPositionBefore()
    {
        return ($this->getIsInCatalogProduct() && !$this->getShowOrPosition())
            || ($this->getShowOrPosition() && $this->getShowOrPosition() == self::POSITION_BEFORE);

    }

    /**
     * Check is "OR" label position after shortcut
     *
     * @return bool
     */
    public function isOrPositionAfter()
    {
        return (!$this->getIsInCatalogProduct() && !$this->getShowOrPosition())
            || ($this->getShowOrPosition() && $this->getShowOrPosition() == self::POSITION_AFTER);
    }
}
