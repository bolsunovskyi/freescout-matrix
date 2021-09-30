<?php

namespace Modules\MatrixNotification\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;


class MatrixNotificationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        \Eventy::addFilter('settings.sections', function($sections) {
            $sections['matrixnotification'] = ['title' => __('Matrix Notifications'), 'icon' => 'envelope', 'order' => 800];

            return $sections;
        }, 30);

        // Section settings
        \Eventy::addFilter('settings.section_settings', function($settings, $section) {

            if ($section != 'matrixnotification') {
                return $settings;
            }

            $settings = \Option::getOptions([
                'matrixnotification.active',
                'matrixnotification.homeserver',
                'matrixnotification.access_token',
                'matrixnotification.room',
                'matrixnotification.events',
            ]);


            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function($view, $section) {
            if ($section != 'matrixnotification') {
                return $view;
            } else {
                return 'matrixnotification::index';
            }
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function($params, $section) {
            if ($section != 'matrixnotification') {
                return $params;
            }

            $params = [
                'template_vars' => [
                    'events' => [
                        'conversation.created'        => __('Conversation Created'),
                        'conversation.assigned'       => __('Conversation Assigned'),
                        'conversation.note_added'     => __('Conversation Note Added'),
                        'conversation.customer_replied' => __('Conversation Customer Reply'),
                        'conversation.user_replied'    => __('Conversation Agent Reply'),
                        'conversation.status_changed'  => __('Conversation Status Updated'),
                    ],
                    'active' => \Option::get('matrixnotification.active'),
                ],
                'validator_rules' => [],
            ];

            return $params;
        }, 20, 2);

        \Eventy::addAction('matrixnotification.post', function($conversation, $pretext, $fields = []) {
            if (!\Option::get('matrixnotification.active')) {
                return false;
            }

            // Default fields.
            $default_fields = [
                'conversation' => [
                    'title' => self::escape($conversation->getSubject()),
                ],
                'customer' => [
                    'title' => __('Customer'),
                    'short' => true,
                ],
                'mailbox' => [
                    'title' => __('Mailbox'),
                    'short' => true,
                ],
            ];

            // Remove mailbox if there is only one active mailbox.
            $mailboxes = \App\Mailbox::getActiveMailboxes();
            if (count($mailboxes) == 1) {
                unset($default_fields['mailbox']);
            }

            if (!is_array($fields)) {
                $fields = [];
            }
            $fields = array_merge($default_fields, $fields);

            $formatted_fields = [];
            foreach ($fields as $name => $field) {
                if (!$field) {
                    continue;
                }
                if (empty($field['value'])) {
                    $value = '';
                    switch ($name) {
                        case 'conversation':
                            $value = $conversation->getLastReply()->body;
                            $value = \Helper::htmlToText($value);
                            break;
                        case 'customer':
                            $customer = $conversation->customer;
                            $email = $customer->getMainEmail();
                            $email_markup = '<a href="mailto:'.$email.'">'.$email.'</a>';
                            if ($customer->getFullName()) {
                                $value = $customer->getFullName().' '.$email_markup;
                            } else {
                                $value = $email_markup;
                            }
                            break;
                        case 'mailbox':
                            $mailbox = $conversation->mailbox;
                            if ($mailbox) {
                                $value = $mailbox->name;
                            }
                            break;
                    }

                    $field['value'] = $value;
                    $fields[$name]['value'] = $value;
                }
                if ($name != 'conversation') {
                    $formatted_fields[] = $field;
                }
            }
            $pretext = $pretext.' <a href="'.$conversation->url().'">#'.$conversation->number.'</a>';

            // Conversation field becomes a text.
            $text = '';
            if ($fields['conversation']) {
                $text = '<b>'.$fields['conversation']['title']."</b><br />";
                $text .= $fields['conversation']['value'];
            }

            $message = $pretext."<br /><br />".$text;

            // Add fields;
            foreach ($fields as $key => $field) {
                if ($key == 'conversation') {
                    continue;
                }
                $message .= "<br /><br /><b>".$field['title']."</b><br />".$field['value'];
            }

            try {
                $opts = \Option::getOptions([
                    'matrixnotification.homeserver',
                    'matrixnotification.access_token',
                    'matrixnotification.room',
                ]);
                $url = sprintf("%s/_matrix/client/r0/rooms/%s/send/m.room.message/%d",
                    $opts['matrixnotification.homeserver'], $opts['matrixnotification.room'], time());

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_CUSTOMREQUEST => "PUT",
                    CURLOPT_POSTFIELDS => json_encode([
                        'msgtype' => 'm.text',
                        'formatted_body' => $message,
                        'body' => $message,
                        'format' => 'org.matrix.custom.html',
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $opts['matrixnotification.access_token'],
                        'Content-Type: application/json',
                    ],
                ]);
                curl_exec($ch);
            } catch (\Exception $e) {
                \Helper::log('matrixnotification', 'API error: '.$e->getMessage());
            }
        }, 20, 4);

        // Conversation Created.
        \Eventy::addAction('conversation.created_by_user', function($conversation, $thread) {
            if (!self::isEventEnabled('conversation.created')) {
                return false;
            }
            $user_name = '';
            if ($conversation->created_by_user) {
                $user_name = $conversation->created_by_user->getFullName();
            }
            \Helper::backgroundAction('matrixnotification.post', [
                $conversation,
                __('A <b>New Conversation</b> was created by :user_name', [
                    'user_name'   => self::escape($user_name),
                ]),
            ]);
        }, 20, 2);

        \Eventy::addAction('conversation.created_by_customer', function($conversation, $thread) {
            if (!self::isEventEnabled('conversation.created')) {
                return false;
            }
            \Helper::backgroundAction('matrixnotification.post', [
                $conversation,
                __('A <b>New Conversation</b> was created'),
            ]);
        }, 20, 2);

        // Conversation assigned
        \Eventy::addAction('conversation.user_changed', function($conversation, $by_user) {
            if (!self::isEventEnabled('conversation.assigned')) {
                return false;
            }
            $assignee_name = '';
            if ($conversation->user_id && $conversation->user) {
                $assignee_name = $conversation->user->getFullName();
            }
            \Helper::backgroundAction('matrixnotification.post', [
                $conversation,
                __('Conversation <b>assigned</b> to <b>:assignee_name</b> by :user_name', [
                    'assignee_name' => self::escape($assignee_name),
                    'user_name'     => self::escape($by_user->getFullName()),
                ]),
            ]);
        }, 20, 2);

        // Note added.
        \Eventy::addAction('conversation.note_added', function($conversation, $thread) {
            if (!self::isEventEnabled('conversation.note_added')) {
                return false;
            }
            $note_text = $thread->body;
            $note_text = \Helper::htmlToText($note_text);
            $note_text = self::escape($note_text);

            $fields['conversation'] = [
                'title' => $conversation->getSubject(),
                'value' => $note_text,
            ];

            \Helper::backgroundAction('matrixnotification.post', [
                $conversation,
                __('A <b>note was added</b> by :user_name', [
                    'user_name'     => self::escape($thread->created_by_user->getFullName()),
                ]),
                $fields
            ]);
        }, 20, 2);

        // Conversation Customer Reply.
        \Eventy::addAction('conversation.customer_replied', function($conversation, $thread) {
            if (!self::isEventEnabled('conversation.customer_replied')) {
                return false;
            }
            \Helper::backgroundAction('matrixnotification.post', [
                $conversation,
                __('A customer <b>replied</b> to a conversation'),
            ]);
        }, 20, 2);

        // Conversation Agent Reply.
        \Eventy::addAction('conversation.user_replied', function($conversation, $thread) {
            if (!self::isEventEnabled('conversation.user_replied')) {
                return false;
            }
            \Helper::backgroundAction('matrixnotification.post', [
                $conversation,
                __(':user_name <b>replied</b>', [
                    'user_name' => self::escape($thread->created_by_user->getFullName()),
                ]),
            ]);
        }, 20, 2);

        // Conversation Status Updated
        \Eventy::addAction('conversation.status_changed', function($conversation, $user, $changed_on_reply) {
            if ($changed_on_reply || !self::isEventEnabled('conversation.status_changed')) {
                return false;
            }
            // Create a background job for posting a message.
            \Helper::backgroundAction('matrixnotification.post', [
                $conversation,
                __('Conversation <b>status changed</b> to <b>:status</b> by :user_name', [
                    'status'    => $conversation->getStatusName(),
                    'user_name' => $user->getFullName(),
                ]),
            ]);
        }, 20, 3);
    }

    public static function isEventEnabled($event)
    {
        $events = \Option::get('matrixnotification.events');
        if (empty($events) || !is_array($events) || !in_array($event, $events)) {
            return false;
        } else {
            return true;
        }
    }

    public static function escape($text)
    {
        return strtr($text, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('matrixnotification.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'matrixnotification'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/matrixnotification');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/matrixnotification';
        }, \Config::get('view.paths')), [$sourcePath]), 'matrixnotification');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
