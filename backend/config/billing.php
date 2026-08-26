<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway
    |--------------------------------------------------------------------------
    |
    | Which implementation of App\Services\Billing\PaymentGateway takes money.
    | "manual" shows the owner payment instructions and waits for a super-admin
    | to confirm the transfer — a real way to bill a handful of restaurants,
    | and the one that works without a provider account.
    |
    | Adding a provider means writing one class, registering it in the map
    | inside AppServiceProvider, and changing this value.
    |
    */

    'gateway' => env('BILLING_GATEWAY', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Free trial
    |--------------------------------------------------------------------------
    |
    | Days of full access a restaurant gets on registration, before it has to
    | pay. Set to 0 to charge from the moment they sign up.
    |
    */

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Manual payment instructions
    |--------------------------------------------------------------------------
    |
    | Shown on the checkout screen when the manual gateway is in use. Put your
    | bank details or contact instructions here.
    |
    */

    'manual_instructions' => env('BILLING_MANUAL_INSTRUCTIONS'),

];
