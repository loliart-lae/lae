<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function retryUtil()
    {
        // 1 天后
        return now()->addDay();
    }

    private $to, $content;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($to, $content)
    {
        $this->to = $to;
        $this->content = $content;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::raw($this->content, function ($message) {
            // 发件人（你自己的邮箱和名称）
            $message->from(config('mail.from.address'), config('mail.from.name'));
            // 收件人的邮箱地址
            $message->to($this->to);
            // 邮件主题
            $message->subject(config('app.name') . ' 队列通知');
        });
    }
}
