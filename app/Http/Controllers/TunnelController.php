<?php

namespace App\Http\Controllers;

use Exception;
use Ramsey\Uuid\Uuid;
use App\Models\Server;
use App\Models\Tunnel;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Models\ProjectMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TunnelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tunnels = Tunnel::with(['server', 'project'])->whereHas('member', function ($query) {
            $query->where('user_id', Auth::id());
        })->orderBy('project_id')->get();

        return view('tunnel.index', compact('tunnels'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, Project $project, ProjectMember $member, Server $server)
    {
        // 选择服务器
        $servers = $server->where('type', 'tunnel')->get();

        return view('tunnel.create', compact('servers'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Tunnel $tunnel)
    {
        $this->validate($request, array(
            'name' => 'required',
            'protocol' => 'required',
            'local_address' => 'required',
            'project_id' => 'required',
            'server_id' => 'required'
        ));

        if (!ProjectMembersController::userInProject($request->project_id)) {
            return redirect()->back()->with('status', '项目不存在。');
        }

        if (!ServerController::existsTunnel($request->server_id)) {
            return redirect()->back()->with('status', '服务器不存在。');
        }

        $project_balance = Project::where('id', $request->project_id)->firstOrFail()->balance;
        if ($project_balance <= 1) {
            return redirect()->back()->with('status', '项目积分不足 1，还剩:' . $project_balance);
        }

        if ($request->protocol == 'http' || $request->protocol == 'https') {
            $request->remote_port = 0;
            $this->validate($request, array(
                "custom_domain" => 'required',
            ));

            if (str_contains($request->custom_domain, ',')) {
                return redirect()->back()->with('status', 'Error: 仅支持单域名。');
            }
        } elseif ($request->protocol == 'tcp' || $request->protocol == 'udp') {
            $request->custom_domain = null;
            $this->validate($request, array(
                "remote_port" => 'required',
            ));

            if ($request->remote_port < 1025 || $request->remote_port >= 65535) {
                return redirect()->back()->with('status', 'Error: 公网端口范围不正确，最低1025，最高65535。');
            }
        } else if ($request->protocol == 'xtcp') {
            $request->custom_domain = null;
            $request->remote_port = null;

            $this->validate($request, array(
                "sk" => 'required|alpha_dash|min:3|max:15',
            ));
        } else {
            return redirect()->back()->with('status', 'Error: 不支持的协议。');
        }


        // 检查本地地址是否合法
        if (strpos($request->local_address, ':') == false) {
            return redirect()->back()->with('status', 'Error: 内网地址校验失败。');
        }

        // 检测端口是否被占用
        if ($request->remote_port != null) {
            // 不为null
            if ($tunnel->where('remote_port', $request->remote_port)->where('server_id', $request->server_id)->exists()) {
                return redirect()->back()->with('status', 'Error: 在这个服务器上已经存在相同端口了。');
            }
        } else {
            // 反之，检查域名是否被占用
            if ($request->protocol != 'xtcp') {
                if ($tunnel->where('custom_domain', $request->custom_domain)->where('server_id', $request->server_id)->where('protocol', $request->protocol)->exists()) {
                    return redirect()->back()->with('status', 'Error: 在这个服务器上已经存在相同协议的域名了。');
                }
            }
        }

        $this::create_tunnel($request);

        ProjectActivityController::save($request->project_id, '新建了 穿透隧道 ' . $request->name);


        return redirect()->route('tunnels.index')->with('status', 'Tunnel 隧道新建成功。');
    }

    public static function create_tunnel($request)
    {
        $tunnel = new Tunnel();
        $tunnel->name = $request->name;
        $tunnel->protocol = $request->protocol;
        $tunnel->custom_domain = $request->custom_domain;
        $tunnel->local_address = $request->local_address;
        $tunnel->remote_port = $request->remote_port;
        $tunnel->client_token = Uuid::uuid6()->toString();
        $tunnel->server_id = $request->server_id;
        $tunnel->project_id = $request->project_id;
        $tunnel->sk = $request->sk;
        $tunnel->save();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $tunnel = Tunnel::where('id', $id)->with(['server', 'project'])->firstOrFail();

        if (!ProjectMembersController::userInProject($tunnel->project->id)) {
            return redirect()->to('/')->with('status', '你没有合适的权限。');
        }

        ProjectActivityController::save($tunnel->project->id, '展示了穿透隧道' . $tunnel->name . ' 的配置文件。');


        $address = explode('|', $tunnel->server->address);

        $ini = <<<EOF
# 这是你的配置文件，请将它填入frpc.ini
[common]
server_addr = {$address[0]}
server_port = {$address[1]}
user = $tunnel->client_token
token = lightart_top

EOF;

        $uuid = Uuid::uuid1()->toString();

        $id = $tunnel->id;
        $name = $tunnel->name;
        $client_token = $tunnel->client_token;
        $protocol = $tunnel->protocol;
        $local = explode(':', $tunnel->local_address);
        $local_ip = $local[0];
        $local_port = $local[1];
        $remote_port = $tunnel->remote_port;
        $ini .= PHP_EOL . <<<EOF

# {$tunnel->project->name} 的 $name 于服务器 {$tunnel->server->name}
[{$tunnel->server->id}|$id|$client_token]
type = $protocol
local_ip = $local_ip
local_port = $local_port
EOF . PHP_EOL;

        if ($protocol == 'tcp' || $protocol == 'udp') {
            $ini .= <<<EOF
remote_port = $remote_port

EOF;
        } elseif ($protocol == 'http' || $protocol == 'https') {
            $ini .= <<<EOF
custom_domains = {$tunnel->custom_domain}

EOF;
        } elseif ($protocol == 'xtcp') {
            $ini .= <<<EOF
sk = {$tunnel->sk}



# 以下的是对端配置文件，请不要复制或者使用！
# 如果你想让别人通过XTCP连接到你的主机，请将以下配置文件发给你信任的人。如果你不信任他人，请勿发送，这样会导致不信任的人也能通过XTCP连接到你的主机。
# XTCP 连接不能保证稳定性，并且也不会100%成功。

#------ 对端复制开始 --------
[common]
server_addr = $address[0]
server_port = $address[1]
user = lightart_top
token = lightart_top

[lae_visitor_{$uuid}]
type = xtcp
role = visitor
server_name = {$tunnel->server->id}|$id|$client_token
sk = {$tunnel->sk}
bind_addr = 127.0.0.1
bind_port = $local_port

#------ 对端复制结束 --------

# 觉得好用的话，能否将Light App Engine(https://lightart.top) 推荐给你的好友？算是我们一个小小的请求，这对我们非常重要。
EOF;
        }

        return response($ini, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $tunnel = Tunnel::where('id', $id)->firstOrFail();
        if (!ProjectMembersController::userInProject($tunnel->project_id)) {
            return redirect()->to('/')->with('status', '你没有合适的权限。');
        }
        return view('tunnel.edit', compact('tunnel'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'local_address' => 'required',
            'reset_token' => 'boolean'
        ]);

        $tunnel = Tunnel::where('id', $id)->firstOrFail();
        if (!ProjectMembersController::userInProject($tunnel->project_id)) {
            return redirect()->to('/')->with('status', '你没有合适的权限。');
        }

        // 检查本地地址是否合法
        if (strpos($request->local_address, ':') == false) {
            return redirect()->back()->with('status', '内网地址校验失败。');
        }

        if ($request->reset_token) {
            $client_token = Uuid::uuid6()->toString();
            $msg = '，并且重置了隧道认证信息。';
        } else {
            $client_token = $tunnel->client_token;
            $msg = '。';
        }

        Tunnel::where('id', $id)->update(
            [
                'name' => $request->name,
                'local_address' => $request->local_address,
                'client_token' => $client_token
            ]
        );

        ProjectActivityController::save($tunnel->project->id, '修改了穿透隧道' . $tunnel->name . '，新的名字为 ' . $request->name . '，新的本机地址为 ' . $request->local_address . $msg);

        return redirect()->route('tunnels.index')->with('status', '隧道已修改。');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (self::deleteTunnel($id)) {
            return redirect()->back()->with('status', 'Tunnel 隧道已删除。');
        }
        return redirect()->back()->with('status', 'Tunnel 隧道删除失败。');
    }

    public static function deleteTunnel($id)
    {
        $tunnel = Tunnel::where('id', $id)->with(['server', 'project'])->firstOrFail();
        if (ProjectMembersController::userInProject($tunnel->project->id)) {
            // 删除
            Tunnel::where('id', $id)->delete();
            ProjectActivityController::save($tunnel->project->id, '删除了 穿透隧道 ' . $tunnel->name);

            return true;
        } else {
            return false;
        }
    }

    public function auth(Request $request, Tunnel $tunnel)
    {
        // Log::info($request);
        if ($request->op == 'NewProxy') {
            // Log::info($request);
            if (!$tunnel->where('server_id', $request->route('id'))->where('client_token', $request->content['user'])->exists()) {
                return response()->json(array(
                    "reject" => true,
                    "reject_reason" => "用户不存在",
                    "unchange" => true,
                ));
            }
            try {
                // 分割字符串 // proxy_type // $request->user['user]
                $client = explode('|', $request->content['proxy_name']);
                // 0: 服务器ID 1: 隧道ID
                // $sid = explode('.', $client[0])[1];
                $tid = $client[1];
                $token = $client[2];
            } catch (Exception $e) {
                unset($e);
                return response()->json(array(
                    "reject" => true,
                    "reject_reason" => "我怀疑你在搞事。",
                    "unchange" => true,
                ));
            }

            // 检查是否存在
            $tunnel_where = $tunnel->where('server_id', $request->route('id'))->where('id', $tid)->with(['project']);
            if ($tunnel_where->where('client_token', $token)->exists()) {
                // 检查端口之类的是否相等
                $tunnel_info = $tunnel_where->firstOrFail();

                $error_msg = '隧道 ' . $request->content['proxy_name'] . ' 的配置文件不正确。请确保配置文件复制正确。';
                if ($request->content['proxy_type'] == 'tcp' || $request->content['proxy_type'] == 'udp') {
                    if ($request->content['proxy_type'] == $tunnel_info->proxy_type || $request->content['remote_port'] != $tunnel_info->remote_port || $tunnel_info->remote_port < 1024) {
                        return response()->json(array(
                            "reject" => true,
                            "reject_reason" => $error_msg . '你可以前往Light App Engine(https://lightart.top)中重新复制。',
                            "unchange" => true,
                        ));
                    }
                } elseif ($request->content['proxy_type'] == 'http' || $request->content['proxy_type'] == 'https') {
                    if ($request->content['proxy_type'] == $tunnel_info->proxy_type || $request->content['custom_domains'][0] != $tunnel_info->custom_domain) {
                        return response()->json(array(
                            "reject" => true,
                            "reject_reason" => $error_msg . '域名在Light App Engine(https://lightart.top)中绑定。',
                            "unchange" => true,
                        ));
                    }
                }

                // ProjectActivityController::save($tunnel_info->project_id, '穿透隧道 ' . $tunnel_info->name . ' 登录成功。', true);

                return response()->json(array(
                    "reject" => false,
                    "unchange" => true,
                ));
            } else {
                return response()->json(array(
                    "reject" => true,
                    "reject_reason" => "隧道不存在",
                    "unchange" => true,
                ));
            }
        } elseif ($request->op == 'Ping') {
            $tunnel_orm = $tunnel->where('server_id', $request->route('id'))->where('client_token', $request->content['user']);
            if (!$tunnel_orm->exists()) {
                return response()->json(array(
                    "reject" => true,
                    "reject_reason" => "隧道不存在",
                    "unchange" => true,
                ));
            } else {
                $tunnel_orm->update([
                    'ping' => Carbon::now()
                ]);
            }
        }
    }
}