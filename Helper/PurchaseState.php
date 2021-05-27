<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Helper;

/**
 * Class PurchaseState
 */
class PurchaseState
{
    const INITIALIZED = 'Initialized';
    const EMAIL_ZIP_ENTRY = 'EmailZipEntry';
    const SSN_ENTRY = 'SsnEntry';
    const PHONE_NUMBER_ENTRY = 'PhoneNumberEntry';
    const PHONE_NUMBER_ENTRY_FOR_KNOWN_CUSTOMER = 'PhoneNumberEntryForKnownCustomer';
    const PERSONAL_INFO = 'PersonalInfo';
    const WAITING_FOR_SWISH = 'WaitingForSwish';
    const REDIRECTED_TO_DIRECT_PAYMENT_BANK = 'RedirectedToDirectPaymentBank';
    const REDIRECTED_TO_NETS = 'RedirectedToNets';
    const WAITING_FOR_BANK_ID = 'WaitingForBankId';
    const REDIRECTED_TO_TUPAS = 'RedirectedToTupas';
    const COMPLETED = 'Completed';
    const TIMED_OUT = 'TimedOut';
    const OUTDATED = 'Outdated';
    const HANDLED_BY_MERCHANT = 'HandledByMerchant';
    const AWAITING_CREDIT_APPROVAL = 'AwaitingCreditApproval';
    const UNKNOWN = 'Unknown';

    public static array $stateIds = [
        0 => self::INITIALIZED,
        1 => self::EMAIL_ZIP_ENTRY,
        2 => self::SSN_ENTRY,
        3 => self::PHONE_NUMBER_ENTRY,
        4 => self::PERSONAL_INFO,
        5 => self::WAITING_FOR_SWISH,
        6 => self::REDIRECTED_TO_DIRECT_PAYMENT_BANK,
        7 => self::REDIRECTED_TO_NETS,
        8 => self::WAITING_FOR_BANK_ID,
        9 => self::REDIRECTED_TO_TUPAS,
        10 => self::COMPLETED,
        11 => self::TIMED_OUT,
        12 => self::HANDLED_BY_MERCHANT,
        13 => self::AWAITING_CREDIT_APPROVAL,
        14 => self::PHONE_NUMBER_ENTRY_FOR_KNOWN_CUSTOMER,
        99 => self::UNKNOWN,
    ];

    public static array $states = [
        'Initialized',
        'EmailZipEntry',
        'SsnEntry',
        'PhoneNumberEntry',
        'PhoneNumberEntryForKnownCustomer',
        'PersonalInfo',
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
     * Check if payment is cancelled
     *
     * @param string $state
     * @return bool
     */
    public function isCancelled($state)
    {
        return in_array(
            $this->getState($state),
            [self::TIMED_OUT],
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
        return $this->isCancelled($state) || $this->getState($state) == self::TIMED_OUT;
    }
}
