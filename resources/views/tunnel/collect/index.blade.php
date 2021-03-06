@extends('layouts.app')

@section('title', '穿透隧道')

@section('content')
    <h1 class="mdui-text-color-theme">启动集</h1>

    <br /><br />
    <div class="mdui-table-fluid">
        <table class="mdui-table mdui-table-hoverable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>协议</th>
                    <th>内部地址</th>
                    <th>外部地址</th>
                    <th>共享带宽</th>
                    <th>属于服务器</th>
                    <th>属于项目</th>
                    <th>总价格</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody class="mdui-typo">
                <tr>
                    <td colspan="11" class="mdui-text-center">
                        <a href="{{ route('tunnels.create') }}">新建 隧道</a> 或者 <a href="{{ route('tunnels.create') }}">新建 启动集</a>
                    </td>
                </tr>
                @php($i = 1)
                @foreach ($tunnels as $tunnel)
                    <tr>
                        <td nowrap>{{ $i++ }}</td>
                        <td nowrap>{{ $tunnel->name }}</td>
                        <td nowrap>{{ $tunnel->protocol }}</td>
                        <td nowrap>{{ $tunnel->local_address }}</td>
                        <td nowrap>
                            @switch($tunnel->protocol)
                                @case('http')
                                    {{ $tunnel->custom_domain }}
                                @break
                                @case('https')
                                    {{ $tunnel->custom_domain }}
                                @break
                                @default
                                    {{ $tunnel->server->address }}:{{ $tunnel->remote_port }}

                            @endswitch

                        </td>
                        <td nowrap>{{ $tunnel->server->network_limit }} Mbps</td>
                        <td nowrap>{{ $tunnel->server->name }}</td>
                        <td nowrap><a
                                href="{{ route('projects.show', $tunnel->project->id) }}">{{ $tunnel->project->name }}</a>
                        </td>
                        <td nowrap>{{ $tunnel->server->price }}/m
                        </td>

                        <td nowrap><a href="{{ route('tunnels.show', $tunnel->id)}}">配置文件</a> |
                            <a href="#"
                                onclick="if (confirm('删除后，该隧道将无法再次启动，并且还有可能面临端口被占用的风险。')) { $('#f-{{ $i }}').submit() }">删除</a>
                            <form id="f-{{ $i }}" method="post"
                                action="{{ route('tunnels.destroy', $tunnel->id) }}">
                                @csrf
                                @method('DELETE')</form>
                        </td>

                    </tr>
                @endforeach
                @if ($i > 10)
                    <tr>
                        <td colspan="11" class="mdui-text-center">
                            <a href="{{ route('tunnels.create') }}">Create A Frp Tunnel Please (miao~)</a>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>


@endsection
