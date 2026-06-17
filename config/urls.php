<?php

return [
    'front_office_fe_url'        => env('FRONT_OFFICE_FE_URL', 'http://localhost'),
    'payment_host'               => env('PAYMENT_HOST', 'http://localhost:5500'),
    'backoffice_fe_url'          => env('BACKOFFICE_FE_URL', 'http://localhost'),
    'password_reset_pathname'    => env('PASSWORD_RESET_PATHNAME', 'auth/reset-password/'),
    'email_verify_pathname'      => env('EMAIL_VERIFY_PATHNAME', 'auth/verify-email'),
    'user_approbation_pathname'  => env('USER_APPROBATION_PATHNAME', 'approval-pending'),
    'customer_order_details_pathname' => env('CUSTOMER_ORDER_DETAILS_PATHNAME', 'orders/'),
    'admin_order_details_pathname'    => env('ADMIN_ORDER_DETAILS_PATHNAME', 'orders/'),
    'refund_requests'            => env('REFUND_REQUESTS', 'refund-requests/'),
    'transaction_details'        => env('TRANSACTION_DETAILS', 'transactions/'),
    'account_settings_pathname'  => env('ACCOUNT_SETTINGS_PATHNAME', 'settings'),
    'user_detail_page_pathname'  => env('USER_DETAIL_PAGE_PATHNAME', 'users/'),
    'products_page_pathname'     => env('PRODUCTS_PAGE_PATHNAME', 'products'),
    'contact_page_pathname'      => env('CONTACT_PAGE_PATHNAME', 'support'),
    'admin_user_details_pathname'      => env('ADMIN_USER_DETAILS_PATHNAME', 'users/'),
];
