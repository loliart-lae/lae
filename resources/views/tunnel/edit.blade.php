@extends('layouts.app')

@section('title', '修改隧道名称以及内网地址')

@section('content')
    <div class="mdui-typo-display-2">修改隧道名称以及内网地址</div>

    <br />
    <form method="post" action="{{ route('tunnels.update', $tunnel->id) }}">
        @csrf
        @method('PUT')

        <span class="mdui-typo-headline">内网地址</span>
        <p>被映射主机的地址，比如 127.0.0.1:80</p>
        <div class="mdui-textfield">
            <label class="mdui-textfield-label">内网地址</label>
            <input class="mdui-textfield-input" type="text" name="local_address" value="{{ $tunnel->local_address }}"
                required />
        </div>
        <br />

        <span class="mdui-typo-headline">隧道的名称</span>
        <p>只允许字母、数字，短破折号（-）和下划线（_）,至少 3 位，最多 15 位。该名称用于标识。</p>
        <div class="mdui-textfield">
            <label class="mdui-textfield-label">隧道名称</label>
            <input class="mdui-textfield-input" type="text" name="name" value="{{ $tunnel->name }}" required />
        </div>

        <br />
        <div class="mdui-row-md-4 mdui-m-b-2">
            <div class="mdui-col">
                <label class="mdui-checkbox" mdui-tooltip="{content: '如果你的隧道信息已被泄漏，那么勾选这个可能会很有用。当你勾选并提交后，使用该隧道的客户端将会被强制下线，你也必须申请新的隧道配置文件。', position: 'right'}">
                    <input type="checkbox" name="reset_token" value="1" />
                    <i class="mdui-checkbox-icon"></i>
                    重置隧道认证
                </label>
            </div>
        </div>

        <button type="submit" class="mdui-btn mdui-color-theme-accent mdui-ripple">修改</button>
    </form>
@endsection
