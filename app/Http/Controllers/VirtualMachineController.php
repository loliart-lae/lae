<?php

namespace App\Http\Controllers;

use Proxmox\Nodes;
use Proxmox\Access;
use Proxmox\Cluster;
use Proxmox\Storage;
use App\Models\Server;
use Illuminate\Support\Str;
use Proxmox\Request as Pve;
use Illuminate\Http\Request;
use App\Models\VirtualMachine;
use App\Models\VirtualMachineUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\VirtualMachineTemplate;

class VirtualMachineController extends Controller
{

    // 这段控制器代码部分借鉴于 ProKVM
    // 如：依据配置获取节点，获取虚拟机存储位置以及镜像等
    // 非常感谢！

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $virtualMachines = VirtualMachine::with(['template', 'server', 'project'])->whereHas('member', function ($query) {
            $query->where('user_id', Auth::id());
        })->orderBy('project_id')->get();

        return view('virtualMachine.index', compact('virtualMachines'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Server $server, VirtualMachineTemplate $virtualMachineTemplate)
    {
        // 选择服务器
        $servers = $server->where('free_mem', '>=', '4096')->where('free_disk', '>=', '20')->where('type', 'pve')->get();

        // 列出模板
        $templates = $virtualMachineTemplate->orderBy('price')->get();

        return view('virtualMachine.create', compact('servers', 'templates'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Cluster $cluster, Nodes $nodes, VirtualMachine $virtualMachine, VirtualMachineUser $virtualMachineUser, Access $access)
    {
        $this->validate($request, [
            'server_id' => 'required',
            'image_id' => 'required',
            'name' => 'required|max:10',
            'template_id' => 'required',
            'start_after_created' => 'nullable|boolean',
            'bios' => 'boolean|required',
        ]);

        if (count($request->image_id) > 2) {
            return redirect()->back()->with('status', '最多只能插入两个 CD-ROM。');
        }

        if (!ProjectMembersController::userInProject($request->project_id)) {
            return redirect()->back()->with('status', '你不在项目中。');
        }

        $template = $this->validServer($request->server_id, $request->template_id);
        if (!$template) {
            return redirect()->back()->with('status', '服务器上没有更多的资源了。');
        }

        $vlan = Server::where('id', $request->server_id)->firstOrFail()->external;
        $this->login($request->server_id);

        if (isset($request->image_id[0])) {
            $image1 = $this->checkImage($request->server_id, $request->image_id[0]);
        }
        if (isset($request->image_id[1])) {
            $image2 = $this->checkImage($request->server_id, $request->image_id[1]);
        } else {
            $image2 = 'none';
        }


        if (!$image1 && !$image2) {
            return redirect()->back()->with('status', '找不到镜像。');
        }

        if ($request->bios) {
            $bios = 'ovmf';
        } else {
            $bios = 'seabios';
        }

        // 获取 VMID
        $response = $cluster->nextVmid();
        $next_vmid = $response->data;

        // 选择节点
        $response = $cluster->Resources('node');
        $node_name = $response->data[0]->node;

        $storage_name = $this->getVmStorage($request->server_id);

        if ($request->start_after_created) {
            $status = 1;
        } else {
            $status = 0;
        }

        $virtualMachine->name = $request->name;
        $virtualMachine->template_id = $request->template_id;
        $virtualMachine->project_id = $request->project_id;
        $virtualMachine->server_id = $request->server_id;
        $virtualMachine->status = $status;
        $virtualMachine->node = $node_name;
        $virtualMachine->vm_id = $next_vmid;
        $virtualMachine->bios = $request->bios;
        $virtualMachine->storage_name = $storage_name;
        $virtualMachine->save();

        $virtualMachineUser->username = 'ae-' . $virtualMachine->id;
        $virtualMachineUser->password = Str::random();
        $virtualMachineUser->save();

        $virtualMachine->where('id', $virtualMachine->id)->update([
            'user_id' => $virtualMachineUser->id,
        ]);

        $access->createUser([
            'userid' => $virtualMachineUser->username . '@pve',
            'password' => $virtualMachineUser->password,
        ]);

        $create = $nodes->createQemu($node_name, [
            'vmid' => $next_vmid,
            'name' => 'ae-' . $virtualMachine->id,
            'description' => '这个虚拟机是由 Open App Engine 创建的。创建者是: ' . Auth::user()->name . ', 邮箱: ' . Auth::user()->email . ', 项目ID: ' . $request->project_id . ', 创建时间: ' . $virtualMachine->created_at . '。',
            'scsihw' => 'lsi',
            'ostype' => 'other',
            'cores' => $template->cpu,
            'sockets' => 1,
            'numa' => 0,
            'memory' => $template->memory,
            'sata0' => $storage_name . ':' . $template->disk . ',cache=writethrough,ssd=1,mbps_rd=' . $template->disk_read . ',mbps_wr=' . $template->disk_write,
            'ide1' => $image1 . ',media=cdrom',
            'ide2' => $image2 . ',media=cdrom',
            'net0' => 'e1000,bridge=' . $vlan . ',firewall=1,rate=' . $template->network_limit,
            'kvm' => 1,
            'start' => $status,
            'bios' => $bios
        ]);
        if (is_null($create->data)) {
            $virtualMachine->where('id', $virtualMachine->id)->delete();

            $access->deleteUser($virtualMachineUser->username . '@pve');
            $virtualMachineUser->where('id', $virtualMachineUser->id)->delete();

            ProjectActivityController::save($request->project_id, '尝试创建虚拟机:' . $request->name . '但是失败了，因为服务器出现了问题。');
            return redirect()->route('virtualMachine.index')->with('status', '无法创建虚拟机。');
        } else {
            // 创建 ACL
            $access->updateAcl([
                'path' => '/vms/' . $next_vmid,
                'roles' => 'PVEVMUser',
                'users' => $virtualMachineUser->username . '@pve'
            ]);

            // 减去配额
            $server_data = Server::where('id', $request->server_id)->where('type', 'pve')->firstOrFail();
            $template_data = VirtualMachineTemplate::where('id', $request->template_id)->firstOrFail();
            Server::where('id', $request->server_id)->update([
                'free_mem' => $server_data->free_mem - $template_data->memory,
                'free_disk' => $server_data->free_disk - $template_data->disk,
            ]);

            // 获取并更新虚拟机信息
            // $virtualMachine->disk = 'vm-' . $next_vmid . '-disk-0';
            // $virtualMachine->net = 'vm-' . $next_vmid . '-disk-0';
            for ($i = 0; $i < 50; $i++) {
                try {
                    $vm_data = $nodes->qemuConfig($virtualMachine->node, $virtualMachine->vm_id)->data;

                    $disk_name = explode(':', $vm_data->sata0);
                    $disk_name = explode(',', $disk_name[1]);
                    $disk_name = $disk_name[0];
                    $net = explode(',', $vm_data->net0);
                    $net = $net[0] . ',' . $net[1] . ',' . $net[2];
                    $virtualMachine->where('id', $virtualMachine->id)->update([
                        'disk' => $disk_name,
                        'net' => $net
                    ]);
                    $status = 1;
                } catch (\Exception $e) {
                    $status = 0;
                }
                if ($status) {
                    break;
                }
            }

            ProjectActivityController::save($request->project_id, '创建了虚拟机: ' . $request->name . '。');
            return redirect()->route('virtualMachine.index')->with('status', '成功创建了虚拟机。');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Access $access)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachineUser = new VirtualMachineUser();

        $virtualMachine_where = $virtualMachine->where('id', $id)->with(['dash_user', 'server']);
        $virtualMachine_data = $virtualMachine_where->firstOrFail();

        if (!ProjectMembersController::userInProject($virtualMachine_data->project_id)) {
            return redirect()->back()->with('status', '你不在项目中。');
        }

        $this->login($virtualMachine_data->server_id);

        // 登录到子账号
        $user = [
            'username' => $virtualMachine_data->dash_user->username,
            'password' => $virtualMachine_data->dash_user->password,
            'realm' => 'pve'
        ];

        $ticket = $access->createTicket($user);
        $ticket = $ticket->data->ticket;

        $data = [
            'host' => $virtualMachine_data->server->address,
            'vm_id' => $virtualMachine_data->vm_id,
            'node' => $virtualMachine_data->node,
            'ticket' => $ticket,
            'name' => $virtualMachine_data->name,
            'domain' => $virtualMachine_data->server->domain,
            'id' => $virtualMachine_data->id
        ];

        ProjectActivityController::save($virtualMachine_data->project_id, '进入了虚拟机: ' . $virtualMachine_data->name . '的控制台。');


        return view('virtualMachine.vnc', compact('data'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachineTemplate = new VirtualMachineTemplate();

        $virtualMachine_where = $virtualMachine->where('id', $id)->with(['dash_user', 'server']);
        $virtualMachine = $virtualMachine_where->firstOrFail();

        // 列出模板
        $templates = $virtualMachineTemplate->orderBy('price')->get();

        if (!ProjectMembersController::userInProject($virtualMachine->project_id)) {
            return redirect()->back()->with('status', '你不在项目中。');
        }

        return view('virtualMachine.edit', compact('virtualMachine', 'templates'));
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
            'name' => 'required|max:10',
            'image_id' => 'nullable',
            'remove_cd_rom' => 'boolean',
            'ip_address' => 'nullable|ip'
        ]);

        if (!is_null($request->image_id)) {
            if (count($request->image_id) > 2) {
                return redirect()->back()->with('status', '最多只能挂载两个镜像。');
            }
        }

        $virtualMachine = new VirtualMachine();
        $virtualMachine_where = $virtualMachine->where('id', $id);
        $virtualMachine_data = $virtualMachine_where->firstOrFail();

        $project_id = $virtualMachine_data->project_id;

        if (!ProjectMembersController::userInProject($project_id)) {
            return redirect()->back()->with('status', '你不在项目中。');
        }

        if (is_null($virtualMachine_data->storage_name) || is_null($virtualMachine_data->disk) || is_null($virtualMachine_data->net)) {
            return redirect()->back()->with('status', '无法修改虚拟机。因为你的虚拟机版本与我们的数据库存储的版本不匹配，请尝试重建虚拟机。');
        }

        if ($request->remove_cd_rom) {
            $request->image_id = null;
        }

        $virtualMachine_where->update([
            'name' => $request->name
        ]);

        $this->changeVmImage($id, $request->image_id);
        if ($virtualMachine_data->template_id != $request->template_id) {
            if (!$this->changeVmTemplate($id, $request->template_id)) {
                return redirect()->back()->with('status', '无法更改模板，可能是目标模板配置小于当前模板。');
            }
        }

        // if (!$this->setIpFilter($id, $request->ip_address)) {
        //     return redirect()->back()->with('status', '这个IP地址已被局域网中的其他虚拟机使用了。w');
        // }

        ProjectActivityController::save($project_id, '修改了虚拟机: ' . $virtualMachine_data->name . '，新的名称为: ' . $request->name);


        return redirect()->back()->with('status', '已修改虚拟机。');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachine_where = $virtualMachine->where('id', $id)->with('dash_user');
        $virtualMachine_data = $virtualMachine_where->firstOrFail();

        $project_id = $virtualMachine_data->project_id;

        if (!ProjectMembersController::userInProject($project_id)) {
            return redirect()->back()->with('status', '你不在项目中。');
        }

        // if ($virtualMachine_data->status) {
        //     return redirect()->back()->with('status', '你必须关闭虚拟机电源才能删除。');
        // }

        // 不管了，总之就是删掉了
        $this->deleteVm($id);

        ProjectActivityController::save($project_id, '删除了虚拟机 ' . $virtualMachine_data->name . '。');

        return redirect()->back()->with('status', '虚拟机 ' .  $virtualMachine_data->name . ' 已删除');
    }

    public function deleteVm($id)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachine_where = $virtualMachine->where('id', $id);
        $virtualMachine_data = $virtualMachine_where->firstOrFail();

        try {
            $this->login($virtualMachine_data->server_id);
            $nodes = new Nodes();

            for ($i = 0; $i < 100; $i++) {
                $data = $nodes->qemuCurrent($virtualMachine_data->node, $virtualMachine_data->vm_id)->data;
                if ($data->status == 'running') {
                    $nodes->qemuStop(
                        $virtualMachine_data->node,
                        $virtualMachine_data->vm_id
                    );
                } else {
                    $nodes->deleteQemu($virtualMachine_data->node, $virtualMachine_data->vm_id);

                    $this->deleteUser($virtualMachine_data->server_id, $virtualMachine_data->user_id);
                }

                if (is_null($nodes->qemuCurrent($virtualMachine_data->node, $virtualMachine_data->vm_id))) {
                    // 返回null，代表成功已删除。
                    // 归还配额
                    $server_data = Server::where('id', $virtualMachine_data->server_id)->where('type', 'pve')->firstOrFail();
                    $template_data = VirtualMachineTemplate::where('id', $virtualMachine_data->template_id)->firstOrFail();
                    Server::where('id', $virtualMachine_data->server_id)->update([
                        'free_mem' => $server_data->free_mem + $template_data->memory,
                        'free_disk' => $server_data->free_disk + $template_data->disk,
                    ]);

                    return true;
                }

                sleep(0.5);
            }

            return false;
        } catch (\Exception $e) {
            Log::error($e);
            return false;
        }
    }

    public function deleteUser($server_id, $user_id)
    {
        $virtualMachineUser = VirtualMachineUser::where('id', $user_id);
        $virtualMachineUser_data = $virtualMachineUser->firstOrFail();
        $this->login($server_id);
        $access = new Access();
        $user_id = $virtualMachineUser_data->username . '@pve';
        $access->deleteUser($user_id);
        $virtualMachineUser->delete();
    }

    public function getImage($server_id, $json = true)
    {
        $cache_key = 'pve_server_image_cache_' . $server_id;
        if (Cache::has($cache_key)) {
            $iso = Cache::get($cache_key);
        } else {
            $cluster = new Cluster();
            $storage = new Storage();
            $nodes = new Nodes();

            $this->login($server_id);

            // 获取节点
            $response = $cluster->Resources('node');
            $node_pve = $response->data[0]->node;
            // 获取 ISO 存放位置
            $response = $storage->Storage('dir');
            $storage_local = $response->data[0]->storage;
            // 列出 ISO 列表
            $response = $nodes->listStorageContent($node_pve, $storage_local);
            $iso = [];

            foreach ($response->data as $data) {
                if ($data->format == 'iso') {
                    array_push($iso, $data);
                }
            }

            Cache::put($cache_key, $iso, 600);
        }

        if ($json) {
            return response()->json($iso);
        } else {
            return $iso;
        }
    }

    private function checkImage($server_id, $image_id)
    {
        $images = $this->getImage($server_id, false);
        if (array_key_exists($image_id, $images)) {
            return $images[$image_id]->volid;
        } else {
            abort(404);
        }
    }


    private function getVmStorage($server_id)
    {
        $cache_key = 'pve_server_vm_storage_name_' . $server_id;

        if (Cache::has($cache_key)) {
            $storage_name = Cache::get($cache_key);
        } else {
            $storage = new Storage();

            // 获取虚拟机存放区域
            $response = $storage->Storage('lvmthin');
            $storage_name = $response->data[0]->storage;
            Cache::put($cache_key, $storage_name);
        }

        return $storage_name;
    }

    private function validServer($server_id, $template_id)
    {
        // 验证服务器规格是否足以开设这个模板的虚拟机
        $server = Server::where('id', $server_id)->where('type', 'pve')->firstOrFail();
        $template = VirtualMachineTemplate::where('id', $template_id)->firstOrFail();
        // 服务器内存 减去 模板内存 是否大于 10G
        if (($server->free_mem - $template->memory) > 4096 && ($server->free_disk - $template->disk) > 20) {
            return $template;
        } else {
            return false;
        }
    }

    private function login($server_id)
    {
        $result = Server::where('id', $server_id)->where('type', 'pve')->firstOrFail();
        $credentials = explode('|', $result->token);
        $configure = [
            'hostname' => $result->address,
            'username' => $credentials[0],
            'password' => $credentials[1],
        ];
        Pve::Login($configure);
    }

    public function togglePower($id)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachine_where = $virtualMachine->where('id', $id);
        $virtualMachine_data = $virtualMachine_where->firstOrFail();

        $project_id = $virtualMachine_data->project_id;

        if (!ProjectMembersController::userInProject($project_id)) {
            return redirect()->back()->with('status', '你不在项目中。');
        }
        $this->login($virtualMachine_data->server_id);
        $nodes = new Nodes();

        $power = $virtualMachine_data->status;
        if ($power == 0) {
            $power = 1;
            $status = '开';
            // 打开虚拟机电源
            $nodes->qemuStart(
                $virtualMachine_data->node,
                $virtualMachine_data->vm_id
            );
        } elseif ($power == 1) {
            $power = 0;
            $status = '关';
            // 关闭虚拟机电源
            $nodes->qemuStop(
                $virtualMachine_data->node,
                $virtualMachine_data->vm_id
            );
        }
        $virtualMachine_where->update([
            'status' => $power
        ]);

        ProjectActivityController::save($project_id, '操作虚拟机 ' . $virtualMachine_data->name . ' 的电源状态为 ' . $status . ' 。');

        return response()->json(
            [
                'status' => 1,
                'power' => $power
            ]
        );
    }

    private function changeVmImage($id, $image_id)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachine_data = $virtualMachine->where('id', $id)->firstOrFail();
        $this->login($virtualMachine_data->server_id);
        $nodes = new Nodes();


        if (isset($image_id[0])) {
            $image1 = $this->checkImage($virtualMachine_data->server_id, $image_id[0]);
        } else {
            $image1 = 'none';
        }

        if (isset($image_id[1])) {
            $image2 = $this->checkImage($virtualMachine_data->server_id, $image_id[1]);
        } else {
            $image2 = 'none';
        }

        $nodes->setQemuConfig($virtualMachine_data->node, $virtualMachine_data->vm_id, [
            'ide1' => $image1 . ',media=cdrom',
            'ide2' => $image2 . ',media=cdrom',
        ]);
    }

    private function setIpFilter($id, $ip_address)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachine_data = $virtualMachine->where('id', $id)->firstOrFail();
        // 检测这个IP地址是否为当前实例IP地址
        if ($ip_address != $virtualMachine_data->ip_address) {
            // 检测 IP 地址是否被局域网中的其他主机使用
            if ($virtualMachine->where('server_id', $virtualMachine_data->server_id)->where('ip_address', $ip_address)->exists()) {
                return false;
            }
        } else {
            // 不做任何修改
            return true;
        }

        $this->login($virtualMachine_data->server_id);
        $nodes = new Nodes();

        $ip_set = 'ae-' . $virtualMachine_data->id;


        // 当 修改IP地址时，应该先清除已有的地址
        $nodes->deleteQemuFirewallIpsetNameCidr($virtualMachine_data->node, $virtualMachine_data->vm_id, $ip_set, $virtualMachine_data->ip_address);

        if (!is_null($ip_address)) {
            // Ipset name
            $nodes->createQemuFirewallIpset($virtualMachine_data->node, $virtualMachine_data->vm_id, ['name' => $ip_set]);

            // 创建规则
            $nodes->addQemuFirewallIpsetName($virtualMachine_data->node, $virtualMachine_data->vm_id, $ip_set, ['cidr' => $ip_address]);
        }

        VirtualMachine::where('id', $virtualMachine_data->id)->update(['ip_address' => $ip_address]);

        return true;
    }

    private function changeVmTemplate($id, $template_id)
    {
        $virtualMachine = new VirtualMachine();
        $virtualMachineTemplate = new VirtualMachineTemplate();
        $virtualMachine_data = $virtualMachine->where('id', $id)->with(['template', 'server'])->firstOrFail();
        $virtualMachineTemplate_data = $virtualMachineTemplate->where('id', $template_id)->firstOrFail();

        // 模板不能够降级
        if ($virtualMachine_data->template->disk > $virtualMachineTemplate_data->disk || $virtualMachine_data->template->cpu > $virtualMachineTemplate_data->cpu || $virtualMachine_data->template->memory > $virtualMachineTemplate_data->memory) {
            return false;
        }

        $this->login($virtualMachine_data->server_id);
        $nodes = new Nodes();

        $nodes->setQemuConfig($virtualMachine_data->node, $virtualMachine_data->vm_id, [
            'cores' => $virtualMachineTemplate_data->cpu,
            'memory' => $virtualMachineTemplate_data->memory,
            'sata0' => $virtualMachine_data->storage_name . ':' . $virtualMachine_data->disk . ',cache=writethrough,ssd=1,mbps_rd=' . $virtualMachineTemplate_data->disk_read . ',mbps_wr=' . $virtualMachineTemplate_data->disk_write,
            'net0' => $virtualMachine_data->net . ',rate=' . $virtualMachineTemplate_data->network_limit,
        ]);

        // local-lvm:vm-107-disk-0,cache=writethrough,mbps_rd=10,mbps_wr=10,size=80G,ssd=on

        $nodes->qemuResize($virtualMachine_data->node, $virtualMachine_data->vm_id, [
            'disk' => 'sata0',
            'size' => '+' . $virtualMachineTemplate_data->disk - $virtualMachine_data->template->disk . 'G',
        ]);

        $virtualMachine_data = $virtualMachine->where('id', $id)->with('template')->update([
            'template_id' => $template_id
        ]);

        return true;
    }

    public function checkStatus($status)
    {
        if ($status == 'running') {
            return 1;
        } else {
            return 0;
        }
    }

    public function getAllVm($server_id)
    {
        $this->login($server_id);
        $cluster = new Cluster();
        $response = $cluster->Resources('node');
        $node_name = $response->data[0]->node;
        $nodes = new Nodes();
        $vms = $nodes->Qemu($node_name);
        foreach ($vms->data as $vm) {
            $vm_where = VirtualMachine::where('node', $node_name)->where('vm_id', $vm->vmid);
            if ($vm_where->exists()) {
                $vm_data = VirtualMachine::where('node', $node_name)->where('vm_id', $vm->vmid)->first();

                $vm->status = $this->checkStatus($vm->status);

                if ($vm_data->status != $vm->status) {
                    $vm_where->update(['status' => $vm->status]);
                }

                $cache_key = 'ae-vm-status-' . $vm_data->id;
                $cache_data = [
                    'id' => $vm_data->id,
                    'uptime' => $vm->uptime,
                    'cpu' => $vm->cpu,
                    'mem' => $vm->mem,
                    'max_mem' => $vm->maxmem,
                ];

                Cache::put($cache_key, $cache_data, 600);
            }
        }
    }
}
