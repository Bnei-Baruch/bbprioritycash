<?php

use Civi\Api4\Contribution;

class CRM_Core_Payment_BBPriorityCashIPN extends CRM_Core_Payment_BaseIPN {
    function __construct($inputData) {
        $this->setInputParameters($inputData);
        parent::__construct();
    }

    function main(&$paymentProcessor, &$input, &$ids): void {
        try {
            $contributionID = $input['contributionID'];
            $contribution = $this->getContribution($contributionID);

            if ($this->getContributionStatus($contribution) === 'Completed') {
                Civi::log('BBPCC IPN')->debug('returning since contribution has already been handled');
                return;
            }

            $input['amount'] = $contribution['total_amount'];
            $contribution['txrn_id'] = 'Cash';

            echo("bbpriorityCC IPN success");
            $this->redirectSuccess($input);
            CRM_Utils_System::civiExit();
        } catch (CRM_Core_Exception $e) {
            Civi::log('BBPCC IPN')->debug($e->getMessage());
            echo 'Invalid or missing data';
        }
        CRM_Utils_System::civiExit();
    }

    function getInput(&$input, &$ids) {
        $input = array(
            // GET Parameters
            'module' => self::retrieve('md', 'String'),
            'component' => self::retrieve('md', 'String'),
            'qfKey' => self::retrieve('qfKey', 'String', false),
            'contributionID' => self::retrieve('contributionID', 'String'),
            'contactID' => self::retrieve('contactID', 'String'),
            'eventID' => self::retrieve('eventID', 'String', false),
            'participantID' => self::retrieve('participantID', 'String', false),
            'membershipID' => self::retrieve('membershipID', 'String', false),
            'contributionPageID' => self::retrieve('contributionPageID', 'String', false),
            'relatedContactID' => self::retrieve('relatedContactID', 'String', false),
            'onBehalfDupeAlert' => self::retrieve('onBehalfDupeAlert', 'String', false),
            'returnURL' => self::retrieve('returnURL', 'String', false),
        );

        $ids = array(
            'contribution' => $input['contributionID'],
            'contact' => $input['contactID'],
        );
        if ($input['module'] == "event") {
            $ids['event'] = $input['eventID'];
            $ids['participant'] = $input['participantID'];
        } else {
            $ids['membership'] = $input['membershipID'];
            $ids['related_contact'] = $input['relatedContactID'];
            $ids['onbehalf_dupe_alert'] = $input['onBehalfDupeAlert'];
        }
    }

    function redirectSuccess(&$input): void {
        $returnURL = $this->base64_url_decode($input['returnURL']);

        // Print the tpl to redirect to success
        $template = CRM_Core_Smarty::singleton();
        $template->assign('url', $returnURL);
        print $template->fetch('CRM/Core/Payment/BbpriorityCash.tpl');
    }

    public function retrieve($name, $type, $abort = TRUE, $default = NULL) {
        $value = CRM_Utils_Type::validate(
            empty($this->_inputParameters[$name]) ? $default : $this->_inputParameters[$name],
            $type,
            FALSE
        );
        if ($abort && $value === NULL) {
            throw new CRM_Core_Exception("Could not find an entry for $name");
        }
        return $value;
    }

    private function getContribution($contribution_id) {
        // Check if the contribution exists
        // make sure contribution exists and is valid
        $contribution = Contribution::get(FALSE)
            ->addWhere('id', '=', $contribution_id)
            ->addSelect('*')
            ->addSelect('contribution_status_id:name')
            ->execute()->first();
        if (empty($contribution)) {
            $input = $ids = array();
            throw new CRM_Core_Exception('Failure: Could not find contribution record for ' . $contribution_id, NULL,
                ['context' =>
                    'Could not find contribution record: ' . $contribution_id . ' in IPN request: '
                    . print_r($this->getInput($input, $ids), TRUE)
                ]);
        }
        return $contribution;
    }

    function base64_url_decode($input) {
        return base64_decode(strtr($input, '-_', '+/'));
    }

    private function getContributionStatus($contribution): string {
        return $contribution['contribution_status_id:name'];
    }
}
