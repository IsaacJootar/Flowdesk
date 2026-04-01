<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Transactional Mailer
    |--------------------------------------------------------------------------
    |
    | Flowdesk's operational emails already decide when to queue at the
    | workflow level. This mailer lets those workflows target a production
    | transport explicitly without changing every delivery call site.
    |
    */

    'transactional_mailer' => env('MAIL_TRANSACTIONAL_MAILER', env('MAIL_MAILER', 'log')),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "log", "array", "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'resend_smtp' => [
            // Resend SMTP lets us ship production email without adding a new
            // transport package to the app runtime.
            'transport' => 'smtp',
            'host' => env('RESEND_SMTP_HOST', 'smtp.resend.com'),
            'port' => env('RESEND_SMTP_PORT', 587),
            'encryption' => env('RESEND_SMTP_ENCRYPTION', 'tls'),
            'username' => env('RESEND_SMTP_USERNAME', 'resend'),
            'password' => env('RESEND_API_KEY'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'mailersend_smtp' => [
            // MailerSend SMTP transport (works without extra packages).
            'transport' => 'smtp',
            'host' => env('MAILERSEND_SMTP_HOST', 'smtp.mailersend.net'),
            'port' => env('MAILERSEND_SMTP_PORT', 587),
            'encryption' => env('MAILERSEND_SMTP_ENCRYPTION', 'tls'),
            'username' => env('MAILERSEND_SMTP_USERNAME'),
            'password' => env('MAILERSEND_SMTP_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'resend_failover' => [
            'transport' => 'failover',
            'mailers' => [
                'resend_smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'mailersend_failover' => [
            'transport' => 'failover',
            'mailers' => [
                'mailersend_smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
