<?php

namespace App\Http\Controllers;

use App\Jobs\LxdJob;
use App\Models\Server;
use App\Models\Forward;
use App\Models\Project;
use App\Models\LxdImage;
use App\Models\LxdTemplate;
use App\Models\LxdContainer;
use Illuminate\Http\Request;
use App\Models\ProjectMember;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

class AppEngineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $lxdContainers = LxdContainer::with(['template', 'server', 'forward', 'image'])->whereHas('member', function ($query) {
            $query->where('user_id', Auth::id());
        })->orderBy('project_id')->get();
        // $lxdContainers = LxdContainer::with(['template', 'server', 'forward'])->has('project', function ($query) {
        //     $query->with(['member' => function ($query) {
        //         $query->where('user_id', Auth::id());
        //     }]);
        // })->orderBy('project_id')->get();


        return view('lxd.index', compact('lxdContainers'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, Project $project, ProjectMember $member, Server $server, LxdTemplate $lxdTemplate, LxdImage $lxdImage)
    {
        // 列出项目
        $projects = $member->where('user_id', Auth::id())->with('project')->get();

        // 选择服务器
        $servers = $server->where('free_disk', '>', '5')->where('free_mem', '>', '1024')->get();
        // 列出模板
        $templates = $lxdTemplate->get();

        // 列出镜像
        $images = $lxdImage->get();


        return view('lxd.create', compact('servers', 'templates', 'projects', 'images'));
    }

    // public function create_in_project(Request $request, Project $project, ProjectMember $member, Server $server, LxdTemplate $lxdTemplate)
    // {
    //     // 在选定的项目中新建容器
    //     if ($member->where('user_id', Auth::id())->where('project_id', $request->route('project_id'))->exists()) {
    //         // 选择服务器
    //         $servers = $server->where('free_disk', '>', '5')->where('free_mem', '>', '1024')->get();
    //         // 列出模板
    //         $templates = $lxdTemplate->get();



    //         return view('lxd.create_in_project', compact('servers', 'templates'));
    //     }
    // }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Project $project, ProjectMember $member, Server $server, LxdTemplate $lxdTemplate, LxdContainer $lxdContainer, LxdImage $lxdImage)
    {
        $this->validate($request, [
            'project_id' => 'required',
            'name' => 'required',
            'password' => 'required|alpha_dash',
            'image_id' => 'required',
            'server_id' => 'required'
        ]);

        $project_id = $request->project_id;
        // 在选定的项目中新建容器
        if ($member->where('user_id', Auth::id())->where('project_id', $project_id)->exists()) {

            // 预定义
            $lxdTemplate_data = $lxdTemplate->where('id', $request->template_id)->firstOrFail();
            $server_data = $server->where('id', $request->server_id)->firstOrFail();
            $server_where_id = $server->where('id', $request->server_id);

            // 检查服务器是否存在

            if (!$server_where_id->where('free_disk', '>', '5')->where('free_mem', '>', '1024')->exists()) {
                return redirect()->back()->with('status', '服务器不存在。');
            }

            if (!$lxdTemplate->where('id', $request->template_id)->exists()) {
                return redirect()->back()->with('status', '模板不存在。');
            }

            $lxdImage_where = $lxdImage->where('id', $request->image_id);
            if (!$lxdImage_where->exists()) {
                return redirect()->back()->with('status', '镜像不存在。');
            } else {
                $image_name = $lxdImage_where->first()->image;
            }

            // 检测内存是否足够
            if (!$server_data->free_mem - $lxdTemplate_data->mem > 1024) {
                return redirect()->back()->with('status', '服务器内存配额已满，请尝试更换服务器。');
            }

            // 减去模板内存
            $server_where_id->update(['free_mem' => $server_data->free_mem - $lxdTemplate_data->mem]);


            // 检测硬盘是否足够
            if (!$server_data->free_disk - $lxdTemplate_data->disk > 1024) {
                return redirect()->back()->with('status', '服务器硬盘配额已满，请尝试更换服务器。');
            }

            // 减去模板硬盘
            $server_where_id->update(['free_disk' => $server_data->free_disk - $lxdTemplate_data->disk]);

            // 合计价格
            $total = $server_data->price + $lxdTemplate_data->price;

            // 检测项目余额是否大于当前价格 + 0.5
            $project_balance = $project->where('id', $project_id)->firstOrFail()->balance;
            $check = $project->where('id', $project_id)->firstOrFail()->balance > $total + 0.5;
            if (!$check) {
                return redirect()->back()->with('status', '项目积分不足，还剩:' . $project_balance);
            }

            // 保存
            $lxdContainer->name = $request->name;
            $lxdContainer->project_id = $project_id;
            $lxdContainer->template_id = $request->template_id;
            $lxdContainer->server_id = $request->server_id;
            $lxdContainer->image_id = $request->image_id;
            $lxdContainer->save();

            $config = [
                'address' => $server_data->address,
                'token' => $server_data->token,
                'inst_id' => $lxdContainer->id,
                'cpu' => $lxdTemplate_data->cpu,
                'mem' => $lxdTemplate_data->mem,
                'disk' => $lxdTemplate_data->disk,
                'image' => $image_name,
                'password' => $request->password,
                'method' => 'init',
                'user' => Auth::id(),
            ];


            // 入列
            dispatch(new LxdJob($config));

            return redirect()->route('lxd.index')->with('status', '新建成功，正在调度。');
        } else {
            return redirect()->back()->with('status', '项目不存在。');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $member = new ProjectMember();
        $lxdContainer = new LxdContainer();
        $lxdTemplate = new LxdTemplate();

        $lxd = $lxdContainer->where('id', $id)->where('status', 'running')->with('template')->firstOrFail();

        if (!$member->where('user_id', Auth::id())->where('project_id', $lxd->project_id)->exists()) {
            return redirect()->back()->with('status', '你不在项目中。');
        }

        $selected_template = $lxd->template_id;
        // 列出模板
        $templates = $lxdTemplate->get();


        // 获取已启用的项目模板
        return view('lxd.update', compact('selected_template', 'templates', 'id'));
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
        $member = new ProjectMember();
        $lxdContainer = new LxdContainer();
        $lxdTemplate = new LxdTemplate();

        $lxd = $lxdContainer->where('id', $id)->where('status', 'running')->with('template')->firstOrFail();

        if (!$member->where('user_id', Auth::id())->where('project_id', $lxd->project_id)->exists()) {
            return redirect()->back()->with('status', '你不在项目中。');
        }

        if (!$lxdTemplate->where('id', $request->template_id)->exists()) {
            return redirect()->back()->with('status', '模板不存在。');
        }

        $lxdContainer_where = $lxdContainer->where('id', $id);
        $lxdContainer_data = $lxdContainer_where->firstOrFail();

        // 检查修改前修改后是否相同
        if ($request->template_id == $lxdContainer_data->template_id) {
            return redirect()->back()->with('status', '修改了个寂寞。');
        }


        // 将容器标记为 resizing
        $lxdContainer_where->update(['status' => 'resizing']);

        $config = [
            'method' => 'resize',
            'inst_id' => $id,
            'server_id' => $lxdContainer_data->server_id,
            'old_template' => $lxdContainer_data->template_id,
            'new_template' => $request->template_id,
            'user' => Auth::id()
        ];

        dispatch(new LxdJob($config));

        return redirect()->route('lxd.index')->with('status', '正在调整容器');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $member = new ProjectMember();
        $lxdContainer = new LxdContainer();
        $forwards = new Forward();
        $lxdContainer_data = $lxdContainer->where('id', $id)->with('server', 'template')->firstOrFail();
        if ($lxdContainer_data->status != 'running') {
            return redirect()->back()->with('status', '无法删除，因为容器还没有准备好。');
        }

        if ($forwards->where('lxd_id', $id)->count() > 0) {
            return redirect()->back()->with('status', '无法删除，因为容器绑定了端口转发。');
        }
        $project_id = $lxdContainer_data->project_id;
        $server_where_id = $lxdContainer_data->server;
        if ($member->where('user_id', Auth::id())->where('project_id', $project_id)->exists()) {
            // 调度删除任务
            $config = [
                'inst_id' => $id,
                'method' => 'delete',
                'address' => $server_where_id->address,
                'token' => $server_where_id->token,
                'server_id' => $server_where_id->id,
                'mem' => $lxdContainer_data->template->mem,
                'disk' => $lxdContainer_data->template->disk

            ];
            dispatch(new LxdJob($config));

            //

            // 删除
            $lxdContainer->where('id', $id)->delete();
        }

        return redirect()->back()->with('status', '容器已安排删除。');
    }
}
