<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use SendGrid\Mail\Mail as SendGridMail;
use SendGrid\Mail\From;
use SendGrid\Mail\To;
use SendGrid\Mail\Subject;
use SendGrid\Mail\HtmlContent;
use SendGrid\Mail\PlainTextContent;
use SendGrid\Mail\MailSettings;
use SendGrid\Mail\SandBoxMode;
use SendGrid;

class SendGridServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('sendgrid', function ($app) {
            return new SendGrid(config('services.sendgrid.api_key'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Mail::extend('sendgrid', function (array $config) {
            return new class($config) {
                protected $config;

                public function __construct(array $config)
                {
                    $this->config = $config;
                }

                public function send($view, array $data = [], $callback = null)
                {
                    $sendGrid = app('sendgrid');
                    
                    $mail = new SendGridMail();
                    
                    // Set from address
                    $fromAddress = config('mail.from.address');
                    $fromName = config('mail.from.name');
                    $mail->setFrom(new From($fromAddress, $fromName));
                    
                    // Set subject
                    $subject = $view->getSubject();
                    $mail->setSubject(new Subject($subject));
                    
                    // Set content
                    $htmlContent = $view->render();
                    $mail->addContent(new HtmlContent($htmlContent));
                    
                    // Set recipients
                    foreach ($view->getTo() as $recipient) {
                        $mail->addTo(new To($recipient['address'], $recipient['name'] ?? ''));
                    }
                    
                    // Set CC recipients
                    foreach ($view->getCc() as $recipient) {
                        $mail->addCc(new To($recipient['address'], $recipient['name'] ?? ''));
                    }
                    
                    // Set BCC recipients
                    foreach ($view->getBcc() as $recipient) {
                        $mail->addBcc(new To($recipient['address'], $recipient['name'] ?? ''));
                    }
                    
                    // Set reply-to
                    foreach ($view->getReplyTo() as $recipient) {
                        $mail->setReplyTo(new From($recipient['address'], $recipient['name'] ?? ''));
                    }
                    
                    // Send the email
                    try {
                        $response = $sendGrid->send($mail);
                        return $response->statusCode() === 202;
                    } catch (\Exception $e) {
                        \Log::error('SendGrid email failed: ' . $e->getMessage());
                        return false;
                    }
                }
            };
        });
    }
}