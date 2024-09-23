<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Luigel\Paymongo\Facades\Paymongo;

class PaymentService
{
    /**
     * @throws GuzzleException
     */
    public function createPayment(array $data)
    {
        $paymentIntent = $this->createPaymentIntent(
            $data['amount'],
            $data['description']
        );

        $paymentMethod = $this->createPaymentMethod(
            $data['payment_method_type'],
            $data['payment_method_details'],
            $data['billing_details'],
            $data['billing_address']
        );

        $paymentIntent = $paymentIntent->attach($paymentMethod->getData()['id']);

        return $paymentIntent->getData();
    }

    public function addPayoutMethod(array $data)
    {
        $user = auth('sanctum')->user();

        // Create the payout method
        $payoutMethod = $user->payoutMethods()->create();

        // Add the details of the payout method
        $payoutMethod->payoutMethodDetail()->create($data);

        return $payoutMethod;
    }

    public function getPayoutMethods()
    {
        $user = auth('sanctum')->user();

        return $user->payoutMethods()
            ->with('payoutMethodDetail')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @throws GuzzleException
     */
    public function getPaymentMethods()
    {
        $user = auth('sanctum')->user();

        return $this->getCustomer($user)->paymentMethods();
    }

    /**
     * @throws GuzzleException
     */
    public function getCustomer(User $user)
    {
        if ($user->paymongo_customer_id) {
            return Paymongo::customer()->find($user->paymongo_customer_id);
        }

        // Try to search for the customer first, if not found, create a new one
        $customer = $this->searchCustomer($user);

        if ($customer['data']) {
            // Get the first customer found
            $customerId = $customer['data'][0]['id'];

            $user->update([
                'paymongo_customer_id' => $customerId,
            ]);

            return Paymongo::customer()->find($customerId);
        }

        return $this->createCustomer($user);
    }

    /**
     * @throws GuzzleException
     */
    public function searchCustomer(User $user)
    {
        $client = new Client;

        $response = $client->request('GET', "https://api.paymongo.com/v1/customers?email=$user->email", [
            'headers' => [
                'accept' => 'application/json',
                'authorization' => 'Basic '.base64_encode(config('paymongo.secret_key')),
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    //    public function updateCustomer(User $user)
    //    {
    //        $customer = $this->getCustomer($user);
    //
    //        $customer->update([
    //            'first_name' => $user->firstname,
    //            'last_name' => $user->lastname,
    //            'phone' => $user->mobile_number,
    //            'email' => $user->email,
    //        ]);
    //
    //        return $customer;
    //    }

    /**
     * @throws GuzzleException
     */
    public function deleteCustomer(User $user): void
    {
        $customer = $this->getCustomer($user);

        $customer->delete();

        $user->update([
            'paymongo_customer_id' => null,
        ]);
    }

    private function createCustomer(User $user)
    {
        $customer = Paymongo::customer()->create([
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'phone' => $user->mobile_number,
            'email' => $user->email,
            'default_device' => 'email',
        ]);

        $user->update([
            'paymongo_customer_id' => $customer->getData()['id'],
        ]);

        return $customer;
    }

    private function createPaymentIntent(float $amount, string $description)
    {
        //        $user = auth('sanctum')->user();
        //        $customer = $this->getCustomer($user);

        return Paymongo::paymentIntent()->create([
            'amount' => $amount,
            'payment_method_allowed' => ['card', 'gcash', 'paymaya'],
            'payment_method_options' => [
                'card' => [
                    'request_three_d_secure' => 'automatic',
                ],
            ],
            'description' => $description,
            'statement_descriptor' => 'Suitescape PH',
            'currency' => 'PHP',
            'capture_type' => 'manual',
            //            'setup_future_usage' => [
            //                'session_type' => 'on_session',
            //                'customer_id' => $customer->getData()['id'],
            //            ],
        ]);
    }

    private function createPaymentMethod(
        string $paymentMethodType,
        array $paymentMethodDetails,
        array $billingDetails,
        array $billingAddress
    ) {
        return Paymongo::paymentMethod()->create([
            'type' => $paymentMethodType,
            'details' => $paymentMethodDetails,
            'billing' => array_merge($billingDetails, [
                'address' => $billingAddress,
            ]),
        ]);
    }
}
