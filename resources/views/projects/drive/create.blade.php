@extends('layouts.app')

@section('title', '新建或者上传文件')

@section('content')
    <h1 class="mdui-text-color-theme">新建文件夹 或者 上传文件</h1>


    <form method="POST" action="{{ route('storage.store', Request::route('project_id')) }}">
        @csrf
        <input type="hidden" name="path" value="{{ $path }}" />
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">文件夹名称</label>
            <input class="mdui-textfield-input" type="text" name="name" value="{{ old('name') }}" required />
        </div>

        <button type="submit" class="mdui-btn mdui-color-theme-accent mdui-ripple">新建</button>
    </form>



    <div class="mdui-typo">
        <hr />
    </div>
    <br /><br />

    <span class="mdui-typo-headline">如果要将文件设置为仅项目成员可下载，请将文件名以"_"开头</span>
    <p>你无法在根目录上传文件。</p>
    <div class="form-group">
        <form method="POST" action="{{ route('storage.store', Request::route('project_id')) }}"
            enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}" />
            <div class="form-group">
                <input type="file" name="file" placeholder="上传文件">
                <small class="form-text text-muted">选择文件并上传到/{{ $path }}下</small>
            </div>

               <button type="submit" class="mdui-btn mdui-color-theme-accent mdui-ripple">上传</button>
        </form>
    </div>
@endsection