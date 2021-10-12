<?php

namespace App\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class UserStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $windows_servers = Server::where('type', 'windows')->get();


        // 获取 Windows 服务器 资源占用
        foreach ($windows_servers as $windows_server) {
            $result = Http::retry(5, 100)->get("http://{$windows_server->address}/status", [
                'token' => $windows_server->token
            ]);

            Cache::put('windows_server_' . $windows_server->id, json_encode([
                'cpu' => $result['cpu'],
                'mem' => $result['ram']
            ]), 600);
        }
    }
}
