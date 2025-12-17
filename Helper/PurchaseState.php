<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Helper;

/**
 * Class PurchaseState
 */
class PurchaseState
{
    const string INITIALIZED = 'Initialized';
    const string EMAIL_ZIP_ENTRY = 'EmailZipEntry';
    const string SSN_ENTRY = 'SsnEntry';
    const string PHONE_NUMBER_ENTRY = 'PhoneNumberEntry';
    const string PHONE_NUMBER_ENTRY_FOR_KNOWN_CUSTOMER = 'PhoneNumberEntryForKnownCustomer';
    const string PERSONAL_INFO = 'PersonalInfo';
    const string PERSONAL_INFO_WITHOUT_SSN = 'PersonalInfoWithoutSsn';
    const string WAITING_FOR_SWISH = 'WaitingForSwish';
    const string REDIRECTED_TO_DIRECT_PAYMENT_BANK = 'RedirectedToDirectPaymentBank';
    const string REDIRECTED_TO_NETS = 'RedirectedToNets';
    const string WAITING_FOR_BANK_ID = 'WaitingForBankId';
    const string REDIRECTED_TO_TUPAS = 'RedirectedToTupas';
    const string COMPLETED = 'Completed';
    const string TIMED_OUT = 'TimedOut';
    const string OUTDATED = 'Outdated';
    const string CANCELED = 'Canceled';
    const string HANDLED_BY_MERCHANT = 'HandledByMerchant';
    const string AWAITING_CREDIT_APPROVAL = 'AwaitingCreditApproval';
    const string UNKNOWN = 'Unknown';

    public static array $stateIds = [
        0  => self::INITIALIZED,
        1  => self::EMAIL_ZIP_ENTRY,
        2  => self::SSN_ENTRY,
        3  => self::PHONE_NUMBER_ENTRY,
        4  => self::PERSONAL_INFO,
        5  => self::WAITING_FOR_SWISH,
        6  => self::REDIRECTED_TO_DIRECT_PAYMENT_BANK,
        7  => self::REDIRECTED_TO_NETS,
        8  => self::WAITING_FOR_BANK_ID,
        9  => self::REDIRECTED_TO_TUPAS,
        10 => self::COMPLETED,
        11 => self::TIMED_OUT,
        12 => self::HANDLED_BY_MERCHANT,
        13 => self::AWAITING_CREDIT_APPROVAL,
        14 => self::PHONE_NUMBER_ENTRY_FOR_KNOWN_CUSTOMER,
        15 => self::PERSONAL_INFO_WITHOUT_SSN,
        99 => self::UNKNOWN,
    ];

    public static array $states = [
        'Initialized',
        'EmailZipEntry',
        'SsnEntry',
        'PhoneNumberEntry',
        'PhoneNumberEntryForKnownCustomer',
        'PersonalInfo',
        'PersonalInfoWithoutSsn',
        'WaitingForSwish',
        'RedirectedToDirectPaymentBank',
        'RedirectedToNets',
        'WaitingForBankId',
        'RedirectedToTupas',
        'Completed',
        'TimedOut',
        'HandledByMerchant',
        'AwaitingCreditApproval',
        'Unknown',
    ];

    /**
     * Get payment state code for Magento order
     *
     * @param string $state
     * @return string
     */
    public function getState($state)
    {
        if (in_array($state, self::$states)) {
            return $state;
        }

        return self::UNKNOWN;
    }

    /**
     * Check if customer is in checkout
     *
     * @param string $state
     * @return bool
     */
    public function isInCheckout($state)
    {
        return in_array(
            $this->getState($state),
            [
                self::INITIALIZED,
                self::EMAIL_ZIP_ENTRY,
                self::PERSONAL_INFO,
                self::PERSONAL_INFO_WITHOUT_SSN,
                self::SSN_ENTRY,
                self::PHONE_NUMBER_ENTRY,
                self::PHONE_NUMBER_ENTRY_FOR_KNOWN_CUSTOMER
            ],
            true
        );
    }

    /**
     * Check if payment is complete
     *
     * @param string $state
     * @return bool
     */
    public function isComplete($state)
    {
        return ($this->getState($state) == self::COMPLETED);
    }

    /**
     * Check if payment is waiting for card/bank actions
     *
     * @param string $state
     * @return bool
     */
    public function isWaiting($state)
    {
        return in_array(
            $this->getState($state),
            [
                self::WAITING_FOR_BANK_ID,
                self::WAITING_FOR_SWISH,
                self::AWAITING_CREDIT_APPROVAL,
                self::REDIRECTED_TO_DIRECT_PAYMENT_BANK,
                self::REDIRECTED_TO_NETS,
                self::REDIRECTED_TO_TUPAS,
            ],
            true
        );
    }

    /**
     * Check if payment is dead
     *
     * @param string $state
     * @return bool
     */
    public function isDead($state)
    {
        return in_array($this->getState($state), [self::TIMED_OUT, self::OUTDATED, self::CANCELED]);
    }
}
