<?php

require_once Mage::getBaseDir('lib').DS.'SecureSubmit'.DS.'Hps.php';
/**
 * @category   Hps
 * @package    Hps_Securesubmit
 * @copyright  Copyright (c) 2015 Heartland Payment Systems (https://www.magento.com)
 * @license    https://github.com/SecureSubmit/heartland-magento-extension/blob/master/LICENSE  Custom License
 */

/**
 * NVP API wrappers model
 * @TODO: move some parts to abstract, don't hesitate to throw exceptions on api calls
 */
class Hps_Securesubmit_Model_Api_Paypal extends Hps_Securesubmit_Model_Api_Abstract
{
    /**
     * Filter callbacks for preparing internal amounts to NVP request
     *
     * @var array
     */
    protected $_exportToRequestFilters = array(
        'AMT'         => '_filterAmount',
        'ITEMAMT'     => '_filterAmount',
        'TRIALAMT'    => '_filterAmount',
        'SHIPPINGAMT' => '_filterAmount',
        'TAXAMT'      => '_filterAmount',
        'INITAMT'     => '_filterAmount',
        'CREDITCARDTYPE' => '_filterCcType',
        'AUTOBILLAMT' => '_filterBillFailedLater',
        'BILLINGPERIOD' => '_filterPeriodUnit',
        'TRIALBILLINGPERIOD' => '_filterPeriodUnit',
        'FAILEDINITAMTACTION' => '_filterInitialAmountMayFail',
        'BILLINGAGREEMENTSTATUS' => '_filterBillingAgreementStatus',
        'NOSHIPPING' => '_filterInt',
    );

    protected $_importFromRequestFilters = array(
        'REDIRECTREQUIRED'  => '_filterToBool',
        'SUCCESSPAGEREDIRECTREQUESTED'  => '_filterToBool',
        'PAYMENTSTATUS' => '_filterPaymentStatusFromNvpToInfo',
    );

    /**
     * SetExpressCheckout request/response map
     * @var array
     */
    protected $_setExpressCheckoutRequest = array(
        'PAYMENTACTION', 'AMT', 'CURRENCYCODE', 'RETURNURL', 'CANCELURL', 'INVNUM', 'SOLUTIONTYPE', 'NOSHIPPING',
        'GIROPAYCANCELURL', 'GIROPAYSUCCESSURL', 'BANKTXNPENDINGURL',
        'PAGESTYLE', 'HDRIMG', 'HDRBORDERCOLOR', 'HDRBACKCOLOR', 'PAYFLOWCOLOR', 'LOCALECODE',
        'BILLINGTYPE', 'SUBJECT', 'ITEMAMT', 'SHIPPINGAMT', 'TAXAMT', 'REQBILLINGADDRESS',
        'USERSELECTEDFUNDINGSOURCE'
    );
    protected $_setExpressCheckoutResponse = array('TOKEN');

    /**
     * GetExpressCheckoutDetails request/response map
     * @var array
     */
    protected $_getExpressCheckoutDetailsRequest = array('TOKEN', 'SUBJECT',);

    /**
     * Line items export mapping settings
     * @var array
     */
    protected $_lineItemExportItems = array(
        'id',
        'name',
        'qty',
        'amount',
    );

    /**
     * SetExpressCheckout call
     * @link https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_SetExpressCheckout
     * TODO: put together style and giropay settings
     */
    public function callSetExpressCheckout($credit = null)
    {
        $this->_prepareExpressCheckoutCallRequest($this->_setExpressCheckoutRequest);
        $request = $this->_exportToRequest($this->_setExpressCheckoutRequest);

        $amount = $this->getAmount();
        $currency = $this->getCurrencyCode();
        $totals = $this->_cart->getTotals();

        $buyer = new HpsBuyerData();
        $buyer->returnUrl = $this->getReturnUrl();
        $buyer->cancelUrl = $this->getCancelUrl();
        $buyer->credit = $credit;

        $payment = new HpsPaymentData();
        $payment->subtotal = $totals[Mage_Paypal_Model_Cart::TOTAL_SUBTOTAL];
        $payment->shippingAmount = $totals[Mage_Paypal_Model_Cart::TOTAL_SHIPPING];
        $payment->taxAmount = $totals[Mage_Paypal_Model_Cart::TOTAL_TAX];
        $payment->paymentType = (Mage::getStoreConfig('payment/hps_paypal/payment_action') == 'authorize_capture'
            ? 'Sale' : 'Authorization');

        // import/suppress shipping address, if any
        $options = $this->getShippingOptions();
        $shippingInfo = null;
        if ($this->getAddress()) {
            $a = $this->getAddress();
            $regionModel = Mage::getModel('directory/region')->load($a->getRegionId());
            $shippingInfo = new HpsShippingInfo();
            $shippingInfo->name = $a->getFirstname() . ' ' . $a->getMiddlename() . ' ' . $a->getLastname();
            $shippingInfo->address = new HpsAddress();
            $shippingInfo->address->address = $a->getData('street');
            $shippingInfo->address->city = $a->getCity();
            $shippingInfo->address->state = $regionModel->getCode();
            $shippingInfo->address->zip = $a->getPostcode();
            $shippingInfo->address->country = $a->getCountryId();

            if ($a->getEmail()) {
                $buyer->emailAddress = $a->getEmail();
            }
        } elseif ($options && (count($options) <= 10)) { // doesn't support more than 10 shipping options
            // $request['CALLBACK'] = $this->getShippingOptionsCallbackUrl();
            // $request['CALLBACKTIMEOUT'] = 6; // max value
            // $request['MAXAMT'] = $request['AMT'] + 999.00; // it is impossible to calculate max amount
            // $this->_exportShippingOptions($request);
        }

        $lineItems = $this->_exportLineItems();

        $config = new HpsServicesConfig();
        if (Mage::getStoreConfig('payment/hps_paypal/use_sandbox')) {
            $config->username  = Mage::getStoreConfig('payment/hps_paypal/username');
            $config->password  = Mage::getStoreConfig('payment/hps_paypal/password');
            $config->deviceId  = Mage::getStoreConfig('payment/hps_paypal/device_id');
            $config->licenseId = Mage::getStoreConfig('payment/hps_paypal/license_id');
            $config->siteId    = Mage::getStoreConfig('payment/hps_paypal/site_id');
            $config->soapServiceUri = "https://posgateway.secureexchange.net/Hps.Exchange.PosGateway/PosGatewayService.asmx";
            //$config->soapServiceUri  = "https://api-uat.heartlandportico.com/paymentserver.v1/PosGatewayService.asmx";
            //$config->soapServiceUri = "https://api-uat.heartlandportico.com/paymentserver.HotFix/POSGatewayService.asmx";
        } else {
            $config->secretApiKey = Mage::getStoreConfig('payment/hps_paypal/secretapikey');
        }

        $paypalService = new HpsPayPalService($config);
        $response = $paypalService->createSession($amount, $currency, $buyer, $payment, $shippingInfo, $lineItems);

        $this->token = $response->sessionId;
        return $response;
    }

    /**
     * GetExpressCheckoutDetails call
     * @link https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_GetExpressCheckoutDetails
     */
    function callGetExpressCheckoutDetails()
    {
        $this->_prepareExpressCheckoutCallRequest($this->_getExpressCheckoutDetailsRequest);
        $request = $this->_exportToRequest($this->_getExpressCheckoutDetailsRequest);

        $token = $this->getToken();

        $config = new HpsServicesConfig();
        if (Mage::getStoreConfig('payment/hps_paypal/use_sandbox')) {
            $config->username  = Mage::getStoreConfig('payment/hps_paypal/username');
            $config->password  = Mage::getStoreConfig('payment/hps_paypal/password');
            $config->deviceId  = Mage::getStoreConfig('payment/hps_paypal/device_id');
            $config->licenseId = Mage::getStoreConfig('payment/hps_paypal/license_id');
            $config->siteId    = Mage::getStoreConfig('payment/hps_paypal/site_id');
            $config->soapServiceUri = "https://posgateway.secureexchange.net/Hps.Exchange.PosGateway/PosGatewayService.asmx";
            //$config->soapServiceUri  = "https://api-uat.heartlandportico.com/paymentserver.v1/PosGatewayService.asmx";
            //$config->soapServiceUri = "https://api-uat.heartlandportico.com/paymentserver.HotFix/POSGatewayService.asmx";
        } else {
            $config->secretApiKey = Mage::getStoreConfig('payment/hps_paypal/secretapikey');
        }

        $paypalService = new HpsPayPalService($config);
        $response = $paypalService->sessionInfo($token);

        return $response;
    }

    /**
     * Filter for credit card type
     *
     * @param string $value
     * @return string
     */
    protected function _filterCcType($value)
    {
        if (isset($this->_supportedCcTypes[$value])) {
            return $this->_supportedCcTypes[$value];
        }
        return '';
    }

    /**
     * Filter for true/false values (converts to boolean)
     *
     * @param mixed $value
     * @return mixed
     */
    protected function _filterToBool($value)
    {
        if ('false' === $value || '0' === $value) {
            return false;
        } elseif ('true' === $value || '1' === $value) {
            return true;
        }
        return $value;
    }

    /**
     * Filter for 'AUTOBILLAMT'
     *
     * @param string $value
     * @return string
     */
    protected function _filterBillFailedLater($value)
    {
        return $value ? 'AddToNextBilling' : 'NoAutoBill';
    }

    /**
     * Filter for 'BILLINGPERIOD' and 'TRIALBILLINGPERIOD'
     *
     * @param string $value
     * @return string
     */
    protected function _filterPeriodUnit($value)
    {
        switch ($value) {
            case 'day':        return 'Day';
            case 'week':       return 'Week';
            case 'semi_month': return 'SemiMonth';
            case 'month':      return 'Month';
            case 'year':       return 'Year';
        }
    }

    /**
     * Filter for 'FAILEDINITAMTACTION'
     *
     * @param string $value
     * @return string
     */
    protected function _filterInitialAmountMayFail($value)
    {
        return $value ? 'ContinueOnFailure' : 'CancelOnFailure';
    }

    /**
     * Filter for billing agreement status
     *
     * @param string $value
     * @return string
     */
    protected function _filterBillingAgreementStatus($value)
    {
        switch ($value) {
            case 'canceled':    return 'Canceled';
            case 'active':      return 'Active';
        }
    }

    /**
     * Convert payment status from NVP format to paypal/info model format
     *
     * @param string $value
     * @return string|null
     */
    protected function _filterPaymentStatusFromNvpToInfo($value)
    {
        switch ($value) {
            case 'None': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_NONE;
            case 'Completed': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_COMPLETED;
            case 'Denied': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_DENIED;
            case 'Expired': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_EXPIRED;
            case 'Failed': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_FAILED;
            case 'In-Progress': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_INPROGRESS;
            case 'Pending': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_PENDING;
            case 'Refunded': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_REFUNDED;
            case 'Partially-Refunded': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_REFUNDEDPART;
            case 'Reversed': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_REVERSED;
            case 'Canceled-Reversal': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_UNREVERSED;
            case 'Processed': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_PROCESSED;
            case 'Voided': return Hps_Securesubmit_Model_Info::PAYMENTSTATUS_VOIDED;
        }
    }

    /**
     * Check the EC request against unilateral payments mode and remove the SUBJECT if needed
     *
     * @param &array $requestFields
     */
    protected function _prepareExpressCheckoutCallRequest(&$requestFields)
    {
        if (!$this->_config->shouldUseUnilateralPayments()) {
            if ($key = array_search('SUBJECT', $requestFields)) {
                unset($requestFields[$key]);
            }
        }
    }
}
