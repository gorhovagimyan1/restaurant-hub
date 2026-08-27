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

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    |
    | Used when BILLING_GATEWAY=stripe. Card details are entered on Stripe's
    | own hosted page, so nothing sensitive reaches this application.
    |
    | The webhook secret is what makes payment trustworthy: without it anyone
    | who can POST to the endpoint could grant themselves a subscription. Get
    | it from the Stripe dashboard when you add the endpoint
    | (POST /api/webhooks/stripe) subscribed to `checkout.session.completed`
    | and `checkout.session.expired`.
    |
    | Locally, `stripe listen --forward-to localhost:8000/api/webhooks/stripe`
    | prints a secret to use instead.
    |
    */

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // Where Stripe returns the owner after the hosted page. Both point at
        // the SPA's checkout screen, which reads the query flag.
        'success_url' => env('STRIPE_SUCCESS_URL', env('APP_FRONTEND_URL', 'http://localhost:5173').'/checkout?paid=1'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('APP_FRONTEND_URL', 'http://localhost:5173').'/checkout?cancelled=1'),
    ],

];
