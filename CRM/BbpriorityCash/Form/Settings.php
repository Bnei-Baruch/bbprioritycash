<?php

require_once 'CRM/Core/Form.php';

class CRM_BbpriorityCash_Form_Settings extends CRM_Core_Form {
  public function buildQuickForm() {
    $this->add('checkbox', 'ipn_http', 'Use http for IPN Callback');
    $this->add('text', 'merchant_terminal', 'Merchant Terminal', array('size' => 5));

    $paymentProcessors = $this->getPaymentProcessors();
    foreach( $paymentProcessors as $paymentProcessor ) {
      $settingCode = 'merchant_terminal_' . $paymentProcessor[ "id" ];
      $settingTitle = $paymentProcessor[ "name" ] . " (" .
        ( $paymentProcessor["is_test"] == 0 ? "Live" : "Test" ) . ")";
      $this->add('text', $settingCode, $settingTitle, array('size' => 5));
    }

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));
    parent::buildQuickForm();
  }

  function setDefaultValues() {
    $defaults = array();
    $bbpriorityCash_settings = CRM_Core_BAO_Setting::getItem("BbpriorityCash Settings", 'bbpriorityCash_settings');
    if (!empty($bbpriorityCash_settings)) {
      $defaults = $bbpriorityCash_settings;
    }
    return $defaults;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $bbpriorityCash_settings['ipn_http'] = $values['ipn_http'];
    $bbpriorityCash_settings['merchant_terminal'] = $values['merchant_terminal'];
    
    $paymentProcessors = $this->getPaymentProcessors();
    foreach( $paymentProcessors as $paymentProcessor ) {
      $settingId = 'merchant_terminal_' . $paymentProcessor[ "id" ];
      $bbpriorityCash_settings[$settingId] = $values[$settingId];
    }
    
    CRM_Core_BAO_Setting::setItem($bbpriorityCash_settings, "Bb Priority Cash Settings", 'bbpriorityCash_settings');
    CRM_Core_Session::setStatus(
      ts('Bb Priority Cash Settings Saved', array( 'domain' => 'info.kabbalah.payment.bbpriorityCash')),
      'Configuration Updated', 'success');

    parent::postProcess();
  }

  public function getPaymentProcessors() {
    // Get the BbpriorityCash payment processor type
    $bbpriorityCashName = array( 'name' => 'BbpriorityCash' );
    $paymentProcessorType = civicrm_api3( 'PaymentProcessorType', 'getsingle', $bbpriorityCashName );

    // Get the payment processors of bbpriorityCash type
    $bbpriorityCashType = array(
      'payment_processor_type_id' => $paymentProcessorType[ 'id' ],
      'is_active' => 1 );
    $paymentProcessors = civicrm_api3( 'PaymentProcessor', 'get', $bbpriorityCashType );

    return $paymentProcessors["values"];
  }
}
