<?php

namespace craft\commerce\square\services;

use craft\base\Component;
use craft\commerce\Plugin as Commerce;
use craft\commerce\square\errors\CustomerException;
use craft\commerce\square\gateways\Gateway;
use craft\commerce\square\models\SquareCustomer;
use craft\commerce\square\records\Customer as CustomerRecord;
use craft\db\Query;
use craft\elements\User;

/**
 * Class Customers
 *
 * @package craft\commerce\square\services
 */
class Customers extends Component
{
    /**
     * @param \craft\commerce\square\gateways\Gateway $gateway
     * @param \craft\elements\User                    $user
     *
     * @return \craft\commerce\square\models\SquareCustomer
     * @throws \craft\commerce\square\errors\CustomerException
     */
    public function getCustomer(Gateway $gateway, User $user): SquareCustomer
    {
        $record = $this->getCustomerQuery()
            ->where([
                'gatewayId' => $gateway->id,
                'userId' => $user->id,
            ])
            ->one();

        if ($record !== null) {
            return new SquareCustomer($record);
        }

        $customer = $gateway->createCustomer($user);

        if (!$this->saveCustomer($customer)) {
            $errors = implode(', ', $customer->getErrorSummary(true));
            throw new CustomerException("Could not save customer: {$errors}");
        }

        return $customer;
    }

    /**
     * @param int $id
     *
     * @return \craft\commerce\square\models\SquareCustomer
     */
    public function getCustomerById(int $id): SquareCustomer
    {
        $record = $this->getCustomerQuery()
            ->where([
                'id' => $id,
            ])
            ->one();

        if ($record) {
            return new SquareCustomer($record);
        }

        return null;
    }

    /**
     * @param string $reference
     *
     * @return \craft\commerce\square\models\SquareCustomer
     */
    public function getCustomerByReference(string $reference): SquareCustomer
    {
        $record = $this->getCustomerQuery()
            ->where([
                'reference' => $reference,
            ])
            ->one();

        if ($record) {
            return new SquareCustomer($record);
        }

        return null;
    }

    /**
     * @param \craft\commerce\square\models\SquareCustomer $customer
     *
     * @return bool
     * @throws \craft\commerce\square\errors\CustomerException
     */
    public function saveCustomer(SquareCustomer $customer): bool
    {
        if ($customer->id) {
            $record = CustomerRecord::findOne($customer->id);

            if (!$record) {
                throw new CustomerException('No customer exists with the ID “{id}”', [
                    'id' => $customer->id,
                ]);
            }
        } else {
            $record = new CustomerRecord();
        }

        $record->userId = $customer->userId;
        $record->gatewayId = $customer->gatewayId;
        $record->reference = $customer->reference;
        $record->response = $customer->response;

        $customer->validate();

        if (!$customer->hasErrors()) {
            $record->save(false);

            $customer->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteCustomerById($id): bool
    {
        $record = CustomerRecord::findOne($id);

        if ($record) {
            return (bool) $record->delete();
        }

        return false;
    }

    /**
     * @return \craft\db\Query
     */
    protected function getCustomerQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'gatewayId',
                'userId',
                'reference',
                'response',
            ])
            ->from(CustomerRecord::tableName());
    }
}
