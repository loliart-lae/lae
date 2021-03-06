@extends('layouts.app')

@section('title', '更改容器')

@section('content')
    <div class="mdui-typo-display-2">更改容器</div>
    <br />
    <form method="post" action="{{ route('lxd.update', $lxd->id) }}">
        @csrf
        @method('PUT')

        <div class="mdui-row mdui-p-t-4 mdui-p-l-1">
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">名称</label>
                <input class="mdui-textfield-input" type="text" name="name" value="{{ $lxd->name }}" required />
            </div>
        </div>


        <br />
        <span class="mdui-typo-headline">选择容器模板</span>
        <p class="mdui-typo-subheading">容器模板影响着计费。计费每1分钟进行一次。</p>
        <br />
        <br />
        <div class="mdui-table-fluid">
            <table class="mdui-table mdui-table-hoverable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>名称</th>
                        <th>CPU</th>
                        <th>内存</th>
                        <th>硬盘</th>
                        <th>消耗积分</th>
                        {{-- <th>月付预估</th> --}}
                        <th>选择</th>
                    </tr>
                </thead>
                <tbody>
                    @php($i = 1)
                    @foreach ($templates as $template)
                        <tr>
                            <td nowrap>{{ $i++ }}</td>
                            <td nowrap>{{ $template->name }}</td>
                            <td nowrap>{{ $template->cpu }}</td>


                            <td nowrap>{{ $template->mem }} M</td>
                            <td nowrap>{{ $template->disk }} G</td>
                            <td nowrap>{{ $template->price }}</td>
                            {{-- <td nowrap>{{ $template->price * 44640 / 100 }} 元 / 月</td> --}}
                            <td nowrap>
                                <label class="mdui-radio">
                                    <input type="radio" value="{{ $template->id }}" name="template_id"
                                        @if ($template->id == $lxd->template_id) checked @endif required />
                                    <i class="mdui-radio-icon"></i>

                                </label>
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <br /><br />
        <button type="submit"
            class="mdui-float-right mdui-btn mdui-color-theme-accent mdui-ripple umami--click--lxd-update">修改</button>


    </form>
@endsection
