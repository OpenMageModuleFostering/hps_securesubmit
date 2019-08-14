<?php

class Hps_Securesubmit_Adminhtml_Hps_Paypal_ReportsController extends Mage_Adminhtml_Controller_Action
{
    protected function _initAction()
    {
        $this->loadLayout();
            // ->_setActiveMenu('hps_securesubmit/items');
            // ->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
        return $this;
    }   
   
    public function indexAction() {
        $this->_initAction();       
        $this->_addContent($this->getLayout()->createBlock('hps_securesubmit/adminhtml_paypal_settlement_report'));
        $this->renderLayout();
    }
}
