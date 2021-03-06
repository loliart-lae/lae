@extends('layouts.app')

@section('title', '修改 游戏服务器')

@section('content')
    <div class="mdui-typo-display-2">修改 游戏服务器</div>

    <form method="post" action="{{ route('gameServer.update', $id) }}">
        @csrf
        @method('PUT')

        <div class="mdui-row mdui-p-t-4 mdui-p-b-2 mdui-p-l-1">
            <span class="mdui-typo-headline">选择镜像</span>
            {{-- <p class="mdui-typo-subheading">不同镜像拥有着不同操作系统以及操作方式。</p> --}}
        </div>
        <div class="mdui-table-fluid">
            <table class="mdui-table mdui-table-hoverable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>镜像</th>
                        <th>选择</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($images as $image)
                        <tr>
                            <td nowrap>{{ $image->id }}</td>
                            <td nowrap>{{ $image->name }}</td>

                            <td>
                                <label class="mdui-radio">
                                    <input type="radio" value="{{ $image->id }}" name="image_id" @if ($pterodactylServer_data->image_id == $image->id) checked @endif
                                        required />
                                    <i class="mdui-radio-icon"></i>
                                </label>
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mdui-row mdui-p-t-4 mdui-p-b-2 mdui-p-l-1">
            <span class="mdui-typo-headline">选择配置模板</span>
            <p class="mdui-typo-subheading">配置模板影响着计费以及服务器性能。</p>
        </div>
        <div class="mdui-table-fluid">
            <table class="mdui-table mdui-table-hoverable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>名称</th>
                        <th>CPU限制</th>
                        <th>内存大小</th>
                        <th>虚拟内存</th>
                        <th>硬盘空间</th>
                        <th>块IO</th>
                        <th>数据库数量</th>
                        <th>备份数量</th>
                        <th>积分/分钟</th>
                        <th>月预估</th>
                        <th>选择</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td nowrap>{{ $template->id }}</td>
                            <td nowrap>{{ $template->name }}</td>
                            <td nowrap>{{ $template->cpu_limit }}</td>
                            <td nowrap>{{ $template->memory }} M</td>
                            <td nowrap>{{ $template->swap }} M</td>
                            <td nowrap>{{ $template->disk_space }} G</td>
                            <td nowrap>{{ $template->io }}</td>
                            <td nowrap>{{ $template->databases }}</td>
                            <td nowrap>{{ $template->backups }}</td>
                            <td nowrap>{{ $template->price }}</td>
                            <td nowrap>
                                {{ number_format(($template->price * 44640) / config('billing.exchange_rate'), 2) }} 元 /
                                月
                            </td>

                            <td>
                                <label class="mdui-radio">
                                    <input type="radio" value="{{ $template->id }}" name="template_id"
                                        @if ($pterodactylServer_data->template_id == $template->id) checked @endif required />
                                    <i class="mdui-radio-icon"></i>
                                </label>
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>


        <div class="mdui-row mdui-p-t-4 mdui-p-l-1">
            <span class="mdui-typo-headline">名称</span>
            <div class="mdui-textfield">
                <label class="mdui-textfield-label">名称</label>
                <input class="mdui-textfield-input" type="text" name="name" value="{{ $pterodactylServer_data->name }}" required />
            </div>
        </div>


        <div class="mdui-row mdui-p-y-2">
            <button type="submit"
                class="mdui-m-l-1 mdui-float-right mdui-btn mdui-color-theme-accent mdui-ripple umami--click--update-game-server">修改</button>
        </div>
        </div>
    </form>

    <div class="mdui-typo" style="text-align: right;margin-top: 10px"><small class="mdui-clearfix">
            注意：每分钟价格 = 地区服务器基础价格<br />
        </small></div>

@endsection
